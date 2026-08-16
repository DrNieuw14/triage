# -*- mode: python ; coding: utf-8 -*-
#
# macOS build of the launcher — --onedir + BUNDLE() so this produces a real
# TriageApp.app the user double-clicks in Finder, mirroring what
# TriageApp.exe is on Windows. The runtime/php, laravel-app, and api
# folders are copied into TriageApp.app/Contents/Resources/ by the build
# workflow *after* this spec builds the .app shell — launcher.py's
# base_dir() already knows to look there (Contents/Resources) instead of
# next to the executable itself, since sys.executable inside a .app bundle
# resolves to Contents/MacOS/TriageApp, not the bundle's top level.

a = Analysis(
    ['launcher.py'],
    pathex=[],
    binaries=[],
    datas=[],
    hiddenimports=[],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    [],
    exclude_binaries=True,
    name='TriageApp',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)

coll = COLLECT(
    exe,
    a.binaries,
    a.datas,
    strip=False,
    upx=True,
    upx_exclude=[],
    name='TriageApp',
)

app = BUNDLE(
    coll,
    name='TriageApp.app',
    icon=None,
    bundle_identifier='org.cvsu.triage-decision-support',
    info_plist={
        'CFBundleName': 'Triage Decision Support Tool',
        'CFBundleDisplayName': 'Triage Decision Support Tool',
        'CFBundleShortVersionString': '1.0.0',
        'NSHighResolutionCapable': True,
        # Not sandboxed / not code-signed — this is a thesis-demo build,
        # not an App Store submission, so no entitlements file is needed
        # for its plain http://127.0.0.1 calls to the two local backends.
    },
)
