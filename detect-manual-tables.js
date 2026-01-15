/**
 * Script para detectar tablas MANUALES que necesitan empty state
 * Ejecutar: node detect-manual-tables.js
 */

const fs = require('fs');
const path = require('path');

const SEARCH_PATHS = [
    path.join(__dirname, 'resources/js/views'),
    path.join(__dirname, 'modules')
];

function findVueFiles(dir, fileList = []) {
    if (!fs.existsSync(dir)) return fileList;

    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);

        if (stat.isDirectory()) {
            if (file !== 'node_modules' && file !== 'vendor') {
                findVueFiles(filePath, fileList);
            }
        } else if (file.endsWith('.vue')) {
            fileList.push(filePath);
        }
    });

    return fileList;
}

function analyzeFile(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        
        // Detectar si usa DataTable (ya tiene empty state)
        const usesDataTable = /<data-table/i.test(content) || 
                             /import.*DataTable.*from.*@components\/DataTable/i.test(content);
        
        // Detectar si tiene tabla HTML manual
        const hasManualTable = /<table/i.test(content) && 
                              /<tbody/i.test(content);
        
        // Detectar si tiene el patrón v-for en tbody
        const hasVForInTable = /<tr\s+v-for/i.test(content);
        
        // Detectar si YA tiene empty-state
        const hasEmptyState = /<empty-state/i.test(content) ||
                             /empty-state/i.test(content);
        
        // Detectar si usa el-table de Element UI
        const usesElTable = /<el-table/i.test(content);
        
        return {
            usesDataTable,
            hasManualTable,
            hasVForInTable,
            hasEmptyState,
            usesElTable,
            needsEmptyState: (hasManualTable || usesElTable) && !usesDataTable && !hasEmptyState
        };
    } catch (error) {
        return null;
    }
}

function main() {
    console.log('🔍 Analizando archivos Vue...\n');
    console.log('Buscando tablas que NECESITAN empty state\n');
    console.log('='.repeat(80) + '\n');

    let stats = {
        total: 0,
        withDataTable: 0,
        withManualTable: 0,
        withElTable: 0,
        alreadyHasEmptyState: 0,
        needsEmptyState: 0
    };

    let filesNeedingFix = [];

    SEARCH_PATHS.forEach(searchPath => {
        if (!fs.existsSync(searchPath)) {
            console.log(`⚠️  Ruta no encontrada: ${searchPath}`);
            return;
        }

        const vueFiles = findVueFiles(searchPath);
        
        vueFiles.forEach(file => {
            stats.total++;
            const analysis = analyzeFile(file);
            
            if (!analysis) return;
            
            if (analysis.usesDataTable) {
                stats.withDataTable++;
            }
            
            if (analysis.hasManualTable) {
                stats.withManualTable++;
            }
            
            if (analysis.usesElTable) {
                stats.withElTable++;
            }
            
            if (analysis.hasEmptyState) {
                stats.alreadyHasEmptyState++;
            }
            
            if (analysis.needsEmptyState) {
                stats.needsEmptyState++;
                const relativePath = path.relative(__dirname, file);
                filesNeedingFix.push({
                    path: relativePath,
                    type: analysis.usesElTable ? 'el-table' : 'manual-table',
                    hasVFor: analysis.hasVForInTable
                });
            }
        });
    });

    // Mostrar estadísticas
    console.log('📊 ESTADÍSTICAS GLOBALES:');
    console.log('─'.repeat(80));
    console.log(`   Total de archivos .vue analizados: ${stats.total}`);
    console.log(`   ✅ Archivos con DataTable (YA tienen empty state): ${stats.withDataTable}`);
    console.log(`   ✅ Archivos que YA tienen empty-state implementado: ${stats.alreadyHasEmptyState}`);
    console.log(`   📋 Archivos con tablas HTML manuales: ${stats.withManualTable}`);
    console.log(`   📋 Archivos con <el-table>: ${stats.withElTable}`);
    console.log(`   ⚠️  ARCHIVOS QUE NECESITAN EMPTY STATE: ${stats.needsEmptyState}\n`);
    console.log('='.repeat(80) + '\n');

    if (filesNeedingFix.length === 0) {
        console.log('🎉 ¡PERFECTO! Todos los archivos ya tienen empty state o usan DataTable.\n');
        return;
    }

    // Agrupar por módulo y tipo
    const byModule = {};
    filesNeedingFix.forEach(file => {
        const parts = file.path.split(path.sep);
        const module = parts.includes('modules') 
            ? parts[parts.indexOf('modules') + 1] 
            : 'Core';
        
        if (!byModule[module]) {
            byModule[module] = {
                'manual-table': [],
                'el-table': []
            };
        }
        byModule[module][file.type].push(file);
    });

    console.log('⚠️  ARCHIVOS QUE NECESITAN SER CORREGIDOS:\n');
    
    Object.keys(byModule).sort().forEach(module => {
        const manualCount = byModule[module]['manual-table'].length;
        const elTableCount = byModule[module]['el-table'].length;
        const total = manualCount + elTableCount;
        
        if (total === 0) return;
        
        console.log(`\n📦 ${module} (${total} archivos):`);
        console.log('─'.repeat(80));
        
        // Tablas manuales HTML
        if (manualCount > 0) {
            console.log(`\n   📋 Tablas HTML manuales (${manualCount}):`);
            byModule[module]['manual-table'].forEach(file => {
                console.log(`   ❌ ${file.path}`);
                console.log(`      → Agregar: <tr v-if="records.length === 0"><td colspan="X"><empty-state /></td></tr>`);
            });
        }
        
        // Element UI tables
        if (elTableCount > 0) {
            console.log(`\n   📋 Element UI <el-table> (${elTableCount}):`);
            byModule[module]['el-table'].forEach(file => {
                console.log(`   ❌ ${file.path}`);
                console.log(`      → Agregar: <empty-state v-if="data.length === 0" /> después del <el-table>`);
            });
        }
    });

    console.log('\n' + '='.repeat(80));
    console.log('\n💡 PASOS PARA CORREGIR:\n');
    console.log('1. Para tablas HTML manuales:');
    console.log('   Agregar dentro del <tbody>:');
    console.log('   <tr v-if="records.length === 0">');
    console.log('       <td colspan="NUMERO_DE_COLUMNAS" style="border: none; padding: 0;">');
    console.log('           <empty-state message="Tu-mensaje-aquí" />');
    console.log('       </td>');
    console.log('   </tr>\n');
    
    console.log('2. Para <el-table>:');
    console.log('   Agregar después del <el-table>:');
    console.log('   <empty-state v-if="tableData.length === 0" />\n');
    
    console.log('3. Ocultar paginación cuando no hay datos:');
    console.log('   <div v-if="records.length > 0">');
    console.log('       <el-pagination ... />');
    console.log('   </div>\n');
    
    console.log('='.repeat(80));
    console.log(`\n📝 Total de archivos a corregir: ${stats.needsEmptyState}`);
    console.log('🔧 Usa el archivo item-lots/index.vue como referencia\n');
}

main();