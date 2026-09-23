@echo off
setlocal

rem ============================================================
rem  Kolekta - one-click launcher (Windows + XAMPP)
rem
rem  1) Starts MySQL and Apache if they are not already running
rem  2) Installs the 'kolekta' database the first time
rem  3) Opens the app in your browser
rem
rem  Run this AFTER installing XAMPP at C:\xampp.
rem ============================================================

cd /d "%~dp0"

rem ---- locate XAMPP ------------------------------------------------
set "XAMPP_ROOT="
if exist "C:\xampp\apache\bin\httpd.exe" set "XAMPP_ROOT=C:\xampp"
if "%XAMPP_ROOT%"=="" if exist "%ProgramFiles%\xampp\apache\bin\httpd.exe" set "XAMPP_ROOT=%ProgramFiles%\xampp"
if "%XAMPP_ROOT%"=="" if exist "%ProgramFiles(x86)%\xampp\apache\bin\httpd.exe" set "XAMPP_ROOT=%ProgramFiles(x86)%\xampp"

if "%XAMPP_ROOT%"=="" (
  echo.
  echo  XAMPP was not found.
  echo  Install it first, then run this file again.
  echo.
  pause
  exit /b 1
)
echo  Using XAMPP at %XAMPP_ROOT%

rem ---- start MySQL if needed --------------------------------------
call :IsRunning mysqld.exe
if errorlevel 1 (
  echo  Starting MySQL ...
  start "Kolekta MySQL" "%XAMPP_ROOT%\mysql\bin\mysqld.exe"
) else (
  echo  MySQL already running.
)

rem ---- start Apache if needed -------------------------------------
call :IsRunning httpd.exe
if errorlevel 1 (
  echo  Starting Apache ...
  start "Kolekta Apache" "%XAMPP_ROOT%\apache\bin\httpd.exe"
) else (
  echo  Apache already running.
)

rem ---- locate PHP -------------------------------------------------
set "PHP_BIN="
if exist "%XAMPP_ROOT%\php\php.exe" set "PHP_BIN=%XAMPP_ROOT%\php\php.exe"
if "%PHP_BIN%"=="" (
  where php >nul 2>nul
  if not errorlevel 1 set "PHP_BIN=php"
)
if "%PHP_BIN%"=="" (
  echo.
  echo  Could not find PHP. Reinstall XAMPP and try again.
  echo.
  pause
  exit /b 1
)

rem ---- install / verify the database (retry while MySQL boots) ----
echo  Checking database ...
set /a tries=0
:InstallTry
  set /a tries+=1
  "%PHP_BIN%" setup-db.php
  if not errorlevel 1 goto Installed
  if %tries% geq 4 goto InstallFailed
  echo  Database not ready yet, retrying ...
  timeout /t 3 /nobreak >nul
  goto InstallTry

:InstallFailed
echo.
echo  Database setup failed after several tries.
echo  Confirm MySQL is running, then run:  php setup-db.php
echo.
pause
exit /b 1

:Installed
echo.
echo  Waiting for the web server ...
set /a wp=0
:WaitWebReady
  powershell -noprofile -command "try{$c=New-Object Net.Sockets.TcpClient;$c.Connect('127.0.0.1',80);$c.Close();exit 0}catch{exit 1}" >nul 2>&1
  if not errorlevel 1 goto WebReady
  set /a wp+=1
  if %wp% geq 10 (
    echo  Apache did not respond on port 80. Check the XAMPP control panel.
    pause
    exit /b 1
  )
  timeout /t 1 /nobreak >nul
  goto WaitWebReady

:WebReady
echo  Opening http://localhost/kolekta/ in your browser ...
start "" "http://localhost/kolekta/"
echo.
echo  Ready.
echo  Admin:    jayvie / kolekta-admin-2026
echo  Admin:    spencer / kolekta-admin-2026
echo  Admin:    pau / kolekta-admin-2026
echo  Residents:daniel maria jose ana pedro liza / same password
echo.
echo  Re-seed a fresh copy of the data:  php setup-db.php --force
echo.
pause

rem ---- end of main flow -------------------------------------------
exit /b 0

rem ============================================================
rem  SUBROUTINES  (keep below everything)
rem ============================================================

:IsRunning
  tasklist /fi "imagename eq %1" 2>nul | findstr /i "%1" >nul
  exit /b %errorlevel%