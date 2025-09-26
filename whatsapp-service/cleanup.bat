@echo off
echo === LIMPIEZA DE PROCESOS WHATSAPP ===
echo.

echo Buscando procesos node.exe...
tasklist | find "node.exe" >nul
if %errorlevel% == 0 (
    echo Deteniendo procesos node.exe...
    taskkill /F /IM node.exe
    echo ✓ Procesos detenidos
) else (
    echo No hay procesos node.exe activos
)

echo.
echo Limpiando carpetas de sesion...
if exist .wwebjs_auth rmdir /s /q .wwebjs_auth
if exist tokens rmdir /s /q tokens
if exist "session-empresa-*" rmdir /s /q session-empresa-*

echo ✓ Limpieza completada
echo.
timeout /t 3 >nul