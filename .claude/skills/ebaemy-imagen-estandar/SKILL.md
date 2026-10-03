---
name: ebaemy-imagen-estandar
description: El estándar de imagen de EBAEMY y cómo hacerlo cumplir. Invocar cuando se reporte que el sistema va lento por imágenes, que una foto no sube o no se ve, cuando se vaya a añadir un formulario que suba imágenes, cuando haya que auditar o reparar el catálogo de fotos, o cuando alguien pregunte "qué tamaño debe tener la imagen". También antes de tocar ImageProcessingService, imageCompressor.js o cualquier <el-upload> de imagen.
---

# Estándar de imagen — EBAEMY

Una tienda no se cae por una foto pesada: se cae por mil. El estándar existe para
que ninguna imagen entre al sistema sin pasar por el mismo embudo, y para poder
**comprobarlo** en vez de confiar en que se respetó.

## El estándar

### Lo que el usuario sube (entrada)

| | |
|---|---|
| Formatos admitidos | JPG, JPEG, PNG, WEBP, GIF, BMP, **HEIC/HEIF** (iPhone) |
| Nunca | SVG — admite JavaScript dentro |
| Tope duro del proxy | **8 MB** · por encima, nginx corta con 413 antes de llegar a PHP |
| Tope de PHP | 20 MB (`upload_max_filesize`) |
| Antes de salir del teléfono | el cliente la reduce a **960 px / calidad 0,68** en móvil y 1200 px / 0,82 en escritorio |

En la práctica, una foto de catálogo sale del teléfono pesando **entre 60 y 150 KB**.
Si algo llega al servidor con megas, es que se saltó el embudo.

### Lo que se guarda (salida)

`ImageProcessingService::processAndStore` genera **cinco versiones** de cada imagen.
Es la única manera legítima de escribir en disco:

| Versión | Medidas | Para qué | Tope |
|---|---|---|---|
| principal (sin sufijo) | 1200 px de ancho, calidad 80 | catalogación, origen de las demás | **300 KB** |
| `_medium` | 512 px | catálogo del panel | 400 KB |
| `_small` | 256 px | miniaturas | 400 KB |
| `_mp` | **1080 × 1080 cuadrado** | marketplace (estilo Falabella / Mercado Libre) | 400 KB |
| `_mobile` | 640 px, calidad 72 | tienda en el celular | 400 KB |

Más: corrección de rotación EXIF (`orientate()`), nombre saneado (slug + UUID corto)
y copia en WebP.

### Las dos puertas, y no hay terceras

```
Navegador  →  imageCompressor.prepararImagen()   ← valida y comprime
Servidor   →  ImageProcessingService::processAndStore()   ← genera las 5 versiones
```

- **Front:** todo `<el-upload>` de imagen lleva `:before-upload="prepararImagen"` y el
  mixin `imageCompressor`. No se escribe una lista de MIMEs nueva: la puerta acepta
  por tipo **o** por extensión, porque en Android `file.type` llega vacío a menudo.
- **Back:** nunca escribas en `items.image`, `item_images.image` ni
  `item_variants.image` sin pasar por el service.

## Comprobarlo: `php artisan images:audit`

El estándar es ejecutable. El comando recorre los tenants y contrasta cada imagen
contra la tabla de arriba.

```bash
php artisan images:audit                    # informe de todos los tenants
php artisan images:audit --tenant=<uuid>    # uno solo
php artisan images:audit --json             # salida para un agente o un cron
php artisan images:audit --fix              # regenera lo que incumple
php artisan images:audit --limit=200        # corta el recorrido (pruebas)
```

Sale con **código distinto de cero si algo incumple**, así que encadena en un cron
sin leer el texto. **No borra nada**: `--fix` solo regenera a partir de la imagen
principal, que es la única que no se puede reconstruir.

### Línea base (producción, 2026-10-02)

```
Productos con imagen: 977
Cumplen el estándar : 804
Incumplen           : 173
```

Repartidos así:

| Tenant | Incumplen | Motivo dominante |
|---|---|---|
| `torneo` | 108 | **la imagen principal no está en disco** (referencias rotas; tenant bloqueado) |
| `alasitas` | 55 | 52 pesan más de 300 KB · 7 miden más de 1200 px |
| `mitienda` | 8 | peso y variantes faltantes |
| `makingroup`, `myka` | 2 | peso / variantes |

`carolayimport` (326), `importacionesdeywa` (361), `motalvan`, `floristeria` y
`charitzi` están al 100 %.

**Qué significa cada motivo:**

- *la principal pesa más de 300 KB* / *mide más de 1200 px* → esa imagen **no pasó por
  el pipeline**. Entró por un camino que se lo saltó, o es anterior a que existiera.
  `--fix` lo arregla.
- *falta la versión `_mp` / `_mobile`* → es anterior a la fase 6 del pipeline. `--fix`,
  o el comando específico `images:backfill-variants`.
- *la imagen principal no está en disco* → **`--fix` no puede hacer nada**: no hay de
  dónde regenerar. O se vuelve a subir la foto, o se limpia la referencia. Antes de
  tocar nada, mira si el tenant sigue vivo.

## Cuando añadas un formulario que suba imágenes

1. `<el-upload :before-upload="prepararImagen" :action="/items/upload" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif">`
2. `import { imageCompressor }` y `mixins: [imageCompressor]`.
3. En el backend, el endpoint valida con `UploadFileHelper::validateUploadFile` (que ya
   avisa con el motivo si PHP descartó el archivo por tamaño) y procesa con el service.
4. Corre `php artisan images:audit --limit=20` después de subir una foto de prueba.

## Trampas que ya costaron caro

- **Decidir por `file.type`.** En Android llega vacío o `application/octet-stream` según
  de dónde salga la foto (galería, Google Fotos, WhatsApp, un gestor de archivos). Las
  validaciones rechazaban JPGs válidos, y la guarda `if (!file.type.startsWith('image/'))`
  hacía que esas mismas fotos **se saltaran la compresión y se subieran crudas**.
- **Un botón de subida sin compresión.** `items/form.vue` (Productos y Servicios) validaba
  y devolvía `true`: subía la foto tal cual, 3-12 MB desde un teléfono.
- **El límite invisible.** `upload_max_filesize` estuvo en **2M** hasta el 2026-10-02.
  Cualquier foto de cámara lo pasaba, PHP la descartaba y el helper reventaba con un 500
  mudo. Antes de depurar el pipeline, **mira los tres topes** (proxy, PHP, service): están
  en el skill `ebaemy-image-pipeline`.
- **El canvas de Chrome Android** corta sobre ~16,7 M de píxeles: una foto de 50 MP sale
  en blanco o devuelve `toBlob` null. Por eso se acota por megapíxeles antes de dibujar.

## Mantenimiento recomendado

Mensual, o después de cualquier importación masiva de catálogo:

```bash
php artisan images:audit --json > /tmp/img.json   # medir
php artisan images:audit --fix                    # corregir lo corregible
php artisan images:audit                          # confirmar que quedó en cero
```

Lo que `--fix` no arregla (imágenes que no están en disco) es decisión de negocio:
resubirlas o limpiar la referencia.

## Relación con los otros skills

- `ebaemy-image-pipeline` — **cómo funciona** el pipeline por dentro (HEIC, EXIF, cola
  asíncrona, Imagick, los topes del servidor). Este skill es **qué debe cumplirse**.
- `ebaemy-deploy` — `--fix` sobre miles de imágenes es trabajo pesado: no lo lances
  durante un despliegue.
