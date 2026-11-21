<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Person;
use App\Services\Tenant\WhatsApp\Evolution\EvolutionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes que LLEGAN al WhatsApp de la tienda (webhook de Evolution).
 *
 * Hoy hace una sola cosa: si un cliente responde «BAJA» a una campaña, lo
 * marca (`persons.whatsapp_opt_out`) para que no entre en ninguna más, y le
 * confirma. Antes la tienda tenía que marcarlo a mano en el detalle.
 *
 * Seguridad: Evolution no firma sus avisos. La URL lleva un token que es el
 * HMAC de la instancia con la APP_KEY; quien no lo tenga recibe 404. Y el
 * aviso solo se atiende si viene de la instancia de ESTA tienda.
 */
class WhatsAppInboundController extends Controller
{
    /** Palabras que valen como baja (sin tildes, mayúsculas). */
    private const BAJA = ['BAJA', 'STOP', 'NO MAS', 'NO MAS MENSAJES', 'CANCELAR', 'DESUSCRIBIR', 'UNSUBSCRIBE'];

    public static function token(string $instance): string
    {
        return hash_hmac('sha256', 'whatsapp-inbound|' . $instance, (string) config('app.key'));
    }

    /** URL pública del webhook de esta tienda para su instancia. */
    public static function urlFor(string $instance): string
    {
        return url('/whatsapp/webhook/evolution/' . self::token($instance));
    }

    public function evolution(Request $request, string $token)
    {
        $c = Configuration::first();
        $instance = $c->qr_api_instance ?? null;
        if (!$instance || !hash_equals(self::token($instance), $token)) {
            abort(404);
        }

        $body = $request->all();
        if (($body['instance'] ?? $instance) !== $instance) {
            return response()->json(['ok' => true, 'ignored' => 'otra instancia']);
        }

        // Evolution manda un mensaje por aviso (data = objeto) o varios (data = lista).
        $data = $body['data'] ?? [];
        $mensajes = isset($data['key']) ? [$data] : (is_array($data) ? $data : []);

        $bajas = 0;
        foreach ($mensajes as $m) {
            if (data_get($m, 'key.fromMe')) {
                continue; // lo escribió la tienda
            }
            $jid = (string) data_get($m, 'key.remoteJid', '');
            if ($jid === '' || str_contains($jid, '@g.us') || str_contains($jid, 'status@broadcast')) {
                continue; // grupos y estados
            }
            $texto = data_get($m, 'message.conversation')
                ?: data_get($m, 'message.extendedTextMessage.text')
                ?: data_get($m, 'message.ephemeralMessage.message.conversation')
                ?: '';
            if (!$this->esBaja((string) $texto)) {
                continue;
            }

            $telefono = preg_replace('/\D/', '', explode('@', $jid)[0]);
            $n = $this->darDeBaja($telefono);
            $bajas += $n;

            Log::info('[WhatsAppInbound] BAJA', ['telefono' => $telefono, 'clientes' => $n]);

            if ($n > 0) {
                try {
                    (new EvolutionClient())->sendText($instance, $telefono, 'Listo, no te enviaremos más avisos. Si cambias de opinión, escríbenos cuando quieras.');
                } catch (\Throwable $e) {
                    Log::warning('[WhatsAppInbound] no se pudo confirmar la baja: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['ok' => true, 'bajas' => $bajas]);
    }

    private function esBaja(string $texto): bool
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
        $t = trim(preg_replace('/[^A-Z ]+/u', ' ', $t));
        $t = preg_replace('/\s+/', ' ', $t);

        return in_array($t, self::BAJA, true);
    }

    /**
     * Marca a todos los clientes cuyo teléfono termina en los 9 dígitos del
     * remitente (los teléfonos se guardan con y sin 51, con espacios…).
     */
    private function darDeBaja(string $telefono): int
    {
        if (!Schema::connection('tenant')->hasColumn('persons', 'whatsapp_opt_out')) {
            return 0;
        }
        $ultimos = substr($telefono, -9);
        if (strlen($ultimos) < 9) {
            return 0;
        }

        $candidatos = Person::query()
            ->where('telephone', 'like', '%' . substr($ultimos, 0, 3) . '%')
            ->get(['id', 'telephone']);

        $n = 0;
        foreach ($candidatos as $p) {
            $digitos = preg_replace('/\D/', '', (string) $p->telephone);
            if ($digitos !== '' && str_ends_with($digitos, $ultimos)) {
                Person::where('id', $p->id)->update(['whatsapp_opt_out' => 1]);
                $n++;
            }
        }

        return $n;
    }
}
