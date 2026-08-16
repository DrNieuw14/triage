@echo off
title Triage Decision Support Launcher
color 0A

cd /d "%~dp0"

echo ==========================================
echo   Starting Triage Decision Support Tool...
echo ==========================================
echo.

echo Starting Python Model API...
start "Triage Model API" cmd /k "cd ml && venv\Scripts\python.exe api.py"

timeout /t 3 >nul

echo Starting Laravel Server...
start "Laravel Server" cmd /k "cd laravel-app && php artisan serve --port=8020"

timeout /t 3 >nul

echo Opening Browser...
start http://127.0.0.1:8020/triage

echo.
echo ==========================================
echo Triage Decision Support Tool Started!
echo ==========================================
echo.
pause
