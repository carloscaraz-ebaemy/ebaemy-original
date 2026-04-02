/**
 * ImageCompressor — comprime imagenes en el navegador antes de subirlas.
 * Reduce tamaño y peso para evitar errores 413 y saturar el servidor.
 *
 * Uso:
 *   const compressed = await ImageCompressor.compress(file, { maxWidth: 1200, quality: 0.8 });
 *   // compressed es un File listo para subir
 */
(function (window) {
    'use strict';

    var ImageCompressor = {
        defaults: {
            maxWidth: 1200,
            maxHeight: 1200,
            quality: 0.82,
            maxSizeKB: 500,
            type: 'image/jpeg'
        },

        compress: function (file, options) {
            var opts = Object.assign({}, this.defaults, options || {});

            return new Promise(function (resolve, reject) {
                if (!file || !file.type.startsWith('image/')) {
                    return resolve(file);
                }

                // Si ya es menor al limite, no comprimir
                if (file.size <= opts.maxSizeKB * 1024) {
                    return resolve(file);
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    var img = new Image();
                    img.onload = function () {
                        var canvas = document.createElement('canvas');
                        var w = img.width;
                        var h = img.height;

                        // Redimensionar manteniendo proporcion
                        if (w > opts.maxWidth) {
                            h = Math.round(h * opts.maxWidth / w);
                            w = opts.maxWidth;
                        }
                        if (h > opts.maxHeight) {
                            w = Math.round(w * opts.maxHeight / h);
                            h = opts.maxHeight;
                        }

                        canvas.width = w;
                        canvas.height = h;
                        var ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);

                        canvas.toBlob(function (blob) {
                            if (!blob) return resolve(file);

                            var name = file.name.replace(/\.[^.]+$/, '.jpg');
                            var compressed = new File([blob], name, {
                                type: opts.type,
                                lastModified: Date.now()
                            });
                            resolve(compressed);
                        }, opts.type, opts.quality);
                    };
                    img.onerror = function () { resolve(file); };
                    img.src = e.target.result;
                };
                reader.onerror = function () { resolve(file); };
                reader.readAsDataURL(file);
            });
        }
    };

    window.ImageCompressor = ImageCompressor;
}(window));
