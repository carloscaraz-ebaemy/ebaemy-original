# CSS Skins Auto Sync Script
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "    SINCRONIZACION DE SKINS CSS" -ForegroundColor Cyan  
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

try {
    Write-Host "[INFO] Copiando archivos CSS de skins..." -ForegroundColor Yellow
    
    # Crear directorio de destino si no existe
    if (!(Test-Path "public\storage\skins")) {
        New-Item -ItemType Directory -Path "public\storage\skins" -Force | Out-Null
    }
    
    # Copiar archivos
    Copy-Item "storage\app\public\skins\*.css" "public\storage\skins\" -Force
    
    Write-Host "[EXITO] Skins CSS sincronizados correctamente!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Archivos actualizados:" -ForegroundColor Green
    Get-ChildItem "public\storage\skins\*.css" | ForEach-Object { 
        Write-Host "  - $($_.Name)" -ForegroundColor White
    }
    
} catch {
    Write-Host "[ERROR] No se pudieron sincronizar los archivos" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Read-Host "Presiona Enter para cerrar"