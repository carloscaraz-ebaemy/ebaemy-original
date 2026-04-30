/**
 * Mixin para comprimir imagenes antes de subirlas via el-upload.
 *
 * Uso en componente:
 *   import { imageCompressor } from '../../../mixins/imageCompressor'
 *   mixins: [imageCompressor],
 *
 *   <el-upload :before-upload="beforeUpload" ...>
 */
export const imageCompressor = {
    methods: {
        beforeUpload(file) {
            return new Promise((resolve, reject) => {
                if (!file || !file.type.startsWith('image/')) {
                    return resolve(file);
                }

                // Compresión adaptiva: en móvil reducimos resolución y calidad
                // para acelerar la subida sobre redes celulares.
                const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                const maxWidth = isMobile ? 1024 : 1200;
                const maxHeight = isMobile ? 1024 : 1200;
                const quality = isMobile ? 0.72 : 0.82;

                // Solo saltar SVG (no se puede comprimir con canvas)
                if (file.type === 'image/svg+xml') {
                    return resolve(file);
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        let w = img.width;
                        let h = img.height;

                        if (w > maxWidth) {
                            h = Math.round(h * maxWidth / w);
                            w = maxWidth;
                        }
                        if (h > maxHeight) {
                            w = Math.round(w * maxHeight / h);
                            h = maxHeight;
                        }

                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);

                        canvas.toBlob((blob) => {
                            if (!blob) return resolve(file);
                            // Cambiar extension a .jpg para que Laravel no rechace
                            const name = file.name.replace(/\.[^.]+$/, '.jpg');
                            const compressed = new File([blob], name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            resolve(compressed);
                        }, 'image/jpeg', quality);
                    };
                    img.onerror = () => resolve(file);
                    img.src = e.target.result;
                };
                reader.onerror = () => resolve(file);
                reader.readAsDataURL(file);
            });
        }
    }
};
