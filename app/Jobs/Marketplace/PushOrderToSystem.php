<?php

namespace App\Jobs\Marketplace;

use App\Models\System\MarketplaceUserOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push de un pedido del tenant al snapshot agregado en system.
 *
 * Idempotente: si ya existe una fila con el mismo (hostname_id, order_id)
 * la actualiza en lugar de insertar.
 *
 * Se dispatcha desde el tenant cuando un pedido cambia de estado
 * (confirmado, cancelado, completado). El push es asincrono — el
 * pedido en el tenant nunca falla si el system esta caido: el job
 * se encola y reintenta.
 *
 * Conexion: el job se ejecuta en el contexto del worker (que tiene
 * acceso a system DB directamente, sin contexto de tenant) — por eso
 * MarketplaceUserOrder usa UsesSystemConnection.
 */
class PushOrderToSystem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 120, 600, 1800, 3600]; // 30s, 2m, 10m, 30m, 1h

    public function __construct(
        public int $userId,
        public int $hostnameId,
        public int $orderId,
        public float $total,
        public string $currency,
        public string $status,
        public ?string $confirmedAt,
        public ?string $cancelledAt,
        public int $itemsCount,
        public array $productCategories,
    ) {}

    public function handle(): void
    {
        MarketplaceUserOrder::updateOrCreate(
            [
                'hostname_id' => $this->hostnameId,
                'order_id'    => $this->orderId,
            ],
            [
                'user_id'            => $this->userId,
                'total'              => $this->total,
                'currency'           => $this->currency,
                'status'             => $this->status,
                'confirmed_at'       => $this->confirmedAt,
                'cancelled_at'       => $this->cancelledAt,
                'items_count'        => $this->itemsCount,
                'product_categories' => $this->productCategories,
            ]
        );
    }
}
