"""
TriageApp — the single thing a user double-clicks (TriageApp.exe on Windows,
TriageApp.app on macOS).

Starts the two backend processes this app is actually made of (the
Laravel form/UI, served by a bundled portable PHP; the ML prediction API,
already frozen into its own TriageAPI binary by PyInstaller), waits for both
to answer a real health check, then opens a native app window pointing at
the triage form — no browser chrome, no address bar, no visible console
windows for the backends.

Layout this expects (works from any folder — nothing here is hardcoded to
a dev-machine path):

    Windows (flat folder, TriageApp.exe at the top):
        TriageApp.exe
        runtime/php/php.exe, php.ini, ext/
        laravel-app/            <- vendor/ already installed, .env set for sqlite
        api/TriageAPI.exe

    macOS (everything lives inside the .app bundle's Resources folder,
    since sys.executable itself is buried at Contents/MacOS/TriageApp):
        TriageApp.app/Contents/MacOS/TriageApp
        TriageApp.app/Contents/Resources/runtime/php/php
        TriageApp.app/Contents/Resources/laravel-app/
        TriageApp.app/Contents/Resources/api/TriageAPI/TriageAPI  (onedir build)
"""

import ctypes
import os
import signal
import subprocess
import sys
import time

import requests
import webview

IS_WINDOWS = sys.platform.startswith("win")
IS_MACOS = sys.platform == "darwin"

APP_URL = "http://127.0.0.1:8020/triage"
API_HEALTH_URL = "http://127.0.0.1:5055/health"
LARAVEL_HEALTH_URL = "http://127.0.0.1:8020/triage"

# Prevents php.exe's own console window from flashing up on Windows —
# TriageAPI.exe is already built --noconsole so it doesn't need this, but
# php.exe is a normal console-subsystem binary. No equivalent needed on
# macOS/Linux; subprocess never shows a console there in the first place.
CREATE_NO_WINDOW = 0x08000000


def base_dir() -> str:
    if not getattr(sys, "frozen", False):
        return os.path.dirname(os.path.abspath(__file__))

    if IS_MACOS:
        # sys.executable == .../TriageApp.app/Contents/MacOS/TriageApp —
        # the runtime/laravel-app/api folders live one level up, in
        # Contents/Resources, so the whole thing stays a single .app bundle
        # instead of a loose exe-plus-sibling-folders layout.
        return os.path.normpath(os.path.join(os.path.dirname(sys.executable), "..", "Resources"))

    return os.path.dirname(sys.executable)


BASE = base_dir()
PHP_EXE = os.path.join(BASE, "runtime", "php", "php.exe" if IS_WINDOWS else "php")
PHP_INI = os.path.join(BASE, "runtime", "php", "php.ini")
LARAVEL_ROOT = os.path.join(BASE, "laravel-app")
LARAVEL_PUBLIC = os.path.join(LARAVEL_ROOT, "public")

# Windows build is --onefile (flat api/TriageAPI.exe); macOS build is
# --onedir (api/TriageAPI/TriageAPI alongside its unpacked dependencies) —
# onedir avoids the bootloader-forks-a-child problem entirely (see
# cleanup()'s docstring below), so there's no reason to fight for onefile
# on macOS too.
API_EXE = (
    os.path.join(BASE, "api", "TriageAPI.exe")
    if IS_WINDOWS
    else os.path.join(BASE, "api", "TriageAPI", "TriageAPI")
)

processes: list[subprocess.Popen] = []


def _popen_kwargs() -> dict:
    if IS_WINDOWS:
        return {"creationflags": CREATE_NO_WINDOW}
    # New session/process group on POSIX so cleanup() can signal the whole
    # group at once, the same reason Windows needs `taskkill /T` below.
    return {"start_new_session": True}


def start_backends() -> None:
    processes.append(subprocess.Popen(
        [API_EXE],
        cwd=os.path.dirname(API_EXE),
        **_popen_kwargs(),
    ))

    processes.append(subprocess.Popen(
        [PHP_EXE, "-c", PHP_INI, "-S", "127.0.0.1:8020", "-t", LARAVEL_PUBLIC],
        cwd=LARAVEL_ROOT,
        **_popen_kwargs(),
    ))


def wait_until_ready(url: str, timeout: float = 45) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            if requests.get(url, timeout=1).status_code == 200:
                return True
        except requests.RequestException:
            pass
        time.sleep(0.4)
    return False


def cleanup() -> None:
    # On Windows, p.terminate() alone is not enough when a backend is a
    # PyInstaller --onefile build: its bootloader process spawns a separate
    # child process to actually run, and terminating just the tracked
    # (bootloader) PID leaves that child running and still holding its
    # port — confirmed via a real test (see project notes). `taskkill /T`
    # kills the whole process tree instead of just the one PID.
    #
    # On macOS the API build is --onedir (no bootloader re-exec, no forked
    # child), so a plain signal to the process group is sufficient — but it
    # still goes to the whole group via start_new_session, as cheap
    # insurance against any future switch back to onefile.
    for p in processes:
        try:
            if IS_WINDOWS:
                subprocess.run(
                    ["taskkill", "/F", "/T", "/PID", str(p.pid)],
                    creationflags=CREATE_NO_WINDOW,
                    capture_output=True,
                )
            else:
                os.killpg(os.getpgid(p.pid), signal.SIGTERM)
                p.wait(timeout=5)
        except Exception:
            try:
                p.kill()
            except Exception:
                pass


def fatal_error(message: str) -> None:
    if IS_WINDOWS:
        ctypes.windll.user32.MessageBoxW(0, message, "Triage Decision Support Tool", 0x10)
    elif IS_MACOS:
        # Native macOS alert via osascript — no extra Python dependency
        # (e.g. tkinter) needed just for a single startup-failure dialog.
        escaped = message.replace("\\", "\\\\").replace('"', '\\"')
        subprocess.run([
            "osascript", "-e",
            f'display alert "Triage Decision Support Tool" message "{escaped}" as critical',
        ])
    else:
        print(message, file=sys.stderr)


def main() -> None:
    start_backends()

    api_ready = wait_until_ready(API_HEALTH_URL)
    laravel_ready = wait_until_ready(LARAVEL_HEALTH_URL) if api_ready else False

    if not (api_ready and laravel_ready):
        cleanup()
        fatal_error(
            "The Triage Decision Support Tool couldn't start.\n\n"
            "The prediction service or the web app didn't respond in time. "
            "Try closing this and running it again — if it keeps happening, "
            "another program may already be using port 8020 or 5055."
        )
        return

    try:
        webview.create_window(
            "Triage Decision Support Tool",
            APP_URL,
            width=1150,
            height=820,
            min_size=(900, 650),
        )
        webview.start()
    finally:
        cleanup()


if __name__ == "__main__":
    main()
