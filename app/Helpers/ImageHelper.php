<?php

namespace App\Helpers;

class ImageHelper
{
    /**
     * Generate optimized image HTML with lazy loading.
     */
    public static function productImage(
        string $path,
        string $alt,
        int $width = 300,
        int $height = 300,
        bool $lazy = true,
        string $class = 'product-img'
    ): string {
        $fallback = asset('logo/imagen-no-disponible.jpg');
        $attrs = $lazy ? 'loading="lazy" decoding="async"' : 'decoding="async"';

        return sprintf(
            '<img src="%s" %s width="%d" height="%d" alt="%s" class="%s" onerror="this.src=\'%s\'">',
            e($path),
            $attrs,
            $width,
            $height,
            e($alt),
            e($class),
            $fallback
        );
    }
}
