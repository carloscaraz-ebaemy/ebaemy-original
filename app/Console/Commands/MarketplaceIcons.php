<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera los PNG del icono del marketplace a partir del mismo diseño que
 * `public/images/mp-store-icon.svg`.
 *
 * Existe por dos motivos. Uno: `public/images/icon-192.png` y `icon-512.png`
 * eran marcadores de posición de 919 y 2101 bytes (lo decía el propio
 * `PWA_ICONS_NEEDED.txt`), y además los comparten el ERP y las tiendas de los
 * tenants, así que no se podían reemplazar sin tocarles el icono a todos. Dos:
 * un PNG binario commiteado sin forma de regenerarlo es un archivo huérfano;
 * si cambia el color de marca, con esto se vuelve a sacar en un comando.
 *
 * No hay Imagick en este servidor, así que el dibujo es con GD sobre las
 * mismas coordenadas del SVG (lienzo de 64) y supersampling x3 para que los
 * bordes diagonales del toldo no salgan dentados.
 *
 *   php artisan marketplace:icons
 */
class MarketplaceIcons extends Command
{
    protected $signature = 'marketplace:icons {--force : Sobrescribe los PNG existentes}';

    protected $description = 'Genera los iconos PNG del marketplace (favicon y PWA) desde el diseño del SVG';

    /** Los tamaños que el layout y el manifest del marketplace referencian. */
    private const SALIDAS = [
        'mp-favicon-32.png' => 32,
        'mp-icon-192.png'   => 192,
        'mp-icon-512.png'   => 512,
    ];

    /** Factor de supersampling. x3 basta para las diagonales del toldo. */
    private const SS = 3;

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('Falta la extensión GD: sin ella no se puede dibujar nada.');

            return self::FAILURE;
        }

        $destino = public_path('images');

        foreach (self::SALIDAS as $nombre => $lado) {
            $ruta = $destino . DIRECTORY_SEPARATOR . $nombre;

            if (is_file($ruta) && !$this->option('force')) {
                $this->line("  ya existe, se conserva: {$nombre} (usa --force para rehacerlo)");
                continue;
            }

            $img = $this->dibujar($lado);

            if (!imagepng($img, $ruta, 9)) {
                imagedestroy($img);
                $this->error("No se pudo escribir {$nombre}.");

                return self::FAILURE;
            }

            imagedestroy($img);
            $this->info(sprintf('  %s — %dx%d, %s', $nombre, $lado, $lado, $this->peso($ruta)));
        }

        $this->line('');
        $this->line('El SVG es la fuente del diseño: public/images/mp-store-icon.svg');

        return self::SUCCESS;
    }

    /**
     * Dibuja el icono a `$lado` px. Las coordenadas son las del SVG (lienzo de
     * 64), escaladas, para que las dos versiones no se puedan desincronizar.
     */
    private function dibujar(int $lado): \GdImage
    {
        $grande = $lado * self::SS;
        $img    = imagecreatetruecolor($grande, $grande);

        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        $teal   = imagecolorallocate($img, 15, 138, 130);   // #0f8a82
        $blanco = imagecolorallocate($img, 255, 255, 255);
        // El teal al 82 % sobre blanco, resuelto a color plano: GD no compone
        // opacidades de relleno como el SVG.
        $franja = imagecolorallocate($img, 58, 159, 153);

        $u = fn (float $v): int => (int) round($v / 64 * $grande);

        // Fondo redondeado (rx 14 del SVG).
        $this->rectRedondeado($img, 0, 0, $grande, $grande, $u(14.0), $teal);

        // Toldo: trapecio más ancho abajo — M17 14h30l6 11H11z
        imagefilledpolygon($img, [
            $u(17), $u(14),
            $u(47), $u(14),
            $u(53), $u(25),
            $u(11), $u(25),
        ], $blanco);

        // Dos franjas, en las mismas diagonales que el SVG.
        imagefilledpolygon($img, [
            $u(26.6), $u(14), $u(30.8), $u(14), $u(27.8), $u(25), $u(23.2), $u(25),
        ], $franja);
        imagefilledpolygon($img, [
            $u(37), $u(14), $u(41.2), $u(14), $u(45.4), $u(25), $u(40.8), $u(25),
        ], $franja);

        // Fachada. El hueco de 3 px sobre ella es lo que separa el toldo del
        // cuerpo: sin ese aire, a 16 px se funden en una sola mancha.
        $this->rectRedondeado($img, $u(15), $u(28), $u(34), $u(24), $u(2), $blanco);

        // Puerta
        imagefilledrectangle($img, $u(26), $u(36), $u(38), $u(52), $teal);

        if (self::SS === 1) {
            return $img;
        }

        $final = imagescale($img, $lado, $lado, IMG_BICUBIC);
        imagedestroy($img);

        imagesavealpha($final, true);

        return $final;
    }

    /** Rectángulo de esquinas redondeadas: GD no trae primitiva para esto. */
    private function rectRedondeado(\GdImage $img, int $x, int $y, int $ancho, int $alto, int $r, int $color): void
    {
        $r = max(0, min($r, (int) floor(min($ancho, $alto) / 2)));

        imagefilledrectangle($img, $x + $r, $y, $x + $ancho - $r - 1, $y + $alto - 1, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $ancho - 1, $y + $alto - $r - 1, $color);

        $d = $r * 2;
        foreach ([
            [$x + $r, $y + $r],
            [$x + $ancho - $r - 1, $y + $r],
            [$x + $r, $y + $alto - $r - 1],
            [$x + $ancho - $r - 1, $y + $alto - $r - 1],
        ] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $d, $d, $color);
        }
    }

    private function peso(string $ruta): string
    {
        $b = (int) @filesize($ruta);

        return $b >= 1024 ? round($b / 1024, 1) . ' KB' : $b . ' B';
    }
}
