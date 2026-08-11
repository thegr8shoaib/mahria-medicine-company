@echo off
cd /d "%~dp0backend"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP not found. Run SETUP.bat first.
  pause
  exit /b 1
)
if not exist "database\database.sqlite" (
  echo Database missing. Run SETUP.bat first.
  pause
  exit /b 1
)

set BUSY=0
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000 " ^| findstr LISTENING') do set BUSY=1
if not "%BUSY%"=="1" (
  echo Starting Mehria server...
  start "Mehria Server" php artisan serve --host=127.0.0.1 --port=8000
  timeout /t 4 /nobreak >nul
)

start http://127.0.0.1:8000
echo App opened in browser. Close this window or press any key to exit.
pause >nul