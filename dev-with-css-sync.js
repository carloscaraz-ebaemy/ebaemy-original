const { spawn } = require('child_process');
const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const isWatchMode = args.includes('--watch');
const modeLabel = isWatchMode ? 'build en modo watch' : 'desarrollo';

console.log(`🚀 Iniciando ${modeLabel} con sincronización automática de CSS...\n`);

// Función para sincronizar archivos
function syncFile(filePath) {
    const fileName = path.basename(filePath);
    const sourcePath = filePath;
    const destinationPath = path.join('public/storage/skins', fileName);
    
    try {
        // Crear directorio si no existe
        const destDir = path.dirname(destinationPath);
        if (!fs.existsSync(destDir)) {
            fs.mkdirSync(destDir, { recursive: true });
        }
        
        fs.copyFileSync(sourcePath, destinationPath);
        const timestamp = new Date().toLocaleTimeString();
        console.log(`✅ [${timestamp}] CSS Skin: ${fileName} sincronizado`);
    } catch (error) {
        console.log(`❌ Error sincronizando ${fileName}:`, error.message);
    }
}

// Sincronizar archivos existentes al inicio
console.log('🔄 Sincronización inicial de CSS Skins...');
const glob = require('glob');
const existingFiles = glob.sync('storage/app/public/skins/*.css');
if (existingFiles.length > 0) {
    existingFiles.forEach(syncFile);
    console.log('🎉 Sincronización inicial completa\n');
} else {
    console.log('ℹ️  No se encontraron archivos CSS de skins\n');
}

// Configurar el watcher para cambios en tiempo real
const watcher = chokidar.watch('storage/app/public/skins/*.css', {
    persistent: true,
    ignoreInitial: true
});

watcher
    .on('change', (filePath) => {
        console.log(`📝 Cambio detectado en: ${path.basename(filePath)}`);
        syncFile(filePath);
    })
    .on('add', (filePath) => {
        console.log(`➕ Nuevo archivo CSS detectado: ${path.basename(filePath)}`);
        syncFile(filePath);
    })
    .on('error', (error) => {
        console.log('❌ Error en watcher CSS:', error);
    });

console.log('👀 Watcher CSS activo - Monitoreando cambios en tiempo real...');

// Iniciar Vite
const viteArgs = isWatchMode ? ['vite', 'build', '--watch'] : ['vite'];
console.log(`🚀 Iniciando Vite (${isWatchMode ? 'build --watch' : 'dev server'})...\n`);
const viteProcess = spawn('npx', viteArgs, {
    stdio: 'inherit',
    shell: true
});

// Manejar cierre del proceso
process.on('SIGINT', () => {
    console.log('\n🛑 Cerrando watcher CSS y Vite...');
    watcher.close();
    viteProcess.kill('SIGINT');
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('\n🛑 Cerrando watcher CSS y Vite...');
    watcher.close();
    viteProcess.kill('SIGTERM');
    process.exit(0);
});