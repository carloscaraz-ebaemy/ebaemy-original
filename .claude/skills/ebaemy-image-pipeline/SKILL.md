---
name: ebaemy-image-pipeline
description: Pipeline de procesamiento de imágenes en EBAEMY (HEIC + EXIF + queue async + UI progreso). Invocar cuando el usuario pida tocar uploads de productos/variantes, agregar tipos de archivo, integrar remove-bg, cambiar tamaños generados, manejar errores de imágenes, o cuando se reporte "no se puede subir foto del iPhone", "subida muy lenta", "imagen sale rotada".
---

# Pipeline de imágenes — EBAEMY

## Estado del pipeline (fases)

| Fase | Status | Qué resuelve |
|---|---|---|
| **F1** | ✅ Listo | HEIC client (heic2any) + Imagick backend + corrección EXIF orientation |
| **F2** | ✅ Backend listo, frontend POC en galería | Queue + polling + UI progreso ("Subiendo / Procesando / Listo") |
| F3 | Pendiente | Tabla `item_image_versions` para versiones por canal |
| F4 | Pendiente | Remove-bg (Remove.bg API o rembg self-hosted) |
| F5 | Pendiente | UI moderna: dropzone + selector de fondo |
| F6 | Pendiente | Versiones por canal (ecom 800, mp 1080, social, mobile) |
| F7 | Pendiente | Storage S3/R2 con prefix por tenant |

## Archivos clave

```
app/Services/Tenant/
└── ImageProcessingService.php
    ├── ALLOWED_MIMES + HEIC_MIMES
    ├── supportsHeic() → Imagick + libheif
    ├── convertHeicToJpeg() → JPG temporal con orientate EXIF
    ├── isHeicFile() → finfo MIME
    ├── validate() → MIME real, peso, corrupción
    ├── processAndStore() → 3 tamaños, WebP, orientate(), HEIC fallback
    └── sanitizeFilename() → slug + UUID corto

app/Models/Tenant/
└── ImageProcessingJob.php           (lifecycle: pending → processing → completed/failed)

app/Jobs/Tenant/
├── ProcessUploadedImageJob.php      (F2 — sin item_id, escribe a image_processing_jobs)
└── ProcessProductImageJob.php       (legacy — con item_id, escribe directo a items.image)

app/Http/Controllers/Tenant/
├── ItemController.php
│   ├── upload()              POST /items/upload          síncrono (legacy)
│   ├── uploadAsync()         POST /items/upload-async    F2: encola y devuelve uuid
│   └── uploadJobStatus()     GET  /items/upload-jobs/{uuid}  polling
└── ItemVariantController::uploadImage  POST /items/{i}/variants/{v}/image  síncrono

resources/js/mixins/
└── imageCompressor.js
    ├── beforeUpload()    canvas resize + heic2any (dynamic import)
    ├── asyncUpload()     reemplaza upload de <el-upload> via :http-request
    └── _pollImageJob()   polling 1.5s, timeout 90s

database/migrations/tenant/
├── 2026_05_08_000001_add_is_primary_to_item_variants.php
└── 2026_05_08_000002_create_image_processing_jobs_table.php
```

## Cómo activar el flujo async en un componente Vue

Por defecto los `<el-upload>` siguen el flujo síncrono. Para migrar uno al async:

```vue
<el-upload
    :http-request="asyncUpload"           {{-- reemplaza :action="..." --}}
    :on-success="onSuccessF"
    :on-error="onErrorF"
    :on-progress="onProgressF"            {{-- nuevo handler --}}
    :before-upload="beforeUpload"
    accept="image/jpeg,image/jpg,image/png,image/webp,image/heic,image/heif">
</el-upload>
```

```js
import { imageCompressor } from '../../../mixins/imageCompressor'

export default {
    mixins: [imageCompressor],
    data: () => ({ processingMsg: '' }),
    methods: {
        onSuccessF(response) {
            this.processingMsg = ''
            // response.data trae: { filename, image_url, processed_filename, ... }
        },
        onProgressF(event) {
            const pct = Math.round(event.percent)
            if (pct < 50) this.processingMsg = `Subiendo… ${pct}%`
            else if (pct < 100) this.processingMsg = 'Procesando…'
        },
    },
}
```

