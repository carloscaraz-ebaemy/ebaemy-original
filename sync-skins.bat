@echo off
echo ======================================
echo    SINCRONIZACION DE SKINS CSS
echo ======================================
echo.

echo [INFO] Copiando archivos CSS de skins...
copy "storage\app\public\skins\*.css" "public\storage\skins\" >nul

if %errorlevel% == 0 (
    echo [EXITO] Skins CSS sincronizados correctamente!
    echo.
    echo Archivos actualizados:
    dir "public\storage\skins\*.css" /b
) else (
    echo [ERROR] No se pudieron sincronizar los archivos
)

echo.
echo ======================================
echo Presiona cualquier tecla para cerrar...
pause >nul