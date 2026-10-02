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

// Area maxima de canvas que aguanta un movil modesto. Chrome Android ronda los
// 16,7 M de pixeles y por encima devuelve un canvas en blanco; se deja margen.
const MAX_PIXELES_CANVAS = 12 * 1000 * 1000

// `toBlob` puede quedarse sin contestar en moviles con poca memoria.
const TOBLOB_TIMEOUT_MS = 12000

// Lo que admite el servidor hoy (`upload_max_filesize` 20M, y el proxy corta en
// 8M). Se queda en 8 para avisar ANTES de gastar la subida; si se sube el tope
// del proxy, subir tambien este numero.
const LIMITE_SUBIDA_BYTES = 8 * 1024 * 1024

function _mb(bytes) {
    return (Math.round(bytes / 1024 / 1024 * 10) / 10) + ' MB'
}

// Helpers internos para el flujo async (no reactivos, evita Vue reactivity)
const ASYNC_TIMEOUT_MS  = 90000   // desktop default
const ASYNC_INTERVAL_MS = 1500    // polling cada 1.5s

export const imageCompressor = {
    methods: {
        _isMobileClient() {
            return /iPhone|iPad|iPod|Android/i.test(navigator.userAgent || '')
        },
        _getAsyncTimeoutMs() {
            // En móvil el usuario percibe más "cuelgue" a 90%; reducimos espera
            // y activamos fallback sync antes.
            return this._isMobileClient() ? 35000 : ASYNC_TIMEOUT_MS
        },
        /**
         * LA puerta de entrada de cualquier imagen del panel: valida y comprime.
         *
         * Existe porque habia tres validaciones distintas —una por formulario—
         * y las tres se equivocaban en lo mismo: decidian por `file.type`. En
         * Android eso no se puede: segun de donde salga la foto (galeria,
         * Google Fotos, un gestor de archivos, WhatsApp) el navegador entrega
         * el File con `type` vacio o `application/octet-stream`, y el
         * formulario contestaba «Solo se permiten imagenes JPG, PNG…» sobre un
         * JPG perfectamente valido.
         *
         * Aqui se acepta por tipo O por extension, y quien decide de verdad es
         * el servidor, que mira los bytes con finfo (`ImageProcessingService`).
         * El cliente solo filtra lo obvio para no gastar una subida.
         *
         * Devuelve el File listo, o `false` — que es lo que `el-upload` entiende
         * como «no subas esto».
         */
        async prepararImagen(file) {
            if (!file) return false

            const tipo = (file.type || '').toLowerCase()
            const nombre = (file.name || '').toLowerCase()
            const porTipo = tipo.startsWith('image/') && !/svg/.test(tipo)
            const porExtension = /\.(jpe?g|png|gif|webp|bmp|heic|heif)$/i.test(nombre)

            // Lo que NO es imagen de ninguna de las dos formas, fuera.
            if (!porTipo && !porExtension) {
                this.$message && this.$message.error(
                    'Ese archivo no parece una imagen. Usa JPG, PNG, WEBP, GIF o HEIC.'
                )

                return false
            }

            // SVG nunca: se puede meter javascript dentro.
            if (/svg/.test(tipo) || /\.svgz?$/i.test(nombre)) {
                this.$message && this.$message.error('Los archivos SVG no se admiten.')

                return false
            }

            try {
                const listo = await this.beforeUpload(file)

                return listo || false
            } catch (e) {
                // `beforeUpload` ya aviso con el motivo (pesa mas de lo que
                // admite el servidor y no se pudo reducir).
                return false
            }
        },

        async beforeUpload(file) {
            if (!file) return file

            // 1) HEIC/HEIF → JPEG (libheif WASM en cliente).
            if (isHeic(file)) {
                file = await convertHeicToJpegFile(file)
            }

            // Si despues de la conversion sigue sin parecer una imagen, tal cual.
            //
            // Mirando el tipo Y el nombre: Android entrega muchas fotos con
            // `type` vacio o `application/octet-stream` segun de donde salgan
            // (galeria, Google Fotos, WhatsApp, un gestor de archivos). Con la
            // comprobacion antigua esas se saltaban la compresion y se subian
            // CRUDAS — justo las que luego el servidor rechazaba.
            const pareceImagen = (file.type || '').startsWith('image/')
                || /\.(jpe?g|png|gif|webp|bmp)$/i.test(file.name || '')
            if (!pareceImagen) return file

            // 2) Compresión adaptativa: en móvil reducimos resolución y
            //    calidad para acelerar la subida sobre redes celulares.
            const isMobile  = this._isMobileClient()
            const saveData  = !!(navigator.connection && navigator.connection.saveData)
            const maxWidth  = isMobile ? (saveData ? 880 : 960) : 1200
            const maxHeight = isMobile ? (saveData ? 880 : 960) : 1200
            const quality   = isMobile ? (saveData ? 0.64 : 0.68) : 0.82

            // Solo saltar SVG (no se puede comprimir con canvas)
            if (file.type === 'image/svg+xml') return file

            const comprimido = await this._comprimir(file, { maxWidth, maxHeight, quality })

            // Si la compresion no salio, NO se sube el original a ciegas: una
            // foto de camara Android son 3-12 MB y el servidor la rechaza, asi
            // que el operador recibe «error» sin saber por que. Mas vale
            // decirselo aqui, antes de gastar la subida.
            const limite = LIMITE_SUBIDA_BYTES
            if (!comprimido) {
                if (file.size > limite) {
                    this.$message && this.$message.error(
                        'No pudimos reducir la foto en este teléfono y pesa ' + _mb(file.size) +
                        ' (máximo ' + _mb(limite) + '). Prueba a elegirla desde la galería, ' +
                        'o hazla con menos resolución.'
                    )
                    return Promise.reject(new Error('imagen demasiado grande'))
                }

                return file
            }

            return comprimido
        },

        /**
         * Reduce la imagen con canvas, defendiendose de lo que falla en movil.
         *
         * Devuelve el File comprimido, o null si no se pudo — y entonces decide
         * el llamador, que es quien sabe si el original cabe o no.
         *
         * Tres cosas que rompen en Android y no en iPhone:
         *
         * 1. `readAsDataURL` carga la foto entera como base64 (1,37x su peso) y
         *    una foto de 50 MP revienta la memoria de un telefono modesto. Se
         *    usa `createImageBitmap`, que decodifica sin pasar por base64, y el
         *    camino viejo queda solo de respaldo.
         * 2. Chrome Android limita el AREA del canvas (unos 16,7 M de pixeles en
         *    muchos dispositivos). Las fotos de 50 y 108 MP se pasan de largo:
         *    el canvas sale en blanco o `toBlob` devuelve null. Por eso se acota
         *    por megapixeles ANTES de dibujar.
         * 3. `toBlob` puede tardar o devolver null sin lanzar nada. Se le pone
         *    un plazo: si no contesta, se da por fallida.
         */
        async _comprimir(file, { maxWidth, maxHeight, quality }) {
            const fuente = await this._decodificar(file)
            if (!fuente) return null

            const anchoOrig = fuente.width
            const altoOrig  = fuente.height
            if (!anchoOrig || !altoOrig) return null

            let w = anchoOrig
            let h = altoOrig

            // Tope por lado
            if (w > maxWidth)  { h = Math.round(h * maxWidth / w);  w = maxWidth }
            if (h > maxHeight) { w = Math.round(w * maxHeight / h); h = maxHeight }

            // Y tope por area, que es el limite que de verdad aplica el movil.
            const area = w * h
            if (area > MAX_PIXELES_CANVAS) {
                const f = Math.sqrt(MAX_PIXELES_CANVAS / area)
                w = Math.max(1, Math.floor(w * f))
                h = Math.max(1, Math.floor(h * f))
            }

            try {
                const canvas = document.createElement('canvas')
                canvas.width  = w
                canvas.height = h
                const ctx = canvas.getContext('2d')
                if (!ctx) return null
                ctx.drawImage(fuente, 0, 0, w, h)

                const blob = await this._aBlob(canvas, quality)
                if (fuente.close) fuente.close()   // libera el bitmap
                if (!blob || !blob.size) return null

                const name = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg'

                return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() })
            } catch (e) {
                console.warn('[imageCompressor] el canvas no pudo con la foto:', e)
                return null
            }
        },

        /** Decodifica el archivo a algo dibujable, sin base64 si se puede. */
        async _decodificar(file) {
            if (typeof createImageBitmap === 'function') {
                try {
                    return await createImageBitmap(file)
                } catch (e) {
                    // Safari viejo y algunos Android no admiten un File directo.
                    console.warn('[imageCompressor] createImageBitmap fallo, voy por el camino viejo:', e)
                }
            }

            return new Promise((resolve) => {
                const reader = new FileReader()
                reader.onload = (e) => {
                    const img = new Image()
                    img.onload  = () => resolve(img)
                    img.onerror = () => resolve(null)
                    img.src = e.target.result
                }
                reader.onerror = () => resolve(null)
                reader.readAsDataURL(file)
            })
        },

        /** `toBlob` con plazo: en movil puede no contestar nunca. */
        _aBlob(canvas, quality) {
            return new Promise((resolve) => {
                let resuelto = false
                const fin = (b) => { if (!resuelto) { resuelto = true; resolve(b) } }

                setTimeout(() => fin(null), TOBLOB_TIMEOUT_MS)

                try {
                    canvas.toBlob((b) => fin(b), 'image/jpeg', quality)
                } catch (e) {
                    fin(null)
                }
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
                    // Si el job falló en cola, intentamos fallback sync para no
                    // bloquear al usuario móvil.
                    const fallbackOk = await this._trySyncFallbackUpload(file, onProgress, onSuccess)
                    if (!fallbackOk) {
                        const msg = result.error_message || 'Error procesando la imagen.'
                        onError && onError(new Error(msg))
                    }
                }
            } catch (err) {
                // Fallback resiliente: si el flujo async se estanca (p.ej. sin
                // worker de cola), hacemos upload síncrono para no dejar la UI
                // congelada en 90%.
                const isTimeout = /Tiempo de espera agotado/i.test((err && err.message) || '')
                if (isTimeout) {
                    const fallbackOk = await this._trySyncFallbackUpload(file, onProgress, onSuccess)
                    if (!fallbackOk) {
                        onError && onError(err)
                    }
                    return
                }
                onError && onError(err)
            }
        },
        async _trySyncFallbackUpload(file, onProgress, onSuccess) {
            try {
                onProgress && onProgress({ percent: 92 })
                const fd = new FormData()
                fd.append('file', file)
                fd.append('skip_preview', '1')
                const syncResp = await this.$http.post('/items/upload', fd)

                if (!syncResp.data || !syncResp.data.success) {
                    return false
                }

                onProgress && onProgress({ percent: 100 })
                onSuccess && onSuccess(syncResp.data, file)
                return true
            } catch (e) {
                return false
            }
        },

        _pollImageJob(uuid, onProgress) {
            return new Promise((resolve, reject) => {
                const start = Date.now()
                let pct = 50
                const timeoutMs = this._getAsyncTimeoutMs()

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

                            if (Date.now() - start > timeoutMs) {
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
