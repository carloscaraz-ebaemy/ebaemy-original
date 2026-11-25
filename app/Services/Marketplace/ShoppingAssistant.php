<?php

namespace App\Services\Marketplace;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\ToolUseBlock;
use App\Models\System\MarketplaceListing;

/**
 * Asistente de compras de ebaemy.com: un chat que entiende lo que el
 * comprador pide con sus palabras («algo para regalar a mi mamá, menos de 80
 * soles») y lo traduce a búsquedas sobre el catálogo real.
 *
 * Claude no ve la base de datos: solo lo que le devuelven las herramientas de
 * ShoppingAssistantTools. Por eso no puede inventar un producto, un precio ni
 * un stock que no estén publicados.
 *
 * Qué sabe además del mensaje:
 *  - La PÁGINA en la que está el comprador (ficha de producto, tienda,
 *    carrito): va en un segundo bloque de system, fuera del prefijo cacheado.
 *  - La conversación la guarda el navegador y llega en cada petición como
 *    texto (sin los bloques de razonamiento, que no se reenvían entre turnos).
 *    Dentro de un turno, el bucle de herramientas devuelve el contenido sin
 *    tocarlo.
 *
 * Qué devuelve además del texto: tarjetas de producto, respuestas rápidas
 * sugeridas (el modelo las escribe en una última línea con un marcador que se
 * recorta), lo que pasó en el carrito, y las métricas del turno que registra
 * ShoppingAssistantLog. El callback $onStatus avisa de cada herramienta para
 * que el navegador muestre «Buscando «cojín»…» mientras espera.
 *
 * Apagado hasta que estén las tres piezas: el paquete `anthropic-ai/sdk`,
 * ANTHROPIC_API_KEY y MARKETPLACE_ASSISTANT_ENABLED=true. Ver isAvailable().
 */
class ShoppingAssistant
{
    /** Rondas de herramientas por mensaje; corta un bucle que no converge. */
    private const MAX_RONDAS = 4;

    /** Turnos previos que se reenvían: bastan para el contexto del chat. */
    private const MAX_HISTORIAL = 10;

    /** Marcador de la última línea con las respuestas rápidas. */
    private const MARCA_SUGERENCIAS = '[[sugerencias]]';

