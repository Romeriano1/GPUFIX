@echo off
setlocal
set "PHP_GPUFIX=%~dp0..\..\..\php\php.exe"
if exist "%PHP_GPUFIX%" goto activar
set "PHP_GPUFIX=php"
:activar
"%PHP_GPUFIX%" "%~dp0sincronizar.php" --instalar
set "RESULTADO_GPUFIX=%ERRORLEVEL%"
echo.
pause
exit /b %RESULTADO_GPUFIX%
