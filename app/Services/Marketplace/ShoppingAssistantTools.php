<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceListingVariant;
use App\Services\System\MarketplaceCartService;
use Illuminate\Support\Facades\DB;

/**
 * Las herramientas del asistente de compras del marketplace.
 *
 * Separadas del cliente de Claude para poder probarlas sin red: el asistente
 * solo puede saber lo que estas funciones le devuelven, así que aquí está la
 * frontera de lo que puede decir. Leen solo lo público (fichas publicadas) o
 * lo del comprador que ha entrado. La ÚNICA que escribe es agregar_al_carrito,
 * y escribe en el carrito de sesión de ese mismo navegador: lo mismo que el
 * botón de la ficha.
 *
 * La búsqueda usa el MISMO filtro y orden que el buscador de la página
 * (scopeSearch + textRelevanceSql): si el buscador mejora, el bot también.
 */
class ShoppingAssistantTools
{
    /** Tope de productos por búsqueda: el chat los pinta como tarjetas. */
    private const MAX_RESULTADOS = 6;

    /** Productos devueltos en esta conversación, para pintarlos como tarjetas. */
    private array $productos = [];

    /** Resumen de lo que se llamó: [{name, input, total}] para el registro. */
    private array $llamadas = [];

    /** Si alguna búsqueda terminó sin nada (métrica de «no encontró»). */
    private bool $sinResultados = false;

    /** Lo que pasó en el carrito, para que el navegador refresque el mini carrito. */
    private ?array $carrito = null;

    public function __construct(private ?int $buyerId = null)
    {
    }

