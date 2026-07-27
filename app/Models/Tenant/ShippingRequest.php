<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Envío registrado en el módulo "Registro y Control de Envíos".
 *
 * @property int $id
 * @property string|null $shipment_code
 * @property int|null $order_id
 * @property string $full_name
 * @property string|null $dni
 * @property string|null $phone
 * @property string|null $shipping_destination
 * @property string|null $destination_city
 * @property string|null $shipping_agency
 * @property string|null $tracking_number
 * @property string|null $shipping_guide_path
 * @property string|null $observation
 * @property string $status
 * @property bool $accepted_terms
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int|null $created_by
 */
class ShippingRequest extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_requests';

    protected $fillable = [
        'shipment_code',
        'order_id',
        'delivery_type',
        'full_name',
        'dni',
        'document_type',
        'phone',
        'shipping_destination',
        'reference',
        'destination_city',
        'department_id',
        'province_id',
        'district_id',
        'shipping_agency',
        // Google Maps (solo entregas a domicilio / motorizado)
        'latitude',
        'longitude',
        'google_place_id',
        'google_maps_url',
        'formatted_address',
        'distance_km',
        'distance_text',
        'duration_text',
        'delivery_price',
        'payment_confirmed',
        'payment_confirmed_at',
        'payment_note',
        'courier_name',
        'courier_phone',
        'package_content',
        'package_count',
        'weight',
        'notes',
        'tracking_number',
        'shipping_guide_path',
        'observation',
        'status',
        'accepted_terms',
        'sent_at',
        'created_by',
        // Arquitectura logística (lotes, prioridad, anulación/restauración)
        'print_batch_id',
        'priority',
        'printed_at',
        'print_count',
        'ready_at',
        'cancelled_at',
        'cancelled_by',
        'cancelled_by_name',
        'cancel_reason',
        'status_before_cancel',
        'restored_at',
        'restored_by',
        'restored_by_name',
        'restore_reason',
        'picked_up_at',
        'picked_up_by',
    ];

    protected $casts = [
        'accepted_terms' => 'boolean',
        'sent_at'        => 'datetime',
        'order_id'       => 'integer',
        'created_by'     => 'integer',
        'package_count'  => 'integer',
        'weight'         => 'decimal:2',
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'distance_km'    => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'payment_confirmed'    => 'boolean',
        'payment_confirmed_at' => 'datetime',
        'print_batch_id'       => 'integer',
        'priority'             => 'integer',
        'print_count'          => 'integer',
        'printed_at'           => 'datetime',
        'ready_at'             => 'datetime',
        'cancelled_at'         => 'datetime',
        'cancelled_by'         => 'integer',
        'restored_at'          => 'datetime',
        'restored_by'          => 'integer',
        'picked_up_at'         => 'datetime',
    ];

    // ── Relaciones logísticas ──────────────────────────────────────────────

    public function printBatch()
    {
        return $this->belongsTo(ShippingPrintBatch::class, 'print_batch_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(ShippingAuditLog::class, 'shipment_id')->latest('id');
    }

    public function printEvents()
    {
        return $this->hasMany(ShippingPrintEvent::class, 'shipment_id')->orderBy('id');
    }

    // ── Reglas de lote y modalidad ─────────────────────────────────────────

    /**
     * ¿El envío está bloqueado por pertenecer a un lote YA IMPRESO?
     * Un lote abierto todavía admite cambios.
     */
    public function isLockedByBatch(): bool
    {
        if (!$this->print_batch_id) {
            return false;
        }

        $batch = $this->relationLoaded('printBatch') ? $this->printBatch : $this->printBatch()->first();

        return $batch ? $batch->isPrinted() : false;
    }

    /**
     * ¿Se puede cambiar la modalidad ahora?
     * @return array{0: bool, 1: string|null} [permitido, motivo del bloqueo]
     */
    public function canChangeModality(): array
    {
        if ($this->status === self::STATUS_ANULADO) {
            return [false, 'El envío está anulado. Restáuralo antes de cambiar su modalidad.'];
        }

        if ($this->isLockedByBatch()) {
            return [false, 'Este pedido ya pertenece a un lote de impresión. '
                . 'Para modificar su modalidad deberá retirarlo del lote o generar un nuevo lote.'];
        }

        return [true, null];
    }

    /** ¿Está listo para entrar a un lote de impresión? */
    public function isReadyToPrint(): bool
    {
        return !$this->print_batch_id
            && $this->status !== self::STATUS_ANULADO
            && $this->needsShippingLabel();
    }

    /** Etiqueta del lote para la tabla ("Sin lote" / "LOTE-000125"). */
    public function getBatchLabelAttribute(): string
    {
        if (!$this->print_batch_id) {
            return 'Sin lote';
        }

        return optional($this->printBatch)->code ?? ('Lote #' . $this->print_batch_id);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? self::PRIORITY_LABELS[3];
    }

    /**
     * Distancia en línea recta (haversine) entre dos coordenadas, en km.
     * Sirve de fallback cuando no hay distancia de manejo de Google.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371; // radio terrestre km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    // ── Modalidad de entrega ───────────────────────────────────────────────
    // Los VALORES de BD se conservan ('domicilio', 'agencia') porque hay
    // envíos vivos, rótulos, QR y seguimiento público apoyados en ellos;
    // lo que cambió con el rediseño logístico son las etiquetas. Los alias
    // LIMA/PROVINCIA existen para que el código nuevo se lea por su
    // significado sin migrar datos.

    /** Envío local Lima/Callao — motorizado o courier propio. */
    public const DELIVERY_DOMICILIO = 'domicilio';
    public const DELIVERY_LIMA      = self::DELIVERY_DOMICILIO;

    /** Envío a provincia — requiere agencia de transporte y guía. */
    public const DELIVERY_AGENCIA   = 'agencia';
    public const DELIVERY_PROVINCIA = self::DELIVERY_AGENCIA;

    /** Recojo en tienda — el cliente pasa por su pedido. */
    public const DELIVERY_TIENDA    = 'tienda';

    public const DELIVERY_TYPES = [
        self::DELIVERY_AGENCIA   => 'Envío a Provincia',
        self::DELIVERY_DOMICILIO => 'Envío Local (Lima / Callao)',
        self::DELIVERY_TIENDA    => 'Recojo en Tienda',
    ];

    /** Etiqueta corta para chips y rótulos. */
    public const DELIVERY_SHORT = [
        self::DELIVERY_AGENCIA   => 'Provincia',
        self::DELIVERY_DOMICILIO => 'Lima',
        self::DELIVERY_TIENDA    => 'Recojo',
    ];

    /** Color e ícono por modalidad (azul provincia, naranja Lima, verde recojo). */
    public const DELIVERY_META = [
        self::DELIVERY_AGENCIA   => ['color' => '#2563eb', 'bg' => '#eff6ff', 'line' => '#bfdbfe', 'icon' => '🔵', 'emoji' => '🚚'],
        self::DELIVERY_DOMICILIO => ['color' => '#ea580c', 'bg' => '#fff7ed', 'line' => '#fed7aa', 'icon' => '🟠', 'emoji' => '🏍️'],
        self::DELIVERY_TIENDA    => ['color' => '#16a34a', 'bg' => '#f0fdf4', 'line' => '#bbf7d0', 'icon' => '🟢', 'emoji' => '🏬'],
    ];

    // ── Prioridad logística (se deriva de la modalidad) ────────────────────
    // 1 Lima      → despacho el mismo día o al siguiente.
    // 2 Recojo    → preparar cuanto antes y avisar que está listo.
    // 3 Provincia → dentro del plazo operativo (max_business_days).
    public const PRIORITY_BY_DELIVERY = [
        self::DELIVERY_DOMICILIO => 1,
        self::DELIVERY_TIENDA    => 2,
        self::DELIVERY_AGENCIA   => 3,
    ];

    public const PRIORITY_LABELS = [
        1 => 'Prioridad 1 · Lima',
        2 => 'Prioridad 2 · Recojo en tienda',
        3 => 'Prioridad 3 · Provincia',
    ];

    /** Prioridad que corresponde a una modalidad. */
    public static function priorityFor(?string $deliveryType): int
    {
        return self::PRIORITY_BY_DELIVERY[$deliveryType] ?? 3;
    }

    public function getDeliveryLabelAttribute(): string
    {
        return self::DELIVERY_TYPES[$this->delivery_type] ?? 'Envío';
    }

    public function getDeliveryShortAttribute(): string
    {
        return self::DELIVERY_SHORT[$this->delivery_type] ?? 'Envío';
    }

    public function getDeliveryMetaAttribute(): array
    {
        return self::DELIVERY_META[$this->delivery_type] ?? self::DELIVERY_META[self::DELIVERY_AGENCIA];
    }

    /** ¿El cliente recoge en tienda? No lleva agencia, guía ni manifiesto. */
    public function getIsPickupAttribute(): bool
    {
        return $this->delivery_type === self::DELIVERY_TIENDA;
    }

    /** ¿Genera rótulo de transporte? El recojo en tienda no. */
    public function needsShippingLabel(): bool
    {
        return !$this->is_pickup;
    }

    /** ¿Entra en el manifiesto de despacho? El recojo en tienda no se despacha. */
    public function entersManifest(): bool
    {
        return !$this->is_pickup;
    }

    // ── Estados del paquete (flujo completo) ───────────────────────────────
    public const STATUS_RECIBIDO   = 'recibido';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_PREPARANDO = 'preparando';
    public const STATUS_EMBALANDO  = 'embalando';
    public const STATUS_DESPACHADO = 'despachado';
    public const STATUS_EN_AGENCIA = 'en_agencia';
    public const STATUS_EN_RUTA    = 'en_ruta';
    public const STATUS_ENTREGADO  = 'entregado';
    public const STATUS_ANULADO    = 'anulado';
    // Estados exclusivos de la entrega a domicilio (motorizado).
    public const STATUS_ASIGNADO   = 'asignado_motorizado';
    public const STATUS_EN_CAMINO  = 'en_camino';
    /** Rótulo impreso (lo marca el cierre del lote de impresión). */
    public const STATUS_IMPRESO    = 'impreso';
    /** Recojo en tienda: preparado y esperando al cliente. */
    public const STATUS_LISTO_RECOJO = 'listo_recojo';

    public const STATUSES = [
        self::STATUS_RECIBIDO     => 'Pendiente de revisión',
        self::STATUS_CONFIRMADO   => 'Pedido confirmado',
        self::STATUS_PREPARANDO   => 'Preparando pedido',
        self::STATUS_IMPRESO      => 'Rótulo impreso',
        self::STATUS_EMBALANDO    => 'Empacado',
        self::STATUS_LISTO_RECOJO => 'Listo para recojo',
        self::STATUS_ASIGNADO     => 'Asignado a motorizado',
        self::STATUS_EN_CAMINO    => 'Motorizado en camino',
        self::STATUS_DESPACHADO   => 'Despachado',
        self::STATUS_EN_AGENCIA   => 'Entregado a agencia',
        self::STATUS_EN_RUTA      => 'En tránsito',
        self::STATUS_ENTREGADO    => 'Entregado',
        self::STATUS_ANULADO      => 'Anulado',
    ];

    /**
     * Secuencia del flujo por TIPO de entrega (para la línea de tiempo).
     * Cada tipo tiene su propio recorrido de estados.
     */
    public const STATUS_FLOWS = [
        // Lima: se rotula, se empaca y sale el motorizado.
        self::DELIVERY_DOMICILIO => [
            self::STATUS_RECIBIDO, self::STATUS_PREPARANDO, self::STATUS_IMPRESO,
            self::STATUS_EMBALANDO, self::STATUS_EN_CAMINO, self::STATUS_ENTREGADO,
        ],
        // Provincia: el trabajo de la tienda termina al dejarlo en la agencia.
        self::DELIVERY_AGENCIA => [
            self::STATUS_RECIBIDO, self::STATUS_PREPARANDO, self::STATUS_IMPRESO,
            self::STATUS_EMBALANDO, self::STATUS_DESPACHADO, self::STATUS_EN_AGENCIA,
            self::STATUS_ENTREGADO,
        ],
        // Recojo en tienda: sin rótulo de transporte ni despacho.
        self::DELIVERY_TIENDA => [
            self::STATUS_RECIBIDO, self::STATUS_PREPARANDO,
            self::STATUS_LISTO_RECOJO, self::STATUS_ENTREGADO,
        ],
    ];

    /** Secuencia por defecto (provincia) — se mantiene por compatibilidad. */
    public const STATUS_ORDER = self::STATUS_FLOWS[self::DELIVERY_AGENCIA];

    /**
     * Secuencia de estados según la modalidad del envío.
     * @return string[]
     */
    public static function statusOrderFor(?string $deliveryType): array
    {
        return self::STATUS_FLOWS[$deliveryType] ?? self::STATUS_FLOWS[self::DELIVERY_AGENCIA];
    }

    /**
     * Estados elegibles desde el panel para este envío.
     *
     * Si el envío está en un estado que ya no forma parte del flujo de su
     * modalidad (legados como 'confirmado' o 'asignado_motorizado', o porque
     * le cambiaron la modalidad), se incluye igual para que el selector no
     * aparezca vacío ni pierda el valor actual.
     */
    public function selectableStatuses(): array
    {
        $flow = self::statusOrderFor($this->delivery_type);

        if ($this->status && !in_array($this->status, $flow, true)
            && $this->status !== self::STATUS_ANULADO) {
            array_unshift($flow, $this->status);
        }

        return array_values(array_unique($flow));
    }

    /** Etiquetas de valores legados (compatibilidad con envíos previos a Fase 2). */
    public const LEGACY_LABELS = [
        'pendiente' => 'Registro recibido',
        'listo'     => 'Embalando',
        'enviado'   => 'Entregado a agencia',
    ];

    /**
     * Mensaje de WhatsApp por estado (o null si ese estado no notifica).
     * El tipo de entrega ajusta el texto (motorizado vs agencia).
     */
    public static function statusWhatsappMessage(string $status, ?string $deliveryType = null): ?string
    {
        // El recojo en tienda tiene su propio guion: no se despacha.
        if ($deliveryType === self::DELIVERY_TIENDA) {
            return [
                self::STATUS_CONFIRMADO   => 'Tu pedido fue *confirmado*. Te avisamos cuando esté listo para recoger. ✅',
                self::STATUS_PREPARANDO   => 'Estamos *preparando* tu pedido para que lo recojas. 📦',
                self::STATUS_LISTO_RECOJO => '¡Tu pedido ya está *listo para recoger* en la tienda! 🏬 Acércate con tu documento.',
                self::STATUS_ENTREGADO    => 'Tu pedido fue *entregado*. ¡Gracias por tu compra! 🎉',
            ][$status] ?? null;
        }

        $map = [
            self::STATUS_CONFIRMADO => 'Tu pedido fue *confirmado*. En breve lo prepararemos. ✅',
            self::STATUS_PREPARANDO => 'Estamos *preparando* tu pedido. 📦',
            self::STATUS_ASIGNADO   => 'Tu pedido fue *asignado a un motorizado* y saldrá pronto. 🏍️',
            self::STATUS_EN_CAMINO  => 'Nuestro *motorizado está en camino* a tu dirección. 🏍️💨',
            self::STATUS_EMBALANDO  => 'Tu pedido ya fue *embalado*. 📦✅',
            self::STATUS_DESPACHADO => 'Tu pedido fue *despachado*. 🚚',
            self::STATUS_EN_AGENCIA => 'Tu pedido fue *entregado a la agencia*. 🏢',
            self::STATUS_EN_RUTA    => 'Tu pedido se encuentra *en tránsito*. 🛣️',
            self::STATUS_ENTREGADO  => 'Tu pedido fue *entregado correctamente*. 🎉',
        ];
        return $map[$status] ?? null;
    }

    /** Etiqueta legible del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? self::LEGACY_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** ¿Ya tiene la guía de envío cargada? */
    public function getHasGuideAttribute(): bool
    {
        return !empty($this->shipping_guide_path);
    }

    /** ¿Está anulado? */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    /** Tipos de documento aceptados en el registro. */
    public const DOC_TYPES = [
        'dni'       => 'DNI / RUC',
        'ce'        => 'C. Extranjería',
        'pasaporte' => 'Pasaporte',
    ];

    /**
     * Etiqueta corta del documento (para rótulo, panel y WhatsApp).
     * DNI y RUC son una sola opción en el formulario porque se distinguen por
     * la cantidad de dígitos, igual que la consulta a RENIEC/SUNAT.
     */
    public function getDocumentLabelAttribute(): string
    {
        $t = $this->document_type;
        if ($t === null || $t === '' || $t === 'dni' || $t === 'ruc') {
            $n = preg_replace('/\D+/', '', (string) $this->dni);
            return strlen($n) === 11 ? 'RUC' : 'DNI';
        }

        return self::DOC_TYPES[$t] ?? 'Doc.';
    }

    /** Valor que debe quedar marcado en el selector (normaliza el legacy 'ruc'). */
    public function getDocumentOptionAttribute(): string
    {
        $t = $this->document_type;

        return isset(self::DOC_TYPES[$t]) ? $t : 'dni';
    }

    /** ¿El pago del envío ya fue confirmado por el encargado? */
    public function getIsPaidAttribute(): bool
    {
        return (bool) $this->payment_confirmed;
    }

    /** ¿Es una entrega a domicilio (motorizado con Google Maps)? */
    public function getIsDomicilioAttribute(): bool
    {
        return $this->delivery_type === self::DELIVERY_DOMICILIO;
    }

    /** Etiqueta legible del tipo de entrega. */
    public function getDeliveryTypeLabelAttribute(): string
    {
        return self::DELIVERY_TYPES[$this->delivery_type] ?? 'Envío por agencia';
    }

    /** ¿Tiene coordenadas geográficas (para el mapa/motorizado)? */
    public function getHasCoordsAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Enlace a Google Maps para ver la ubicación (URL guardada o construida). */
    public function getMapsLinkAttribute(): ?string
    {
        if ($this->google_maps_url) {
            return $this->google_maps_url;
        }
        if ($this->has_coords) {
            return 'https://www.google.com/maps/search/?api=1&query=' . $this->latitude . ',' . $this->longitude;
        }
        return null;
    }

    /** Enlace de NAVEGACIÓN para el motorizado (abre la ruta en Google Maps). */
    public function getCourierDirectionsUrlAttribute(): ?string
    {
        if (!$this->has_coords) {
            return null;
        }
        return 'https://www.google.com/maps/dir/?api=1&destination=' . $this->latitude . ',' . $this->longitude;
    }

    // ── Scopes por tipo de entrega ────────────────────────────────────────
    public function scopeDomicilio($q)
    {
        return $q->where('delivery_type', self::DELIVERY_DOMICILIO);
    }

    public function scopeAgencia($q)
    {
        return $q->where('delivery_type', self::DELIVERY_AGENCIA);
    }

    /** Envíos a domicilio activos (para el tablero del motorizado). */
    public function scopeCourierActive($q)
    {
        return $q->domicilio()->whereIn('status', [
            self::STATUS_RECIBIDO, self::STATUS_EMBALANDO, self::STATUS_EN_CAMINO,
            // legados, por si quedan envíos con el flujo anterior
            self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO, self::STATUS_ASIGNADO,
        ]);
    }

    // ── Antigüedad / prioridad por días hábiles ───────────────────────────
    /**
     * Feriados nacionales de Perú que NO cuentan como día hábil. Fechas fijas +
     * Jueves/Viernes Santo (movibles, precalculados). Mantener al día por año.
     */
    public const HOLIDAYS = [
        // 2026 (Semana Santa: Pascua 5-abr → Jue/Vie Santo 2 y 3 de abril)
        '2026-01-01', '2026-04-02', '2026-04-03', '2026-05-01', '2026-06-07',
        '2026-06-29', '2026-07-23', '2026-07-28', '2026-07-29', '2026-08-06',
        '2026-08-30', '2026-10-08', '2026-11-01', '2026-12-08', '2026-12-09',
        '2026-12-25',
        // 2027 (Semana Santa: Pascua 28-mar → Jue/Vie Santo 25 y 26 de marzo)
        '2027-01-01', '2027-03-25', '2027-03-26', '2027-05-01', '2027-06-07',
        '2027-06-29', '2027-07-23', '2027-07-28', '2027-07-29', '2027-08-06',
        '2027-08-30', '2027-10-08', '2027-11-01', '2027-12-08', '2027-12-09',
        '2027-12-25',
    ];

    /**
     * Estados en los que el reloj de atención YA se detuvo: el paquete salió de
     * la tienda o el caso se cerró. No se les calcula antigüedad (no "vencen").
     */
    public const CLOSED_STATUSES = [
        self::STATUS_DESPACHADO, self::STATUS_EN_AGENCIA, self::STATUS_EN_RUTA,
        self::STATUS_EN_CAMINO, self::STATUS_ENTREGADO, self::STATUS_ANULADO,
        // Recojo en tienda: el trabajo de la tienda termina cuando el pedido
        // queda listo; a partir de ahí la pelota la tiene el cliente.
        self::STATUS_LISTO_RECOJO,
        'enviado', // legado = entregado a agencia
    ];

    /** Metadatos visuales por nivel de antigüedad (0 verde … 3 rojo). */
    public const AGING_META = [
        0 => ['key' => 'verde',    'color' => '#15803d', 'bg' => '#dcfce7', 'label' => 'En plazo'],
        1 => ['key' => 'amarillo', 'color' => '#a16207', 'bg' => '#fef9c3', 'label' => 'Por vencer'],
        2 => ['key' => 'naranja',  'color' => '#c2410c', 'bg' => '#ffedd5', 'label' => 'Urgente'],
        3 => ['key' => 'rojo',     'color' => '#b91c1c', 'bg' => '#fee2e2', 'label' => 'Vencido'],
    ];

    /** ¿El reloj de atención ya se detuvo para este envío? */
    public function isClosedForAging(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Días HÁBILES en el intervalo (start, end]: cuenta los días laborables
     * posteriores a $start hasta $end inclusive (sáb/dom fuera; feriados fuera
     * si $skipHolidays). Primitiva única para el badge y el filtro (evita
     * off-by-one entre ambos).
     */
    public static function businessDaysBetween(\Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end, bool $skipHolidays = true): int
    {
        $s = $start->copy()->startOfDay();
        $e = $end->copy()->startOfDay();
        if ($e->lessThanOrEqualTo($s)) {
            return 0;
        }
        $holidays = $skipHolidays ? array_flip(self::HOLIDAYS) : [];
        $days = 0;
        $cursor = $s->copy();
        while ($cursor->lessThan($e)) {
            $cursor->addDay();
            if ($cursor->isWeekend()) {
                continue;
            }
            if (isset($holidays[$cursor->toDateString()])) {
                continue;
            }
            $days++;
        }
        return $days;
    }

    /**
     * Días HÁBILES transcurridos desde el registro hasta hoy. El día de
     * registro cuenta como 0.
     */
    public function businessDaysElapsed(bool $skipHolidays = true, ?\Illuminate\Support\Carbon $now = null): int
    {
        if (!$this->created_at) {
            return 0;
        }
        return self::businessDaysBetween($this->created_at, $now ?? now(), $skipHolidays);
    }

    /**
     * Estado de antigüedad del envío según el plazo máximo (en días hábiles).
     * Devuelve ['open'=>bool, 'days'=>?int, 'level'=>?int(0-3)]. Cerrado → level null.
     */
    public function aging(int $maxDays = 4, bool $skipHolidays = true): array
    {
        if ($this->isClosedForAging()) {
            return ['open' => false, 'days' => null, 'level' => null];
        }
        $max = $maxDays >= 1 ? $maxDays : 4;
        $d = $this->businessDaysElapsed($skipHolidays);
        // Bandas: verde ≤ max-3 · amarillo = max-2 · naranja = max-1 · rojo ≥ max.
        $level = $d >= $max ? 3 : ($d >= $max - 1 ? 2 : ($d >= $max - 2 ? 1 : 0));
        return ['open' => true, 'days' => $d, 'level' => $level];
    }

    /**
     * Fecha de corte para filtrar por antigüedad: la fecha de registro MÁS
     * RECIENTE que ya acumula ≥ $k días hábiles a hoy. Filtrar
     * `created_at ≤ corte` ⟺ badge con nivel de ese umbral o mayor. Camina
     * hacia atrás usando la MISMA primitiva del badge, así nunca se desalinean
     * (fines de semana / feriados / hoy no hábil incluidos).
     */
    public static function agingCutoff(int $k, bool $skipHolidays = true, ?\Illuminate\Support\Carbon $today = null): \Illuminate\Support\Carbon
    {
        $end = ($today ?? now())->copy()->startOfDay();
        $d = $end->copy();
        $guard = 0;
        while (self::businessDaysBetween($d, $end, $skipHolidays) < max(1, $k) && $guard++ < 400) {
            $d->subDay();
        }
        return $d;
    }

    // ── Scopes para los filtros del panel ─────────────────────────────────
    public function scopeWithoutGuide($q)
    {
        // Los anulados no cuentan como "pendientes de guía".
        return $q->whereNull('shipping_guide_path')
                 ->where('status', '!=', self::STATUS_ANULADO);
    }

    public function scopeWithGuide($q)
    {
        return $q->whereNotNull('shipping_guide_path');
    }

    public function scopePending($q)
    {
        // "Pendientes" = aún no salió a la agencia (incluye valores legados).
        return $q->whereIn('status', [
            self::STATUS_RECIBIDO, self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO,
            self::STATUS_EMBALANDO, self::STATUS_DESPACHADO, 'pendiente', 'listo',
        ]);
    }

    public function scopeSentToday($q)
    {
        // "Enviados hoy" = entregados a la agencia / en ruta / entregados hoy.
        return $q->whereIn('status', [self::STATUS_EN_AGENCIA, self::STATUS_EN_RUTA, self::STATUS_ENTREGADO, 'enviado'])
                 ->whereDate('sent_at', now()->toDateString());
    }

    /** Agencias de envío frecuentes en Perú (para el desplegable del form). */
    public const AGENCIES = [
        'Shalom', 'Olva Courier', 'Marvisur', 'Cruz del Sur Cargo', 'Móvil Tours',
        'Tepsa', 'Civa', 'Flores', 'Transportes Línea', 'GH Bus', 'Ittsa', 'Oltursa',
    ];

    /**
     * Genera el código legible del envío: ENV-AAAAMMDD-000015 (fecha + id).
     * El número final es el id, garantizando unicidad global.
     */
    public static function buildCode(int $id, ?string $date = null): string
    {
        $date = $date ?: now()->format('Ymd');
        return 'ENV-' . $date . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
