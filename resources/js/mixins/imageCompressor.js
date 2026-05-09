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
 * - heic2any se carga via dynamic import SOLO cuando llega un HEIC. Así el
 *   bundle principal no incluye libheif WASM (~140KB) y la lib no rompe la
 *   inicialización en pages que nunca subirán imágenes.
 */

const HEIC_MIME_RE = /image\/(heic|heif)/i
const HEIC_EXT_RE  = /\.(heic|heif)$/i

function isHeic(file) {
    if (!file) return false
    if (HEIC_MIME_RE.test(file.type || '')) return true
    if (HEIC_EXT_RE.test(file.name || '')) return true
    return false
}

async function convertHeicToJpegFile(file) {
    try {
        // Lazy load — heic2any solo entra al bundle cuando hay un HEIC real
        const mod = await import('heic2any')
        const heic2any = mod.default || mod
        const blob = await heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.9,
        })
        const out = Array.isArray(blob) ? blob[0] : blob
        const newName = (file.name || 'foto').replace(/\.(heic|heif)$/i, '.jpg')
        return new File([out], newName, { type: 'image/jpeg', lastModified: Date.now() })
    } catch (err) {
        console.warn('[imageCompressor] heic2any falló, se sube HEIC raw:', err)
        return file
    }
}

// Helpers internos para el flujo async (no reactivos, evita Vue reactivity)
const ASYNC_TIMEOUT_MS  = 90000   // 90s — HEIC + Imagick + remove-bg futuro
const ASYNC_INTERVAL_MS = 1500    // polling cada 1.5s

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

        // ── Upload asíncrono con queue + polling ─────────────────────────
        // Reemplaza el upload por defecto de <el-upload> via :http-request.
        // El backend encola el procesamiento y devuelve un UUID; aquí
        // hacemos polling y solo llamamos onSuccess cuando el job termina.
        // Asume que el componente padre ya pasó el file por beforeUpload
        // (compresión + heic→jpg). Element UI llama esto con un objeto:
        //   { file, onSuccess, onError, onProgress, action, ... }
        // Donde 'action' es la URL configurada en el <el-upload>; aquí lo
        // ignoramos y siempre vamos a /items/upload-async.
        async asyncUpload(req) {
            const { file, onSuccess, onError, onProgress } = req
            try {
                onProgress && onProgress({ percent: 5 })
                const fd = new FormData()
                fd.append('file', file)

                const upResp = await this.$http.post('/items/upload-async', fd, {
                    onUploadProgress: (e) => {
                        if (e && e.total && onProgress) {
                            const pct = Math.min(50, Math.round((e.loaded / e.total) * 50))
                            onProgress({ percent: pct })
                        }
                    },
                })

                if (!upResp.data || !upResp.data.success) {
                    onError && onError(new Error(upResp.data?.message || 'Error al subir'))
                    return
                }

                const uuid = upResp.data.job_uuid
                onProgress && onProgress({ percent: 50 })

                // Polling hasta completed o failed
                const result = await this._pollImageJob(uuid, onProgress)

                if (result.status === 'completed') {
                    onProgress && onProgress({ percent: 100 })
                    // Mantenemos forma del response equivalente al endpoint
                    // sync (data.filename) + agregamos processed_filename
                    // para que el ItemController::store lo asigne directo.
                    onSuccess && onSuccess({
                        success: true,
                        data: {
                            filename:       result.filename,
                            image_url:      result.image_url,
                            processed_filename:        result.filename,
                            processed_filename_medium: result.filename_medium,
                            processed_filename_small:  result.filename_small,
                        },
                    }, file)
                } else {
                    const msg = result.error_message || 'Error procesando la imagen.'
                    onError && onError(new Error(msg))
                }
            } catch (err) {
                onError && onError(err)
            }
        },

        _pollImageJob(uuid, onProgress) {
            return new Promise((resolve, reject) => {
                const start = Date.now()
                let pct = 50

                const tick = () => {
                    this.$http.get(`/items/upload-jobs/${uuid}`)
                        .then(({ data }) => {
                            if (!data || !data.success) {
                                return reject(new Error(data?.message || 'Job desconocido'))
                            }
                            if (data.status === 'completed' || data.status === 'failed') {
                                return resolve(data)
                            }
                            pct = Math.min(90, pct + 5)
                            onProgress && onProgress({ percent: pct })

                            if (Date.now() - start > ASYNC_TIMEOUT_MS) {
                                return reject(new Error('Tiempo de espera agotado.'))
                            }
                            setTimeout(tick, ASYNC_INTERVAL_MS)
                        })
                        .catch(reject)
                }
                setTimeout(tick, ASYNC_INTERVAL_MS)
            })
        },
    },
}
