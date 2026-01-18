# 🎨 CSS Skins - Guía de Sincronización Automática

## 🚨 Problema Identificado
Los archivos CSS de skins en Laravel no se actualizan automáticamente porque:
- **Editas**: `storage/app/public/skins/default.css`
- **El navegador carga**: `public/storage/skins/default.css`

**Problema adicional de especificidad CSS:**
- Bootstrap se carga desde `resources/js/app.js` y puede sobrescribir los estilos de skins
- Los estilos de Bootstrap aparecen como `<style>` en el navegador (compilados por Vite)
- Para solucionarlo se ha reorganizado el orden de imports y agregado especificidad alta a las skins

## ⚡ Soluciones Disponibles

### 1. 🚀 Sincronización Rápida (Recomendada)
```bash
npm run sync-skins-once
```

### 2. 🔄 Watcher Automático (Para desarrollo)
```bash
npm run sync-skins
```
- Se ejecuta en segundo plano
- Detecta cambios automáticamente
- Sincroniza al instante
- Usa Ctrl+C para detener

### 3. 📂 Scripts de Windows

#### Opción A: Archivo .BAT
```bash
# Doble clic en:
sync-skins.bat
```

#### Opción B: PowerShell
```powershell
# Clic derecho > Ejecutar con PowerShell:
sync-skins.ps1
```

### 4. 🛠️ Desde VS Code (Tasks)
- **Ctrl + Shift + P**
- Escribir: "Tasks: Run Task"
- Elegir:
  - `Sync CSS Skins - Once` (una vez)
  - `Sync CSS Skins - Auto Watch` (automático)
  - `Sync CSS Skins - Manual (BAT)` (manual)

## 💡 Flujo de Trabajo Recomendado

### 🚀 Para Desarrollo con Sincronización Automática (Recomendado):
```bash
npm run dev
```
- **Sincronización inicial**: Se sincronizan todos los CSS al inicio
- **Watcher activo**: Detecta cambios automáticamente en `storage/app/public/skins/*.css`
- **Auto-sincronización**: Los cambios se copian instantáneamente a `public/storage/skins/`
- **Vite integrado**: El servidor de desarrollo funciona normalmente
- **Un solo comando**: Todo funciona con `npm run dev`

### 🎯 Para Desarrollo Simple (Sin watcher):
```bash
npm run dev-simple
```
- Solo inicia Vite sin sincronización automática
- Requiere `npm run sync-skins-once` manual para sincronizar

### Para Cambios Ocasionales:
1. Edita `storage/app/public/skins/default.css`
2. Ejecuta: `npm run sync-skins-once`
3. Refrescar navegador (F5)

### Para Desarrollo Continuo (Solo CSS):
1. Ejecuta: `npm run sync-skins`
2. Edita `storage/app/public/skins/default.css`
3. Los cambios se sincronizan automáticamente
4. Refrescar navegador (F5)

## 🔍 Verificar Sincronización

### Comprobar fechas de archivos:
```bash
# Original (el que editas)
stat storage/app/public/skins/default.css

# Público (el que carga el navegador)  
stat public/storage/skins/default.css
```

### Si las fechas no coinciden, ejecutar:
```bash
npm run sync-skins-once
```

## 🚨 Solución de Problemas

### ❌ Los cambios no se ven en el navegador:
1. **Hard Refresh**: Ctrl + F5
2. **DevTools**: F12 → Network → "Empty cache and hard reload"
3. **Incógnito**: Abrir en ventana privada

### ❌ Error en scripts:
- Verificar que estás en la carpeta del proyecto
- Comprobar que Node.js está instalado: `node --version`

### ❌ Archivo no encontrado:
```bash
# Crear directorios si no existen:
mkdir -p public/storage/skins
```

## 🎯 Consejos Pro

1. **Especificidad CSS Mejorada**: 
   - Se han agregado selectores con alta especificidad como `body.skin-default .table`
   - Las variables CSS de Bootstrap ahora se sobrescriben con `!important`
   - La clase `skin-default` se aplica automáticamente al `<body>`

2. **Orden de Carga Optimizado**: 
   - Bootstrap se carga ANTES que Element UI en `resources/js/app.js`
   - Los skins se cargan después, dándoles prioridad

3. **Versioning CSS**: Agrega `?v=timestamp` en desarrollo
4. **Usar Chrome DevTools**: Network tab para verificar carga de CSS
5. **Backup**: Respalda tus skins antes de cambios mayores
6. **Git**: Trackea cambios en ambos directorios

## 📝 Archivos Importantes

- `storage/app/public/skins/default.css` → Archivo que editas
- `public/storage/skins/default.css` → Archivo que sirve el navegador
- `dev-with-css-sync.js` → Script integrado para desarrollo con watcher automático
- `css-watcher.js` → Watcher independiente para solo CSS
- `sync-skins.bat` → Script manual Windows
- `.vscode/tasks.json` → Tasks de VS Code