    private const SYSTEM_PROMPT = <<<'TXT'
Eres el asistente de compras de ebaemy.com, un marketplace peruano donde varias tiendas venden sus productos. Ayudas a los compradores a encontrar lo que buscan, a decidirse y a comprar, y respondes dudas sobre cómo funciona ebaemy.

Cómo trabajas:
- Para recomendar o mencionar cualquier producto, precio o disponibilidad, usa primero la herramienta buscar_productos. Solo existe lo que devuelven las herramientas: nunca inventes productos, precios, stock, descuentos ni plazos.
- Traduce lo que pide el comprador a palabras clave de producto. Si pide «algo para regalar a mi mamá», lanza dos o tres búsquedas concretas a la vez (ej. «cartera», «cojin», «maceta»). Si una búsqueda no trae nada, prueba una o dos palabras alternativas antes de rendirte.
- Si da un presupuesto, pásalo en precio_max. Si pide lo más barato, orden precio_asc. Si pide ofertas, solo_ofertas o ofertas_del_dia.
- Los productos que encuentres se muestran solos como tarjetas debajo de tu mensaje, con foto y enlace. No repitas la lista completa: comenta en 1 a 3 frases cuáles encajan mejor y por qué.
- Si pregunta por colores, tallas o detalles de un producto, usa ver_producto. Si pregunta por el envío a su ciudad, costo_envio con lima o provincia.
- Si el comprador dice que lo quiere o pide agregarlo, usa agregar_al_carrito. Si el producto tiene variantes y no eligió una, pregúntale cuál antes. Después de agregar, dile que puede ir al carrito para pagar o seguir buscando.
- Si pide algo que solo la tienda puede responder (personalizar, cuándo repondrán, un pedido ya hecho) o quiere hablar con una persona, usa contacto_tienda y dale el enlace.
- Si no encuentras lo que pide, dilo con claridad y propón una alternativa cercana diciendo que es distinta. No ofrezcas algo distinto como si fuera lo mismo.
- Si el comprador está viendo un producto (te lo indica el contexto), «esto», «este producto» o «lo quiero» se refieren a ese producto.

Cómo funciona comprar en ebaemy:
- El comprador le compra directamente a cada tienda: la tienda cobra, envía y atiende cambios y devoluciones. Puede pagar productos de varias tiendas en un solo carrito.
- El costo y el plazo de envío están en la ficha de cada producto, para Lima y provincias. Si la tienda aún no los indicó, dice «A coordinar con la tienda». El envío se paga a la tienda al coordinar la entrega; no se suma en el carrito.
- Garantía en todas las tiendas: si el producto llega dañado, con falla o no es lo que se pidió, hay 7 días calendario desde que se recibe para pedir cambio o devolución del dinero, sin pagar el envío de vuelta. Se pide escribiendo a la tienda con el número de pedido y una foto. Por otros motivos depende de cada tienda. Detalle: https://ebaemy.com/marketplace/envios-y-devoluciones
- Para un problema con un pedido, primero se escribe a la tienda que vendió; si no responde, puede usar el Libro de Reclamaciones de esa tienda o acudir a INDECOPI.
- Para ver sus pedidos, el comprador entra a su cuenta. Si está conectado puedes consultarlos con mis_pedidos; si no, dile que inicie sesión.

Estilo: español de Perú, amable y breve. Precios en soles con el formato S/ 49.90. Sin markdown: ni asteriscos, ni títulos, ni listas con guiones; frases cortas. No pidas datos personales (DNI, dirección, tarjeta). Si te piden algo ajeno a comprar en ebaemy, dilo con amabilidad y vuelve a lo que puedes hacer.

Al final de cada respuesta, en una última línea aparte, escribe exactamente «[[sugerencias]]» seguido de 2 o 3 respuestas cortas (máximo 5 palabras cada una) que el comprador podría querer tocar a continuación, separadas por « | ». Deben ser cosas que tú puedas resolver con tus herramientas, en primera persona del comprador. Ejemplo: [[sugerencias]] Menos de S/ 50 | Ver tallas | Agrégalo al carrito
TXT;

    public static function isAvailable(): bool
    {
        return (bool) config('services.anthropic.assistant_enabled')
            && (string) config('services.anthropic.key') !== ''
            && class_exists(Client::class);
    }

