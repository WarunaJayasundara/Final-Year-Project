@echo off
rem HelaIQ local development launcher (Windows + XAMPP).
rem Double-click it, or run it from a terminal. Opens one window per service.
rem Close a window to stop that service.

cd /d "%~dp0"

tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if errorlevel 1 (
  echo Starting MySQL...
  start "MySQL" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --console
  timeout /t 6 /nobreak >nul
) else (
  echo MySQL is already running.
)

start "HelaIQ backend :8000" cmd /k "cd /d %~dp0backend && php artisan serve"
start "HelaIQ frontend :5173" cmd /k "cd /d %~dp0frontend && npm run dev"
start "HelaIQ ML service :8100" cmd /k "cd /d %~dp0ml-service && venv\Scripts\python.exe -m uvicorn app:app --host 127.0.0.1 --port 8100"

echo.
echo Started. Open http://localhost:5173 in your browser.
