/* Uso (PowerShell):
   node .\fix-extensions.js --dry
   node .\fix-extensions.js
*/
const fs = require('fs');
const path = require('path');

const DRY_RUN = process.argv.includes('--dry');
const CWD = process.cwd();

// Directorios a escanear
const ROOTS = [
  path.resolve(CWD, 'resources', 'js'),
  path.resolve(CWD, 'modules'),
];

const IGNORE_DIRS = new Set([
  'node_modules', 'vendor', 'public', 'storage', 'dist', 'build', '.git', '.idea', '.vscode'
]);

function walk(dir, files = []) {
  if (!fs.existsSync(dir)) return files;
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const e of entries) {
    if (IGNORE_DIRS.has(e.name)) continue;
    const full = path.join(dir, e.name);
    if (e.isDirectory()) {
      walk(full, files);
    } else if (e.isFile() && full.toLowerCase().endsWith('.vue')) {
      files.push(full);
    }
  }
  return files;
}

function hasExt(p) {
  return path.extname(p) !== '';
}
function isRelative(p) {
  return p.startsWith('./') || p.startsWith('../');
}
function normalizeName(s) {
  return (s || '').replace(/[^A-Za-z0-9]/g, '').toLowerCase();
}

function scanModulesDir() {
  const modulesDir = path.resolve(CWD, 'modules');
  if (!fs.existsSync(modulesDir)) return [];
  return fs.readdirSync(modulesDir, { withFileTypes: true })
    .filter(d => d.isDirectory())
    .map(d => d.name);
}
const MODULE_DIRS = scanModulesDir();

// Alias base
const aliasMap = {
  '@': path.resolve(CWD, 'resources', 'js'),
};

// Cargar alias desde scripts/aliases.json (opcional)
function loadAliasFile() {
  const file = path.resolve(CWD, 'scripts', 'aliases.json');
  if (!fs.existsSync(file)) return;
  try {
    const json = JSON.parse(fs.readFileSync(file, 'utf8'));
    for (const [k, v] of Object.entries(json)) {
      aliasMap[k] = path.resolve(CWD, v);
    }
  } catch (e) {
    console.warn('No se pudo leer scripts/aliases.json:', e.message);
  }
}

// Cargar alias desde jsconfig/tsconfig (opcional)
function loadTsJsConfig() {
  for (const cfgName of ['jsconfig.json', 'tsconfig.json']) {
    const file = path.resolve(CWD, cfgName);
    if (!fs.existsSync(file)) continue;
    try {
      const json = JSON.parse(fs.readFileSync(file, 'utf8'));
      const baseUrl = json?.compilerOptions?.baseUrl
        ? path.resolve(CWD, json.compilerOptions.baseUrl)
        : CWD;
      const paths = json?.compilerOptions?.paths || {};
      for (const [key, arr] of Object.entries(paths)) {
        if (!Array.isArray(arr) || arr.length === 0) continue;
        const aliasKey = key.replace(/\/\*$/, '');
        let target = arr[0].replace(/\/\*$/, '');
        if (!path.isAbsolute(target)) target = path.resolve(baseUrl, target);
        aliasMap[aliasKey] = target;
      }
    } catch (e) {
      console.warn(`No se pudo leer ${cfgName}:`, e.message);
    }
  }
}

// Cargar alias desde vite.config.js
function loadViteAliases() {
  const viteFile = path.resolve(CWD, 'vite.config.js');
  if (!fs.existsSync(viteFile)) return;
  try {
    const src = fs.readFileSync(viteFile, 'utf8');
    const re = /['"](@[^'"]+)['"]\s*:\s*path\.resolve\(\s*__dirname\s*,\s*['"]([^'"]+)['"]\s*\)/g;
    let m;
    while ((m = re.exec(src)) !== null) {
      const key = m[1];
      const rel = m[2];
      aliasMap[key] = path.resolve(CWD, rel);
    }
  } catch (e) {
    console.warn('No se pudo leer vite.config.js:', e.message);
  }
}

