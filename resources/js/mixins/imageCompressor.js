/**
 * Mixin para comprimir imagenes antes de subirlas via el-upload.
 *
 * Uso en componente:
 *   import { imageCompressor } from '../../../mixins/imageCompressor'
 *   mixins: [imageCompressor],
 *
 *   <el-upload :before-upload="beforeUpload" ...>
 *
 * Soporte HEIC/HEIF:
 * - Si el browser entrega un archivo HEIC (iPhone share-sheet, Safari sin
 *   conversión), lo detectamos por mime o extensión, lo convertimos a JPEG
 *   en cliente con heic2any (libheif WASM) y luego seguimos el pipeline
 *   normal (resize + compress en canvas). Sin esto el <img> HTML no puede
 *   renderizar HEIC y el archivo se sube crudo sin comprimir.
 */
import heic2any from 'heic2any'

const HEIC_MIME_RE = /image\/(heic|heif)/i
const HEIC_EXT_RE  = /\.(heic|heif)$/i

function isHeic(file) {
    if (!file) return false
    if (HEIC_MIME_RE.test(file.type || '')) return true
    if (HEIC_EXT_RE.test(file.name || '')) return true
    // Algunos browsers entregan type vacío para HEIC — chequeo de magic bytes
    // queda fuera por costo (heic2any falla rápido si no es HEIC, así que
    // basta con la extensión + mime para 99% de casos).
    return false
}

async function convertHeicToJpegFile(file) {
    try {
        const blob = await heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.9,
        })
        // heic2any devuelve Blob o Blob[] (cuando es secuencia HEIC) — agarramos el primero.
        const out = Array.isArray(blob) ? blob[0] : blob
        const newName = (file.name || 'foto').replace(/\.(heic|heif)$/i, '.jpg')
        return new File([out], newName, { type: 'image/jpeg', lastModified: Date.now() })
    } catch (err) {
        // heic2any falló — devolvemos el archivo original y dejamos que el
        // backend intente convertir con Imagick (red de seguridad).
        // eslint-disable-next-line no-console
        console.warn('[imageCompressor] heic2any falló, se sube HEIC raw:', err)
        return file
    }
}

export const imageCompressor = {
    methods: {
        async beforeUpload(file) {
            if (!file) return file

            // 1) HEIC/HEIF → JPEG (libheif WASM en cliente).
            if (isHeic(file)) {
                file = await convertHeicToJpegFile(file)
            }

            // Si después de la conversión sigue sin ser una imagen, devolvemos tal cual
            if (!file.type.startsWith('image/')) return file

            // 2) Compresión adaptativa: en móvil reducimos resolución y
            //    calidad para acelerar la subida sobre redes celulares.
            const isMobile  = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent)
            const maxWidth  = isMobile ? 1024 : 1200
            const maxHeight = isMobile ? 1024 : 1200
            const quality   = isMobile ? 0.72 : 0.82

            // Solo saltar SVG (no se puede comprimir con canvas)
            if (file.type === 'image/svg+xml') return file

            return new Promise((resolve) => {
                const reader = new FileReader()
                reader.onload = (e) => {
                    const img = new Image()
                    img.onload = () => {
                        const canvas = document.createElement('canvas')
                        let w = img.width
                        let h = img.height

                        if (w > maxWidth) {
                            h = Math.round(h * maxWidth / w)
                            w = maxWidth
                        }
                        if (h > maxHeight) {
                            w = Math.round(w * maxHeight / h)
                            h = maxHeight
                        }

                        canvas.width  = w
                        canvas.height = h
                        const ctx = canvas.getContext('2d')
                        ctx.drawImage(img, 0, 0, w, h)

                        canvas.toBlob((blob) => {
                            if (!blob) return resolve(file)
                            // Cambiar extensión a .jpg para que Laravel no rechace
                            const name = file.name.replace(/\.[^.]+$/, '.jpg')
                            const compressed = new File([blob], name, {
                                type: 'image/jpeg',
                                lastModified: Date.now(),
                            })
                            resolve(compressed)
                        }, 'image/jpeg', quality)
                    }
                    img.onerror = () => resolve(file)
                    img.src = e.target.result
                }
                reader.onerror = () => resolve(file)
                reader.readAsDataURL(file)
            })
        },
    },
}