    /**
     * Responde a un mensaje del comprador.
     *
     * @param  array<int,array{role:string,text:string}> $historial
     * @param  array{url?:string,slug?:string}           $contexto  dónde está el comprador
     * @param  callable|null                              $onStatus  fn(string $texto) por cada herramienta
     * @return array{text:string,products:array,suggestions:array,cart:?array,meta:array}
     */
    public function reply(array $historial, string $mensaje, ?int $buyerId = null, array $contexto = [], ?callable $onStatus = null): array
    {
        $tools = new ShoppingAssistantTools($buyerId);
        $definiciones = ShoppingAssistantTools::definitions($buyerId !== null);
        $inicio = microtime(true);
        $uso = ['in' => 0, 'out' => 0, 'cache' => 0];
        // Closure con $uso por referencia: se rellena ronda a ronda.
        $meta = function (?string $error = null) use (&$uso, $tools, $inicio, $contexto) {
            return [
            'tools'             => $tools->llamadas(),
            'zero_results'      => $tools->sinResultados(),
            'input_tokens'      => $uso['in'],
            'output_tokens'     => $uso['out'],
            'cache_read_tokens' => $uso['cache'],
            'latency_ms'        => (int) round((microtime(true) - $inicio) * 1000),
            'error'             => $error,
            'context_slug'      => $contexto['slug'] ?? null,
            ];
        };

        if (ShoppingAssistantLog::overBudget()) {
            return $this->fallo('El asistente alcanzó su límite de hoy. Puedes seguir usando el buscador de arriba.', $tools, $meta('budget'));
        }

        $messages = static::buildMessages($historial, $mensaje);
        $system = [['type' => 'text', 'text' => self::SYSTEM_PROMPT, 'cacheControl' => ['type' => 'ephemeral']]];
        if ($ctx = static::contextText($contexto)) {
            $system[] = ['type' => 'text', 'text' => $ctx];
        }

        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = (string) config('services.anthropic.assistant_model');

        try {
            $response = null;
            for ($ronda = 0; $ronda <= self::MAX_RONDAS; $ronda++) {
                $response = $client->messages->create(
                    model: $model,
                    maxTokens: 4096,
                    system: $system,
                    tools: $definiciones,
                    outputConfig: ['effort' => 'low'],
                    messages: $messages,
                );

                $uso['in'] += (int) ($response->usage->inputTokens ?? 0);
                $uso['out'] += (int) ($response->usage->outputTokens ?? 0);
                $uso['cache'] += (int) ($response->usage->cacheReadInputTokens ?? 0);

                if ($response->stopReason !== 'tool_use' || $ronda === self::MAX_RONDAS) {
                    break;
                }

                $resultados = [];
                foreach ($response->content as $block) {
                    if ($block instanceof ToolUseBlock) {
                        $input = (array) $block->input;
                        if ($onStatus) {
                            $onStatus(ShoppingAssistantTools::statusLabel($block->name, $input));
                        }
                        $resultados[] = [
                            'type'      => 'tool_result',
                            'toolUseID' => $block->id,
                            'content'   => $tools->run($block->name, $input),
                        ];
                    }
                }

                $messages[] = ['role' => 'assistant', 'content' => $response->content];
                $messages[] = ['role' => 'user', 'content' => $resultados];
            }
        } catch (RateLimitException $e) {
            ShoppingAssistantLog::addTokens($uso['in'] + $uso['out']);
            return $this->fallo('Hay muchas consultas en este momento. Inténtalo en un minuto o usa el buscador de arriba.', $tools, $meta('rate_limit'));
        } catch (APIStatusException $e) {
            ShoppingAssistantLog::addTokens($uso['in'] + $uso['out']);
            \Log::warning('Asistente marketplace: error de la API', ['status' => $e->getCode(), 'e' => $e->getMessage()]);
            return $this->fallo('No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', $tools, $meta('api_' . $e->getCode()));
        } catch (APIConnectionException $e) {
            \Log::warning('Asistente marketplace: sin conexión con la API', ['e' => $e->getMessage()]);
            return $this->fallo('No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', $tools, $meta('connection'));
        }

        ShoppingAssistantLog::addTokens($uso['in'] + $uso['out']);

        if ($response->stopReason === 'refusal') {
            return $this->fallo('No puedo ayudarte con eso. Si buscas un producto, dime cuál y lo busco.', $tools, $meta('refusal'));
        }

        $texto = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $texto .= $block->text;
            }
        }
        [$texto, $sugerencias] = static::splitSuggestions($texto);
        if ($texto === '') {
            $texto = $tools->productos()
                ? 'Esto es lo que encontré:'
                : 'No encontré nada con eso. ¿Me lo describes con otras palabras?';
        }

        return [
            'text'        => $texto,
            'products'    => $tools->productos(),
            'suggestions' => $sugerencias,
            'cart'        => $tools->carrito(),
            'meta'        => $meta(),
        ];
    }

    /**
     * Mensajes para la API a partir del historial del navegador: solo texto,
     * empieza por el comprador, como mucho MAX_HISTORIAL turnos y con cada
     * texto recortado. El historial lo manda el cliente, así que se trata
     * como dato: lo que diga un turno «assistant» falso no da acceso a nada
     * que las herramientas no den ya.
     *
     * @param  array<int,mixed> $historial
     * @return array<int,array{role:string,content:string}>
     */
    public static function buildMessages(array $historial, string $mensaje): array
    {
        $limpio = [];
        foreach (array_slice($historial, -self::MAX_HISTORIAL) as $turno) {
            if (!is_array($turno)) continue;
            $rol = $turno['role'] ?? null;
            $texto = trim((string) ($turno['text'] ?? ''));
            if (!in_array($rol, ['user', 'assistant'], true) || $texto === '') continue;
            $limpio[] = ['role' => $rol, 'content' => mb_substr($texto, 0, $rol === 'user' ? 500 : 1500)];
        }

        while ($limpio && $limpio[0]['role'] !== 'user') {
            array_shift($limpio);
        }

        $limpio[] = ['role' => 'user', 'content' => mb_substr(trim($mensaje), 0, 500)];

        return $limpio;
    }

    /**
     * Segundo bloque de system con dónde está el comprador. Va FUERA del
     * bloque cacheado para no invalidar el prefijo. Solo se mira lo que la
     * URL dice: una ficha (/marketplace/item/{slug}), una tienda o el carrito.
     */
    public static function contextText(array $contexto): ?string
    {
        $partes = [];
        $slug = isset($contexto['slug']) ? trim((string) $contexto['slug']) : '';
        $url = isset($contexto['url']) ? (string) $contexto['url'] : '';

        if ($slug === '' && preg_match('~/marketplace/item/([a-z0-9\-]+)~i', $url, $m)) {
            $slug = $m[1];
        }
        if ($slug !== '' && preg_match('/^[a-z0-9\-]{1,250}$/i', $slug)) {
            $l = MarketplaceListing::hasPublicPage()->where('slug', $slug)->first(['slug', 'title', 'tenant_name', 'price', 'mp_price', 'stock', 'has_variants']);
            if ($l) {
                $partes[] = sprintf(
                    'El comprador está viendo la ficha del producto «%s» (slug: %s) de la tienda %s, precio S/ %.2f, %s%s. Si dice «esto», «este» o «lo quiero» se refiere a este producto; para detalles usa ver_producto con ese slug.',
                    $l->title, $l->slug, $l->tenant_name, (float) $l->display_price,
                    (int) $l->stock > 0 ? 'con stock' : 'AGOTADO',
                    $l->has_variants ? ', con variantes (talla/color)' : ''
                );
            }
        } elseif (preg_match('~/marketplace/tienda/([a-z0-9\-]+)~i', $url, $m)) {
            $partes[] = 'El comprador está viendo la página de la tienda «' . $m[1] . '». Si busca algo sin decir tienda, puedes filtrar por esa tienda primero y, si no hay, buscar en todo el marketplace diciéndolo.';
        } elseif (str_contains($url, '/marketplace/cart') || str_contains($url, '/marketplace/checkout')) {
            $partes[] = 'El comprador está en su carrito o en el pago. Ayúdale a resolver dudas de envío, pago y garantía sin sacarlo de ahí salvo que lo pida.';
        }

        return $partes ? implode("\n", $partes) : null;
    }

    /**
     * Separa la última línea «[[sugerencias]] a | b | c» del texto. Si el
     * modelo no la escribió, no pasa nada: sin chips.
     *
     * @return array{0:string,1:array<int,string>}
     */
    public static function splitSuggestions(string $texto): array
    {
        $texto = trim($texto);
        $pos = mb_strrpos($texto, self::MARCA_SUGERENCIAS);
        if ($pos === false) {
            return [$texto, []];
        }
        $linea = mb_substr($texto, $pos + mb_strlen(self::MARCA_SUGERENCIAS));
        $texto = rtrim(mb_substr($texto, 0, $pos), " \n\r\t-:");

        $sugerencias = [];
        foreach (explode('|', $linea) as $s) {
            $s = trim($s, " \n\r\t\"«»-•");
            if ($s !== '' && mb_strlen($s) <= 40) $sugerencias[] = $s;
            if (count($sugerencias) === 3) break;
        }
        return [$texto, $sugerencias];
    }

    private function fallo(string $texto, ShoppingAssistantTools $tools, array $meta): array
    {
        return ['text' => $texto, 'products' => $tools->productos(), 'suggestions' => [], 'cart' => $tools->carrito(), 'meta' => $meta];
    }
}
