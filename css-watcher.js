const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

console.log('🔄 CSS Skins Watcher iniciado...');
console.log('👀 Monitoreando cambios en: storage/app/public/skins/*.css');

// Configurar el watcher
const watcher = chokidar.watch('storage/app/public/skins/*.css', {
    persistent: true,
    ignoreInitial: true
});

// Función para sincronizar archivos
function syncFile(filePath) {
    const fileName = path.basename(filePath);
    const sourcePath = filePath;
    const destinationPath = path.join('public/storage/skins', fileName);
    
    try {
        fs.copyFileSync(sourcePath, destinationPath);
        const timestamp = new Date().toLocaleTimeString();
        console.log(`✅ [${timestamp}] ${fileName} sincronizado correctamente`);
    } catch (error) {
        console.log(`❌ Error sincronizando ${fileName}:`, error.message);
    }
}

// Eventos del watcher
watcher
    .on('change', (filePath) => {
        console.log(`📝 Cambio detectado: ${path.basename(filePath)}`);
        syncFile(filePath);
    })
    .on('error', (error) => {
        console.log('❌ Error en watcher:', error);
    });

console.log('');
console.log('💡 Instrucciones:');
console.log('   - Edita cualquier archivo .css en storage/app/public/skins/');
console.log('   - Los cambios se sincronizarán automáticamente');
console.log('   - Presiona Ctrl+C para detener el watcher');
console.log('');

// Sincronizar todos los archivos al inicio
const glob = require('glob');
glob.sync('storage/app/public/skins/*.css').forEach(syncFile);

// Mantener el proceso activo
process.on('SIGINT', () => {
    console.log('\n👋 CSS Skins Watcher detenido');
    process.exit(0);
});