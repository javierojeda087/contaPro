@echo off
:: Auto-elevar a Administrador si no tiene permisos
net session >nul 2>&1
if %errorLevel% neq 0 (
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

echo Configurando contapro.com.py (177.104.129.168) en Windows...
findstr /I "contapro.com.py" "%SystemRoot%\System32\drivers\etc\hosts" >nul
if %errorLevel% neq 0 (
    echo.>> "%SystemRoot%\System32\drivers\etc\hosts"
    echo 177.104.129.168 contapro.com.py www.contapro.com.py>> "%SystemRoot%\System32\drivers\etc\hosts"
)
ipconfig /flushdns >nul
echo Listo! Abriendo www.contapro.com.py en tu navegador...
start http://www.contapro.com.py
timeout /t 3 >nul
