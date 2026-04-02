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

                const maxSizeKB = 500;
                const maxWidth = 1200;
                const maxHeight = 1200;
                const quality = 0.82;

                // Si ya es menor al limite, no comprimir
                if (file.size <= maxSizeKB * 1024) {
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
