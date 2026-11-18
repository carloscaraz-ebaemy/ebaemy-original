<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceListing;
use Illuminate\Support\Facades\DB;

/**
 * Las herramientas del asistente de compras del marketplace.
 *
 * Separadas del cliente de Claude para poder probarlas sin red: el asistente
 * solo puede saber lo que estas funciones le devuelven, así que aquí está la
 * frontera de lo que puede decir. Todas son de SOLO LECTURA y solo leen lo
 * que ya es público (fichas publicadas) o lo del comprador que ha entrado.
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

    public function __construct(private ?int $buyerId = null)
    {
    }

    /**
     * Definición de las herramientas para la API, en el formato del SDK de
     * PHP (claves camelCase: `inputSchema`).
     */
    public static function definitions(bool $conPedidos): array
    {
        $tools = [
            [
                'name'        => 'buscar_productos',
                'description' => 'Busca productos publicados en el marketplace ebaemy.com. Devuelve hasta 6 con '
                    . 'título, precio en soles, tienda, stock y enlace. Úsala siempre antes de recomendar o '
                    . 'mencionar un producto, precio o disponibilidad. La consulta son 1 a 4 palabras clave del '
                    . 'producto (ej. "maceta ceramica", "polo rojo"), sin frases ni palabras como "quiero" o "barato".',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'consulta'   => ['type' => 'string', 'description' => 'Palabras clave del producto.'],
                        'precio_max' => ['type' => 'number', 'description' => 'Precio máximo en soles, si el comprador lo dio.'],
                        'precio_min' => ['type' => 'number', 'description' => 'Precio mínimo en soles, si el comprador lo dio.'],
                    ],
                    'required'   => ['consulta'],
                ],
            ],
            [
                'name'        => 'ver_producto',
                'description' => 'Detalle de un producto ya encontrado con buscar_productos: variantes (colores, '
                    . 'tallas) con su stock, envío de la tienda y descripción. Recibe el "slug" que devolvió la búsqueda.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'El slug del producto.'],
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
                    isset($input['precio_max']) ? (float) $input['precio_max'] : null
                ),
                'ver_producto' => $this->verProducto((string) ($input['slug'] ?? '')),
                'mis_pedidos'  => $this->misPedidos(),
                default        => ['error' => 'Herramienta desconocida.'],
            };
        } catch (\Throwable $e) {
            \Log::warning('Asistente marketplace: herramienta falló', ['tool' => $nombre, 'e' => $e->getMessage()]);
            $resultado = ['error' => 'No se pudo consultar ahora mismo.'];
        }

        return json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Los productos que salieron en las herramientas, sin repetir, en orden. */
    public function productos(): array
    {
        return array_values($this->productos);
    }

    public function buscar(string $consulta, ?float $min = null, ?float $max = null): array
    {
        $consulta = mb_substr(trim($consulta), 0, 80);
        if ($consulta === '') {
            return ['error' => 'Falta qué buscar.'];
        }

        $relevancia = MarketplaceListing::textRelevanceSql($consulta);

        // Igual que el listado: primero la busqueda exacta y, si no trae nada,
        // la FLEXIBLE (al menos la mitad de las palabras). `parecidos` le
        // dice al modelo que no son exactos, para que no los venda como tales.
        $buscar = function (bool $flexible) use ($consulta, $min, $max, $relevancia) {
            $q = MarketplaceListing::published();
            if ($flexible) {
                $q->searchLoose($consulta);
                $q->orderByRaw(MarketplaceListing::matchCountSql($consulta) . ' DESC');
            } else {
                $q->search($consulta);
            }
            if ($min !== null) $q->whereRaw('COALESCE(mp_price, price) >= ?', [$min]);
            if ($max !== null) $q->whereRaw('COALESCE(mp_price, price) <= ?', [$max]);
            if ($relevancia) $q->orderByRaw("{$relevancia} DESC");

            return $q->orderByRaw('CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until > NOW()) THEN 1 ELSE 0 END DESC')
                ->orderByDesc('view_count')
                ->limit(self::MAX_RESULTADOS)
                ->get();
        };

        $filas = $buscar(false);
        $parecidos = false;
        if ($filas->isEmpty() && MarketplaceListing::looseMinMatches($consulta) !== null) {
            $filas = $buscar(true);
            $parecidos = $filas->isNotEmpty();
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

        $variantes = \App\Models\System\MarketplaceListingVariant::where('listing_id', $l->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')->orderBy('id')
            ->limit(30)
            ->get(['display_name', 'price', 'stock'])
            ->map(fn ($v) => [
                'nombre' => $v->display_name,
                'precio' => $v->price !== null ? (float) $v->price : null,
                'stock'  => (int) $v->stock,
            ])->all();

        return $this->resumen($l) + [
            'descripcion' => $l->description ? mb_substr(strip_tags((string) $l->description), 0, 600) : null,
            'categoria'   => $l->category_name,
            'marca'       => $l->brand_name,
            'envio'       => $l->shipping ?: 'A coordinar con la tienda',
            'variantes'   => $variantes,
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
        $precio = $l->display_price;

        $this->productos[$l->slug] = [
            'slug'        => $l->slug,
            'title'       => $l->title,
            'image_url'   => $l->thumb_image_url ?: $l->image_url,
            'price'       => $precio,
            'tenant_name' => $l->tenant_name,
            'url'         => $l->public_url,
        ];

        return [
            'slug'       => $l->slug,
            'titulo'     => $l->title,
            'precio'     => $precio,
            'en_oferta'  => (bool) $l->is_on_offer,
            'tienda'     => $l->tenant_name,
            'stock'      => (int) $l->stock,
            'variantes'  => (bool) $l->has_variants,
            'enlace'     => $l->public_url,
        ];
    }
}