    /**
     * Definición de las herramientas para la API, en el formato del SDK de
     * PHP (claves camelCase: `inputSchema`). El orden es fijo: forma parte
     * del prefijo cacheado.
     */
    public static function definitions(bool $conPedidos): array
    {
        $tools = [
            [
                'name'        => 'buscar_productos',
                'description' => 'Busca productos publicados en el marketplace ebaemy.com. Devuelve hasta 6 con '
                    . 'título, precio en soles, tienda, stock y enlace. Úsala siempre antes de recomendar o '
                    . 'mencionar un producto, precio o disponibilidad. La consulta son 1 a 4 palabras clave del '
                    . 'producto (ej. "maceta ceramica", "polo rojo"), sin frases ni palabras como "quiero" o "barato". '
                    . 'Puedes llamarla varias veces en paralelo con palabras distintas.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'consulta'     => ['type' => 'string', 'description' => 'Palabras clave del producto.'],
                        'precio_max'   => ['type' => 'number', 'description' => 'Precio máximo en soles, si el comprador lo dio.'],
                        'precio_min'   => ['type' => 'number', 'description' => 'Precio mínimo en soles, si el comprador lo dio.'],
                        'tienda'       => ['type' => 'string', 'description' => 'Nombre de la tienda, solo si el comprador pide productos de una tienda concreta.'],
                        'solo_ofertas' => ['type' => 'boolean', 'description' => 'true si pide ofertas, descuentos o rebajas.'],
                        'orden'        => ['type' => 'string', 'enum' => ['relevancia', 'precio_asc', 'precio_desc'], 'description' => 'precio_asc si pide lo más barato; precio_desc si pide lo mejor o premium.'],
                    ],
                    'required'   => ['consulta'],
                ],
            ],
            [
                'name'        => 'ver_producto',
                'description' => 'Detalle de un producto ya encontrado con buscar_productos o el que el comprador está viendo: variantes (colores, '
                    . 'tallas) con su stock, envío de la tienda y descripción. Recibe el "slug".',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'El slug del producto.'],
                    ],
                    'required'   => ['slug'],
                ],
            ],
            [
                'name'        => 'costo_envio',
                'description' => 'Costo y plazo de envío de un producto a un destino: Lima/Callao o provincias. Úsala cuando pregunten '
                    . 'cuánto cuesta el envío, si llega a una ciudad o cuánto tarda.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'    => ['type' => 'string', 'description' => 'El slug del producto.'],
                        'destino' => ['type' => 'string', 'enum' => ['lima', 'provincia'], 'description' => 'lima para Lima Metropolitana y Callao; provincia para el resto del Perú.'],
                    ],
                    'required'   => ['slug', 'destino'],
                ],
            ],
            [
                'name'        => 'ofertas_del_dia',
                'description' => 'Los productos en oferta ahora mismo en el marketplace, con su descuento. Úsala si el comprador pide '
                    . 'ofertas sin decir qué producto busca.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'agregar_al_carrito',
                'description' => 'Agrega un producto al carrito del comprador. Úsala SOLO cuando el comprador lo pida de forma explícita '
                    . '(«agrégalo», «lo quiero», «ponlo en el carrito»). Si el producto tiene variantes (talla, color) y el comprador '
                    . 'no eligió una, primero pregúntale cuál con ver_producto; no elijas por él.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug'     => ['type' => 'string', 'description' => 'El slug del producto.'],
                        'cantidad' => ['type' => 'integer', 'description' => 'Unidades; 1 si no dijo.'],
                        'variante' => ['type' => 'string', 'description' => 'Nombre de la variante tal como lo devolvió ver_producto (ej. "Rojo / M"), si el producto tiene variantes.'],
                    ],
                    'required'   => ['slug'],
                ],
            ],
            [
                'name'        => 'contacto_tienda',
                'description' => 'Datos para hablar con la tienda que vende un producto: enlace de WhatsApp (si lo tiene) y página de la tienda. '
                    . 'Úsala cuando el comprador quiera una respuesta que solo la tienda puede dar (personalizar, stock futuro, '
                    . 'un pedido ya hecho) o pida hablar con una persona.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'El slug de un producto de esa tienda.'],
                    ],
                    'required'   => ['slug'],
                ],
            ],
        ];

        if ($conPedidos) {
            $tools[] = [
                'name'        => 'mis_pedidos',
                'description' => 'Los últimos pedidos del comprador que está conectado: tienda, fecha, total y estado.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ];
        }

        return $tools;
    }

    /**
     * Ejecuta una herramienta y devuelve el texto (JSON) para el tool_result.
     * Nunca lanza: un error vuelve como texto para que el modelo lo explique.
     */
    public function run(string $nombre, array $input): string
    {
        try {
            $resultado = match ($nombre) {
                'buscar_productos' => $this->buscar(
                    (string) ($input['consulta'] ?? ''),
                    isset($input['precio_min']) ? (float) $input['precio_min'] : null,
                    isset($input['precio_max']) ? (float) $input['precio_max'] : null,
                    isset($input['tienda']) ? (string) $input['tienda'] : null,
                    !empty($input['solo_ofertas']),
                    (string) ($input['orden'] ?? 'relevancia')
                ),
                'ver_producto'       => $this->verProducto((string) ($input['slug'] ?? '')),
                'costo_envio'        => $this->costoEnvio((string) ($input['slug'] ?? ''), (string) ($input['destino'] ?? 'lima')),
                'ofertas_del_dia'    => $this->ofertas(),
                'agregar_al_carrito' => $this->agregarAlCarrito((string) ($input['slug'] ?? ''), (int) ($input['cantidad'] ?? 1), isset($input['variante']) ? (string) $input['variante'] : null),
                'contacto_tienda'    => $this->contactoTienda((string) ($input['slug'] ?? '')),
                'mis_pedidos'        => $this->misPedidos(),
                default              => ['error' => 'Herramienta desconocida.'],
            };
        } catch (\Throwable $e) {
            \Log::warning('Asistente marketplace: herramienta falló', ['tool' => $nombre, 'e' => $e->getMessage()]);
            $resultado = ['error' => 'No se pudo consultar ahora mismo.'];
        }

        $this->llamadas[] = [
            'name'  => $nombre,
            'input' => $input,
            'total' => isset($resultado['total']) ? (int) $resultado['total'] : (isset($resultado['error']) ? -1 : 1),
        ];

        return json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Texto corto para el indicador «Buscando…» del navegador. */
    public static function statusLabel(string $nombre, array $input): string
    {
        return match ($nombre) {
            'buscar_productos'   => 'Buscando «' . mb_substr((string) ($input['consulta'] ?? ''), 0, 40) . '»…',
            'ver_producto'       => 'Revisando la ficha…',
            'costo_envio'        => 'Consultando el envío…',
            'ofertas_del_dia'    => 'Buscando ofertas…',
            'agregar_al_carrito' => 'Agregando al carrito…',
            'contacto_tienda'    => 'Buscando el contacto de la tienda…',
            'mis_pedidos'        => 'Revisando tus pedidos…',
            default              => 'Consultando…',
        };
    }

    /** Los productos que salieron en las herramientas, sin repetir, en orden. */
    public function productos(): array
    {
        return array_values($this->productos);
    }

    public function llamadas(): array
    {
        return $this->llamadas;
    }

    public function sinResultados(): bool
    {
        return $this->sinResultados;
    }

    public function carrito(): ?array
    {
        return $this->carrito;
    }

    /** Tarjetas para una lista de slugs (al recuperar un historial guardado). */
    public static function cardsFor(array $slugs): array
    {
        if (!$slugs) return [];
        $out = [];
        MarketplaceListing::hasPublicPage()->whereIn('slug', array_slice($slugs, 0, 60))->get()
            ->each(function (MarketplaceListing $l) use (&$out) {
                $out[$l->slug] = static::card($l);
            });
        return $out;
    }

    private static function card(MarketplaceListing $l): array
    {
        return [
            'slug'        => $l->slug,
            'title'       => $l->title,
            'image_url'   => $l->thumb_image_url ?: $l->image_url,
            'price'       => $l->display_price,
            'tenant_name' => $l->tenant_name,
            'url'         => $l->public_url,
            'in_stock'    => (int) $l->stock > 0,
        ];
    }

    // ─────────────────────────── herramientas ───────────────────────────

    public function buscar(string $consulta, ?float $min = null, ?float $max = null, ?string $tienda = null, bool $soloOfertas = false, string $orden = 'relevancia'): array
    {
        $consulta = mb_substr(trim($consulta), 0, 80);
        if ($consulta === '') {
            return ['error' => 'Falta qué buscar.'];
        }

        $relevancia = MarketplaceListing::textRelevanceSql($consulta);

        // Igual que el listado: primero la busqueda exacta y, si no trae nada,
        // la FLEXIBLE (al menos la mitad de las palabras). `parecidos` le
        // dice al modelo que no son exactos, para que no los venda como tales.
        $buscar = function (bool $flexible) use ($consulta, $min, $max, $tienda, $soloOfertas, $orden, $relevancia) {
            $q = MarketplaceListing::published();
            if ($flexible) {
                $q->searchLoose($consulta);
                $q->orderByRaw(MarketplaceListing::matchCountSql($consulta) . ' DESC');
            } else {
                $q->search($consulta);
            }
            if ($min !== null) $q->whereRaw('COALESCE(mp_price, price) >= ?', [$min]);
            if ($max !== null) $q->whereRaw('COALESCE(mp_price, price) <= ?', [$max]);
            if ($tienda) $q->where('tenant_name', 'like', '%' . mb_substr(trim($tienda), 0, 60) . '%');
            if ($soloOfertas) $q->onOffer();

            if ($orden === 'precio_asc') {
                $q->orderByRaw('COALESCE(mp_price, price) ASC');
            } elseif ($orden === 'precio_desc') {
                $q->orderByRaw('COALESCE(mp_price, price) DESC');
            } else {
                if ($relevancia) $q->orderByRaw("{$relevancia} DESC");
                $q->orderByRaw('CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until > NOW()) THEN 1 ELSE 0 END DESC')
                  ->orderByDesc('view_count');
            }

            return $q->limit(self::MAX_RESULTADOS)->get();
        };

        $filas = $buscar(false);
        $parecidos = false;
        if ($filas->isEmpty() && MarketplaceListing::looseMinMatches($consulta) !== null) {
            $filas = $buscar(true);
            $parecidos = $filas->isNotEmpty();
        }

        if ($filas->isEmpty()) {
            $this->sinResultados = true;
        }

        $productos = $filas->map(fn ($l) => $this->resumen($l))->all();

        return [
            'consulta'  => $consulta,
            'total'     => count($productos),
            'productos' => $productos,
        ] + ($parecidos ? ['parecidos' => true, 'nota' => 'Ningun producto tiene todas las palabras; estos son parecidos.'] : []);
    }

    public function verProducto(string $slug): array
    {
        $l = MarketplaceListing::hasPublicPage()->where('slug', $slug)->first();
        if (!$l) {
            return ['error' => 'No existe un producto publicado con ese slug.'];
        }

        $variantes = MarketplaceListingVariant::where('listing_id', $l->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')->orderBy('id')
            ->limit(30)
            ->get(['display_name', 'price', 'stock'])
            ->map(fn ($v) => [
                'nombre' => $v->display_name,
                'precio' => $v->price !== null ? (float) $v->price : null,
                'stock'  => (int) $v->stock,
            ])->all();

        $envio = $l->shipping;
        return $this->resumen($l) + [
            'descripcion' => $l->description ? mb_substr(strip_tags((string) $l->description), 0, 600) : null,
            'categoria'   => $l->category_name,
            'marca'       => $l->brand_name,
            'envio'       => is_array($envio) ? [
                'lima'      => ShippingSummaryText::forZone($envio, 'lima', (float) $l->display_price)['text'],
                'provincia' => ShippingSummaryText::forZone($envio, 'province', (float) $l->display_price)['text'],
            ] : 'A coordinar con la tienda',
            'variantes'   => $variantes,
        ];
    }

    public function costoEnvio(string $slug, string $destino): array
    {
        $l = MarketplaceListing::hasPublicPage()->where('slug', $slug)->first();
        if (!$l) {
            return ['error' => 'No existe un producto publicado con ese slug.'];
        }
        $zona = $destino === 'provincia' ? 'province' : 'lima';
        $r = ShippingSummaryText::forZone(is_array($l->shipping) ? $l->shipping : null, $zona, (float) $l->display_price);

        return [
            'producto'       => $l->title,
            'tienda'         => $l->tenant_name,
            'destino'        => $destino === 'provincia' ? 'Provincias' : 'Lima y Callao',
            'costo'          => $r['text'],
            'detalle'        => $r['detail'],
            'lo_confirma_la_tienda' => $r['pending'],
            'nota'           => 'El envío se paga a la tienda al coordinar la entrega; no se suma en el carrito.',
        ];
    }

    public function ofertas(): array
    {
        $filas = MarketplaceListing::published()->onOffer()
            ->orderByDesc('discount_pct')->orderByDesc('view_count')
            ->limit(self::MAX_RESULTADOS)->get();

        if ($filas->isEmpty()) {
            $this->sinResultados = true;
        }

        return [
            'total'     => $filas->count(),
            'productos' => $filas->map(fn ($l) => $this->resumen($l) + [
                'precio_antes' => $l->original_price !== null ? (float) $l->original_price : null,
                'descuento'    => $l->discount_pct !== null ? (int) $l->discount_pct . '%' : null,
            ])->all(),
        ];
    }

    public function agregarAlCarrito(string $slug, int $cantidad, ?string $variante): array
    {
        $l = MarketplaceListing::published()->where('slug', $slug)->first();
        if (!$l) {
            return ['error' => 'Ese producto no está disponible para comprar ahora.'];
        }

        $variantId = null;
        if ($l->has_variants) {
            $vars = MarketplaceListingVariant::where('listing_id', $l->id)->where('is_active', true)->where('stock', '>', 0)
                ->get(['tenant_variant_id', 'display_name']);
            if ($vars->count() > 1 && !$variante) {
                return [
                    'error'     => 'El producto tiene variantes; pregunta al comprador cuál quiere antes de agregar.',
                    'variantes' => $vars->pluck('display_name')->all(),
                ];
            }
            $buscada = mb_strtolower(trim((string) $variante));
            $v = $vars->first(fn ($x) => mb_strtolower(trim((string) $x->display_name)) === $buscada)
                ?: $vars->first(fn ($x) => $buscada !== '' && str_contains(mb_strtolower((string) $x->display_name), $buscada))
                ?: ($vars->count() === 1 ? $vars->first() : null);
            if (!$v) {
                return ['error' => 'No encontré esa variante.', 'variantes' => $vars->pluck('display_name')->all()];
            }
            $variantId = (int) $v->tenant_variant_id;
        }

        $cart = app(MarketplaceCartService::class);
        $line = $cart->add($slug, max(1, min(10, $cantidad)), $variantId);
        if ($line === null) {
            return ['error' => 'No se pudo agregar: sin stock o variante no disponible.'];
        }

        $resumen = $cart->summary();
        $this->carrito = [
            'count'    => (int) ($resumen['count'] ?? $resumen['items_count'] ?? 0),
            'cart_url' => url('/marketplace/cart'),
            'slug'     => $slug,
        ];
        $this->resumen($l);

        return [
            'ok'                 => true,
            'producto'           => $l->title,
            'variante'           => $line['variant_name'] ?? $variante,
            'cantidad_en_carrito'=> (int) ($line['quantity'] ?? $cantidad),
            'precio_unitario'    => (float) ($line['price'] ?? $l->display_price),
            'ver_carrito'        => url('/marketplace/cart'),
            'nota'               => 'Ya está en el carrito. Dile que puede seguir buscando o ir al carrito para pagar.',
        ];
    }

    public function contactoTienda(string $slug): array
    {
        $l = MarketplaceListing::hasPublicPage()->where('slug', $slug)->first();
        if (!$l) {
            return ['error' => 'No existe un producto publicado con ese slug.'];
        }
        $tel = preg_replace('/\D+/', '', (string) $l->seller_whatsapp);
        if ($tel !== '' && strlen($tel) === 9) {
            $tel = '51' . $tel;
        }
        $texto = rawurlencode('Hola, vi "' . $l->title . '" en ebaemy.com y tengo una consulta.');

        return [
            'tienda'     => $l->tenant_name,
            'whatsapp'   => $tel !== '' ? "https://wa.me/{$tel}?text={$texto}" : null,
            'pagina'     => $l->store_url,
            'producto'   => $l->public_url,
            'nota'       => $tel !== '' ? 'Dale el enlace de WhatsApp para que escriba a la tienda.' : 'La tienda no tiene WhatsApp publicado; indícale la página de la tienda.',
        ];
    }

    public function misPedidos(): array
    {
        if (!$this->buyerId) {
            return ['error' => 'El comprador no ha iniciado sesión.'];
        }

        $pedidos = DB::connection('system')->table('marketplace_user_orders as o')
            ->leftJoin('hostnames as h', 'h.id', '=', 'o.hostname_id')
            ->where('o.user_id', $this->buyerId)
            ->orderByDesc('o.confirmed_at')->orderByDesc('o.id')
            ->limit(5)
            ->get(['o.order_id', 'o.total', 'o.status', 'o.items_count', 'o.confirmed_at', 'o.cancelled_at', 'h.fqdn'])
            ->map(fn ($o) => [
                'pedido'    => (int) $o->order_id,
                'tienda'    => $o->fqdn ? strtok((string) $o->fqdn, '.') : null,
                'fecha'     => $o->confirmed_at,
                'total'     => (float) $o->total,
                'productos' => (int) $o->items_count,
                'estado'    => $o->cancelled_at ? 'cancelado' : $o->status,
            ])->all();

        return ['pedidos' => $pedidos, 'ver_todos' => url('/marketplace/account/orders')];
    }

    /** Lo que el modelo necesita de una ficha, y la tarjeta para el chat. */
    private function resumen(MarketplaceListing $l): array
    {
        $this->productos[$l->slug] = static::card($l);

        return [
            'slug'       => $l->slug,
            'titulo'     => $l->title,
            'precio'     => $l->display_price,
            'en_oferta'  => (bool) $l->is_on_offer,
            'tienda'     => $l->tenant_name,
            'stock'      => (int) $l->stock,
            'variantes'  => (bool) $l->has_variants,
            'enlace'     => $l->public_url,
        ];
    }
}
