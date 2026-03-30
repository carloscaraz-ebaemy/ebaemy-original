<?php

    namespace App\Models\Tenant;


    use Illuminate\Database\Eloquent\SoftDeletes;
    use App\Models\Tenant\Document;


    class Order extends ModelTenant
    {
        use SoftDeletes;

        protected $fillable = [
            'external_id',
            'person_id',
            'customer',
            'shipping_address',
            'items',
            'total',
            'points_redeemed',
            'points_earned',
            'reference_payment',
            'document_external_id',
            'number_document',
            'status_order_id',
            'purchase',
            'apply_restaurant',
            // Canal de venta
            'channel_id',
            'external_order_ref',   // Nro pedido en Saga/ML/Instagram
            'marketplace_notes',    // Notas/link del marketplace
            'warehouse_id',
            'seller_id',
            // L2 — Culqi pre-autorización
            'culqi_charge_id',
            'payment_status',
        ];

        protected $casts = [
            'customer' => 'array',
            'items' => 'array',
            'purchase' => 'array'
        ];

        public function status_order()
        {
            return $this->belongsTo(StatusOrder::class);
        }

        public function sale_note()
        {
            return $this->hasOne(SaleNote::class);
        }

        public function channel()
        {
            return $this->belongsTo(SalesChannel::class, 'channel_id');
        }

        public function warehouse()
        {
            return $this->belongsTo(\Modules\Inventory\Models\Warehouse::class, 'warehouse_id');
        }

        public function seller()
        {
            return $this->belongsTo(\App\Models\Tenant\User::class, 'seller_id');
        }

        // ── Scopes ────────────────────────────────────────────────────────────

        public function scopeByChannel($query, $channelId)
        {
            return $query->where('channel_id', $channelId);
        }

        public function scopeEcommerce($query)
        {
            return $query->whereHas('channel', fn($q) => $q->where('type', 'ecommerce'));
        }

        public function scopeNotCancelled($query)
        {
            return $query->where('status_order_id', '!=', 5);
        }

        public function reviews()
        {
            return $this->hasMany(\App\Models\Tenant\ProductReview::class);
        }

        /**
         * Retorna un standar de nomenclatura para el modelo
         *
         * @return array
         */
        public function getCollectionData()
        {
            $data = [
                'id' => $this->id,
                'external_id' => $this->external_id,
                'number_document' => $this->number_document,
                'order_id' => str_pad($this->id, 6, "0", STR_PAD_LEFT),
                'customer' => $this->customer->apellidos_y_nombres_o_razon_social,
                'customer_email' => $this->customer->correo_electronico,
                'customer_telefono' => $this->customer->telefono,
                'customer_direccion' => $this->customer->direccion,
                'items' => $this->items,
                'total' => $this->total,
                'reference_payment' => strtoupper($this->reference_payment),
                'document_external_id' => $this->document_external_id,
                'created_at' => $this->created_at->format('Y-m-d'),
                'status_order_id' => $this->status_order_id,
                'purchase' => $this->purchase,
                'status_order_description' => $this->status_order->description ?? null,
                'points_earned'   => (float) $this->points_earned,
                'points_redeemed' => (float) $this->points_redeemed,
            ];

            return $data;
        }
    }
