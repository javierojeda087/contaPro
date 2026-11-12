@echo off
setlocal
:: Verificador previo a subir cambios: sintaxis PHP + rutas/metodos.
:: Uso:  verificar.bat           -> verificacion rapida
::       verificar.bat --smoke   -> ademas prueba las paginas con el servidor local (requiere MySQL de Laragon encendido)

set "PHPBIN="
where php >nul 2>&1 && set "PHPBIN=php"
if not defined PHPBIN (
    for /d %%D in ("C:\laragon\bin\php\php-*") do set "PHPBIN=%%D\php.exe"
)
if not defined PHPBIN (
    echo No se encontro PHP. Instale PHP o Laragon, o agregue php.exe al PATH.
    pause
    exit /b 1
)

"%PHPBIN%" "%~dp0tools\verificar.php" %*
set "RC=%ERRORLEVEL%"
echo.
pause
exit /b %RC%
