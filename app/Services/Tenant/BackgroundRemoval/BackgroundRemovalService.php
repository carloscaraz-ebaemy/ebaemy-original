<?php

namespace App\Services\Tenant\BackgroundRemoval;

use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\Cache;

/**
 * Quitar el fondo de la imagen principal: que proveedor, si esta encendido y
 * cuanto le queda a la tienda este mes.
 *
 * Cada foto se paga al proveedor, asi que hay un cupo mensual POR TIENDA
 * (`services.background_removal.monthly_limit`). El contador vive en la
 * cache: es un freno de gasto, no contabilidad. Si la cache se vacia, el
 * cupo del mes vuelve a empezar, y el gasto real lo dice la factura del
 * proveedor.
 *
 * Sin clave configurada, `enabled()` es false y el boton no aparece.
 */
class BackgroundRemovalService
{
    public function enabled(): bool
    {
        return $this->remover() !== null;
    }

    public function limit(): int
    {
        return max(0, (int) config('services.background_removal.monthly_limit', 100));
    }

    public function used(): int
    {
        return (int) Cache::get($this->claveCupo(), 0);
    }

    public function remaining(): int
    {
        return max(0, $this->limit() - $this->used());
    }

    /**
     * Quita el fondo y descuenta una foto del cupo. Solo se descuenta si el
     * proveedor contesto bien: un fallo no se cobra.
     *
     * @throws BackgroundRemovalException
     */
    public function remove(string $contenido): string
    {
        $remover = $this->remover();
        if ($remover === null) {
            throw new BackgroundRemovalException('Quitar el fondo no está activado en este sistema.');
        }

        if ($this->remaining() <= 0) {
            throw new BackgroundRemovalException(
                'Tu tienda ya usó las ' . $this->limit() . ' fotos de este mes para quitar fondos. El cupo se renueva el día 1.'
            );
        }

        $resultado = $remover->remove($contenido);

        $clave = $this->claveCupo();
        Cache::add($clave, 0, now()->addDays(40));
        Cache::increment($clave);

        return $resultado;
    }

    private function remover(): ?BackgroundRemover
    {
        $cfg = config('services.background_removal', []);

        return match ($cfg['driver'] ?? null) {
            'photoroom' => !empty($cfg['photoroom_key']) ? new PhotoroomRemover($cfg['photoroom_key']) : null,
            default     => null,
        };
    }

    private function claveCupo(): string
    {
        $website = app(Environment::class)->tenant();

        return 'bg_removal:' . ($website->uuid ?? 'sin-tenant') . ':' . now()->format('Y-m');
    }
}