Y en el backend, donde se consume la respuesta del upload, usar `processed_filename`
en vez de `temp_path` (ItemController::store ya lo soporta).

## Reglas duras

❌ **NUNCA** escribas directo a `items.image`, `item_images.image` o `item_variants.image` sin pasar por `ImageProcessingService::processAndStore` (excepto cuando el filename ya viene de un `ProcessUploadedImageJob` completado — ahí ya pasó por el service).

❌ **NUNCA** confíes en la extensión del archivo subido para validar tipo. Siempre `finfo_open()` MIME real (ya lo hace `ImageProcessingService::validate`).

❌ **NUNCA** importes `heic2any` de forma estática (`import heic2any from 'heic2any'`). Siempre dynamic: `await import('heic2any')`. Causa: la lib toca `window`/`global` al cargarse y rompe el bundle.

❌ **NUNCA** elimines `$img->orientate()` del pipeline. Las fotos del celular guardan rotación en EXIF; sin esto salen volteadas.

✅ **SIEMPRE** que agregues un endpoint que reciba imagen, agrega `heic,heif` a la lista de mimes en la validación.

✅ **SIEMPRE** que agregues un componente Vue de upload de imagen, incluye `accept="...,image/heic,image/heif"` para que iPhone permita seleccionarlas.

✅ **SIEMPRE** que toques el pipeline, prueba con: foto de iPhone (HEIC), foto rotada, PNG con transparencia, JPG grande (10MB), nombre con emoji, archivo corrupto.

## Operación: queue worker en producción

El job corre en queue `images`. Configurar supervisor:

```ini
[program:ebaemy-images]
process_name=%(program_name)s_%(process_num)02d
command=php /home/ebaemy/ebaemy/laravel/artisan queue:work --queue=images --tries=3 --timeout=180 --sleep=2
autostart=true
autorestart=true
user=ebaemy
numprocs=2
redirect_stderr=true
stdout_logfile=/home/ebaemy/ebaemy/laravel/storage/logs/queue-images.log
```

Luego:
```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start ebaemy-images:*
```

En **local** con `QUEUE_CONNECTION=sync` (default) el job ejecuta inline — no requiere worker.

## Imagick + libheif en servidor

Para que la red de seguridad backend de HEIC funcione (cuando heic2any del cliente falla):

```bash
sudo apt-get install -y libheif1 libheif-dev php8.3-imagick
sudo systemctl restart php8.3-fpm
```

Verificar:
```bash
php -r 'echo extension_loaded("imagick") ? "Imagick: SI\n" : "Imagick: NO\n";'
php -r 'print_r(Imagick::queryFormats("HEIC"));'
```

Si `queryFormats("HEIC")` retorna array vacío aún teniendo libheif: la extensión `php-imagick` fue compilada contra una ImageMagick antigua sin HEIC. Tocaría recompilar — usualmente NO vale la pena, `heic2any` cliente cubre 99%.

## Limpieza periódica

Los `original_path` en `image_processing_jobs` apuntan a `/tmp/imgq_*`. El SO los limpia, pero las filas quedan. Cron sugerido:

```bash
# Borra jobs completados con más de 7 días
0 3 * * * php /home/ebaemy/ebaemy/laravel/artisan tinker --execute='\App\Models\Tenant\ImageProcessingJob::where("created_at", "<", now()->subDays(7))->whereIn("status", ["completed", "failed"])->delete();'
```

(Iterar por tenant en producción.)

## Cuándo invocar este skill

- Hay que tocar uploads de productos, variantes o galería
- Aparecen errores con HEIC, fotos rotadas, archivos pesados
- Se reporta "subida lenta" o "no se ve el progreso"
- Se va a integrar remove-bg, cropping manual o versiones por canal (F3+)
- Auditoría de seguridad: validar que no se acepten archivos peligrosos
- Hay que conectar el pipeline a un componente nuevo

## Cuándo NO invocar

- Cambios de UI puramente visuales sin tocar el pipeline
- Imágenes de logos/banners de configuración del tenant (otro flujo, no usa este service)
- Reportes que solo LEEN imágenes (no las modifican)
