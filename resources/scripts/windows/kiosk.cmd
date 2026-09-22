@echo off
setlocal EnableExtensions

set "KIOSK_URL=http://localhost:8000"
set "CHROME_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe"
set "PROFILE_DIR=C:\pos-kiosk-profile"
set /a tries=0

if not exist "%CHROME_PATH%" (
    rem Fallback a la ruta de Chrome por usuario si la instalacion no es global.
    set "CHROME_PATH=%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
    if not exist "%CHROME_PATH%" (
        echo [kiosk] Chrome no encontrado. Verifica la instalacion.
        exit /b 1
    )
)

echo [kiosk] Esperando POS en %KIOSK_URL%

:wait
powershell -NoProfile -Command "try{$r=Invoke-WebRequest -UseBasicParsing -Uri '%KIOSK_URL%' -TimeoutSec 2; exit 0}catch{exit 1}"
if %errorlevel%==0 goto up
set /a tries+=1
if %tries% GEQ 60 goto up
timeout /t 2 /nobreak >nul
goto wait

:up
echo [kiosk] POS listo. Abriendo Chrome...
start "" "%CHROME_PATH%" ^
    --kiosk --app=%KIOSK_URL% ^
    --disable-pinch ^
    --touch-events=enabled ^
    --overscroll-history-navigation=0 ^
    --noerrdialogs ^
    --no-first-run ^
    --disable-infobars ^
    --force-device-scale-factor=1 ^
    --user-data-dir=%PROFILE_DIR%

endlocal