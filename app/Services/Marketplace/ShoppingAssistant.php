<?php

namespace App\Services\Marketplace;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\ToolUseBlock;

/**
 * Asistente de compras de ebaemy.com: un chat que entiende lo que el
 * comprador pide con sus palabras («algo para regalar a mi mamá, menos de 80
 * soles») y lo traduce a búsquedas sobre el catálogo real.
 *
 * Claude no ve la base de datos: solo lo que le devuelven las herramientas de
 * ShoppingAssistantTools. Por eso no puede inventar un producto, un precio ni
 * un stock que no estén publicados.
 *
 * La conversación la guarda el navegador y llega en cada petición como texto
 * (sin los bloques de razonamiento, que no se reenvían entre turnos). Dentro
 * de un turno, el bucle de herramientas devuelve el contenido sin tocarlo.
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

    private const SYSTEM_PROMPT = <<<'TXT'
Eres el asistente de compras de ebaemy.com, un marketplace peruano donde varias tiendas venden sus productos. Ayudas a los compradores a encontrar lo que buscan y respondes dudas sobre cómo comprar.

Cómo trabajas:
- Para recomendar o mencionar cualquier producto, precio o disponibilidad, usa primero la herramienta buscar_productos. Solo existe lo que devuelven las herramientas: nunca inventes productos, precios, stock, descuentos ni plazos.
- Traduce lo que pide el comprador a palabras clave de producto. Si pide «algo para regalar a mi mamá», prueba con búsquedas concretas (ej. «cartera», «cojin», «maceta»). Si una búsqueda no trae nada, prueba una o dos palabras alternativas antes de rendirte.
- Si da un presupuesto, pásalo en precio_max.
- Los productos que encuentres se muestran solos como tarjetas debajo de tu mensaje, con foto y enlace. No repitas la lista completa: comenta en 1 a 3 frases cuáles encajan mejor y por qué.
- Si pregunta por colores, tallas o el envío de un producto, usa ver_producto.
- Si no encuentras lo que pide, dilo con claridad. No ofrezcas algo distinto como si fuera lo mismo.

Cómo funciona comprar en ebaemy:
- El comprador le compra directamente a cada tienda: la tienda cobra, envía y atiende cambios y devoluciones.
- El costo y el plazo de envío están en la ficha de cada producto, para Lima y provincias. Si la tienda aún no los indicó, dice «A coordinar con la tienda». El envío se paga a la tienda al coordinar la entrega; no se suma en el carrito.
- Garantía en todas las tiendas: si el producto llega dañado, con falla o no es lo que se pidió, hay 7 días calendario desde que se recibe para pedir cambio o devolución del dinero, sin pagar el envío de vuelta. Se pide escribiendo a la tienda con el número de pedido y una foto. Por otros motivos depende de cada tienda. Detalle: https://ebaemy.com/marketplace/envios-y-devoluciones
- Para un problema con un pedido, primero se escribe a la tienda que vendió; si no responde, puede usar el Libro de Reclamaciones de esa tienda o acudir a INDECOPI.
- Para ver sus pedidos, el comprador entra a su cuenta. Si está conectado puedes consultarlos con mis_pedidos; si no, dile que inicie sesión.

Estilo: español de Perú, amable y breve. Precios en soles con el formato S/ 49.90. Sin markdown complejo: frases cortas. No pidas datos personales (DNI, dirección, tarjeta). Si te piden algo ajeno a comprar en ebaemy, dilo con amabilidad y vuelve a lo que puedes hacer.
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
     * @return array{text:string,products:array}
     */
    public function reply(array $historial, string $mensaje, ?int $buyerId = null): array
    {
        $tools = new ShoppingAssistantTools($buyerId);
        $definiciones = ShoppingAssistantTools::definitions($buyerId !== null);

        $messages = static::buildMessages($historial, $mensaje);
        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = (string) config('services.anthropic.assistant_model');

        try {
            $response = null;
            for ($ronda = 0; $ronda <= self::MAX_RONDAS; $ronda++) {
                $response = $client->messages->create(
                    model: $model,
                    maxTokens: 4096,
                    system: [['type' => 'text', 'text' => self::SYSTEM_PROMPT, 'cacheControl' => ['type' => 'ephemeral']]],
                    tools: $definiciones,
                    outputConfig: ['effort' => 'low'],
                    messages: $messages,
                );

                if ($response->stopReason !== 'tool_use' || $ronda === self::MAX_RONDAS) {
                    break;
                }

                $resultados = [];
                foreach ($response->content as $block) {
                    if ($block instanceof ToolUseBlock) {
                        $resultados[] = [
                            'type'      => 'tool_result',
                            'toolUseID' => $block->id,
                            'content'   => $tools->run($block->name, (array) $block->input),
                        ];
                    }
                }

                $messages[] = ['role' => 'assistant', 'content' => $response->content];
                $messages[] = ['role' => 'user', 'content' => $resultados];
            }
        } catch (RateLimitException $e) {
            return $this->fallo('Hay muchas consultas en este momento. Inténtalo en un minuto o usa el buscador de arriba.', $tools);
        } catch (APIStatusException $e) {
            \Log::warning('Asistente marketplace: error de la API', ['status' => $e->getCode(), 'e' => $e->getMessage()]);
            return $this->fallo('No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', $tools);
        } catch (APIConnectionException $e) {
            \Log::warning('Asistente marketplace: sin conexión con la API', ['e' => $e->getMessage()]);
            return $this->fallo('No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', $tools);
        }

        if ($response->stopReason === 'refusal') {
            return $this->fallo('No puedo ayudarte con eso. Si buscas un producto, dime cuál y lo busco.', $tools);
        }

        $texto = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $texto .= $block->text;
            }
        }
        $texto = trim($texto);
        if ($texto === '') {
            $texto = $tools->productos()
                ? 'Esto es lo que encontré:'
                : 'No encontré nada con eso. ¿Me lo describes con otras palabras?';
        }

        return ['text' => $texto, 'products' => $tools->productos()];
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

    private function fallo(string $texto, ShoppingAssistantTools $tools): array
    {
        return ['text' => $texto, 'products' => $tools->productos()];
    }
}
