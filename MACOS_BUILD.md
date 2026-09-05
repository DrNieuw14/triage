# Building & running TriageApp on macOS

This mirrors the Windows `TriageApp-Portable` build (see `README.md`), but
for macOS. It's built by `.github/workflows/build-macos.yml` on GitHub's own
macOS runners — real Mac hardware — since PyInstaller and the static PHP
builder both have to run on the OS they're targeting; none of this can be
cross-compiled from Windows.

## Triggering a build

Push to `main` with changes under `launcher/`, `ml/`, or `laravel-app/`, or
trigger it manually from the GitHub Actions tab ("Build macOS App" →
"Run workflow").

## Getting the app

Open the finished workflow run on GitHub → **Artifacts** section at the
bottom → download the zip matching the Mac it'll run on:

- **`TriageApp-macos-arm64.zip`** — Apple Silicon (M1/M2/M3/M4). This is
  what almost anyone with a Mac bought since late 2020 has.
- **`TriageApp-macos-x86_64.zip`** — Intel Macs.

Unzip it — you get a single `TriageApp.app`.

## First launch: the Gatekeeper step

This app isn't code-signed (that needs a paid Apple Developer account,
$99/year), so **the first time you open it**, macOS will refuse with
"Apple could not verify... is free of malware" rather than just opening.
This is expected, not a bug. Two ways past it, once per machine:

- **Right-click (or Control-click) `TriageApp.app` → Open → Open** in the
  dialog that appears. After this once, double-clicking it normally works
  from then on.
- Or, in Terminal: `xattr -cr /path/to/TriageApp.app` before opening it.

## macOS version floor: Monterey (12.0)

The build runs on GitHub's macOS 14/13 runners, but the app is meant to also
launch on older Macs — Monterey (12.0) specifically. A binary compiled with
no explicit target defaults to requiring whatever OS it was *built* on, which
would silently produce a zip that installs fine but refuses to open on an
older Mac ("this app is not compatible with this version of macOS").

Two things in the workflow guard against that:

- `MACOSX_DEPLOYMENT_TARGET: "12.0"` is set for the whole job, so anything
  actually compiled in CI (static PHP via `spc craft`, any C extension pip
  needs to build) targets 12.0 instead of the runner's own OS.
- A **"Verify bundled binaries can launch on macOS 12 (Monterey)"** step runs
  after assembly and before the zip is produced. It reads each bundled
  Mach-O's embedded minimum-OS version (via `otool -l`) — the static PHP
  binary, the two PyInstaller executables, and the bundled Python runtime —
  and **fails the build** if anything requires newer than 12.0, rather than
  shipping an artifact that looks done but won't open on the target machine.

If a build ever fails at that step, the log names exactly which binary is
too new — that's the one to chase (most likely candidate: the Python build
`actions/setup-python` installs, since that's the one binary in this
pipeline this workflow doesn't compile itself).

## What's actually verified vs. not

The workflow's last real step before packaging **starts both backends for
real and calls their health checks plus a live `/predict` call** — that
part is genuinely tested on real macOS hardware by CI every time it runs,
the same way the Windows build was verified end-to-end (see project notes).

What is **not** verified by me: the native window itself (`pywebview`
opening a real GUI window) isn't exercised in CI, since that would try to
open a display in a non-interactive runner and risks hanging the job rather
than proving anything. The backends-and-prediction smoke test is the
strongest check achievable without a physical Mac to click through the UI
on. If the window itself fails to open on a real machine, that's the first
thing to report back.

## Known limitations vs. the Windows build

- **Two architecture-specific builds**, not one universal binary — pick the
  right zip above.
- **Static PHP via `static-php-cli`** instead of a copied XAMPP PHP — this
  actually needs *less* fixing than Windows did (extensions are compiled
  directly into the binary, so there's no `extension_dir` path to get
  wrong), but it's a newer/less battle-tested tool than XAMPP's PHP, and
  its exact CLI/output-path conventions were confirmed from its docs, not
  from a local test run.
- Not notarized (see Gatekeeper note above) — this is normal for a
  thesis-demo build without a paid Apple Developer account, same tradeoff
  most small unsigned Mac apps make.