loadAliasFile();
loadTsJsConfig();
loadViteAliases();

// Heurística para @viewsModuleX -> modules/<XMatch>/Resources/assets/js/views
function findModuleDirByPrefix(prefix) {
  const wanted = normalizeName(prefix);
  return MODULE_DIRS.find(dir => normalizeName(dir).startsWith(wanted));
}

function resolveRelativeVueFile(basedir, importPath) {
  const directVue = path.resolve(basedir, importPath + '.vue');
  if (fs.existsSync(directVue)) return importPath + '.vue';
  const indexVue = path.resolve(basedir, importPath, 'index.vue');
  if (fs.existsSync(indexVue)) return importPath + '/index.vue';
  return null;
}

function resolveAliasVueFile(importPath) {
  const firstSlash = importPath.indexOf('/');
  const aliasName = firstSlash === -1 ? importPath : importPath.slice(0, firstSlash);
  const rest = firstSlash === -1 ? '' : importPath.slice(firstSlash + 1);

  let baseDir = aliasMap[aliasName];

  if (!baseDir && aliasName.startsWith('@viewsModule')) {
    const key = aliasName.slice('@viewsModule'.length);
    const modDir = findModuleDirByPrefix(key);
    if (modDir) {
      baseDir = path.resolve(CWD, 'modules', modDir, 'Resources', 'assets', 'js', 'views');
      aliasMap[aliasName] = baseDir;
    }
  }

  if (!baseDir) return null;

  const candidates = [];
  if (rest) {
    candidates.push(path.resolve(baseDir, rest + '.vue'));
    candidates.push(path.resolve(baseDir, rest, 'index.vue'));
  } else {
    candidates.push(path.resolve(baseDir, 'index.vue'));
  }

  for (const abs of candidates) {
    if (fs.existsSync(abs)) {
      const suffix = abs.endsWith(path.sep + 'index.vue')
        ? (rest ? rest + '/index.vue' : 'index.vue')
        : (rest ? rest + '.vue' : 'index.vue');
      return aliasName + '/' + suffix.replace(/^[\/]+/, '');
    }
  }
  return null;
}

function fixContent(filePath, content) {
  const dir = path.dirname(filePath);
  let changed = false;

  const fixPath = (p) => {
    if (hasExt(p)) return p;

    if (isRelative(p)) {
      const fixed = resolveRelativeVueFile(dir, p);
      if (fixed) { changed = true; return fixed; }
      return p;
    }

    if (p.startsWith('@')) {
      const fixed = resolveAliasVueFile(p);
      if (fixed) { changed = true; return fixed; }
      return p;
    }

    return p;
  };

  content = content.replace(
    /(import\s+[^;]*?\s+from\s+['"])([^'"]+)(['"]\s*;?)/g,
    (m, p1, p2, p3) => p1 + fixPath(p2) + p3
  );
  content = content.replace(
    /(require\(\s*['"])([^'"]+)(['"]\s*\))/g,
    (m, p1, p2, p3) => p1 + fixPath(p2) + p3
  );
  content = content.replace(
    /(import\(\s*['"])([^'"]+)(['"]\s*\))/g,
    (m, p1, p2, p3) => p1 + fixPath(p2) + p3
  );

  return { content, changed };
}

function run() {
  const files = ROOTS.flatMap((r) => walk(r));
  let totalChanged = 0;

  for (const f of files) {
    const raw = fs.readFileSync(f, 'utf8');
    const { content, changed } = fixContent(f, raw);
    if (changed) {
      totalChanged++;
      if (!DRY_RUN) fs.writeFileSync(f, content, 'utf8');
      console.log(`${DRY_RUN ? '[dry]' : '[fix]'} ${path.relative(CWD, f)}`);
    }
  }

  console.log(`Done. Files changed: ${totalChanged}/${files.length}`);
}

run();