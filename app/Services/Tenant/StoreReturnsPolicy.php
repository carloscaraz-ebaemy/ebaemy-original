<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Validation\Rule;

/**
 * Política de cambios y devoluciones de la tienda, como DATOS.
 *
 * Las 9 tiendas activas tenían vacía su página «Cambios y devoluciones» (2026-10-24),
 * y Google Merchant Center y la ley exigen que el comprador vea las condiciones de
 * quien le vende. En vez de una plantilla con huecos —que se publicaría con los
 * corchetes si la tienda no la completa— se piden los datos y el texto se genera.
 *
 * Se guarda en `configuration_ecommerce.preferences.returns_policy` (con MERGE:
 * ese JSON lo comparten otras pantallas, R4) y el texto generado en
 * `cambios_devolucion`, que es lo que ya muestra la página pública de la tienda.
 */
class StoreReturnsPolicy
{
    public const KEY = 'returns_policy';

    public function config(): ?ConfigurationEcommerce
    {
        return ConfigurationEcommerce::first();
    }

    public function data(): ?array
    {
        $prefs = optional($this->config())->preferences;

        return is_array($prefs) && is_array($prefs[self::KEY] ?? null) ? $prefs[self::KEY] : null;
    }

    public function isConfigured(): bool
    {
        return $this->data() !== null;
    }

    /** Lo que se copia al marketplace (dentro del resumen de envío). */
    public function summary(): ?array
    {
        $d = $this->data();
        if (!$d) {
            return null;
        }

        return [
            'defect_days' => (int) $d['defect_days'],
            'change_days' => !empty($d['change_enabled']) ? (int) $d['change_days'] : null,
        ];
    }

    public function formData(): array
    {
        $c = $this->config();
        $d = $this->data() ?? [];

        return [
            'configured'      => $this->isConfigured(),
            'store_name'      => $this->storeName(),
            'has_free_text'   => $c && trim(strip_tags((string) $c->cambios_devolucion)) !== '' && !$this->isConfigured(),
            'defect_days'     => $d['defect_days'] ?? 7,
            'change_enabled'  => $d['change_enabled'] ?? true,
            'change_days'     => $d['change_days'] ?? 7,
            'change_shipping' => $d['change_shipping'] ?? 'customer',
            'refund_days'     => $d['refund_days'] ?? 7,
            'excluded'        => $d['excluded'] ?? '',
            'contact_whatsapp' => $d['contact_whatsapp'] ?? ($c->phone_whatsapp ?? ''),
            'contact_email'   => $d['contact_email'] ?? ($c->information_contact_email ?? ''),
        ];
    }

    public static function rules(): array
    {
        return [
            'defect_days'      => ['required', 'integer', 'min:1', 'max:365'],
            'change_enabled'   => ['boolean'],
            'change_days'      => ['nullable', 'integer', 'min:1', 'max:365', 'required_if:change_enabled,true'],
            'change_shipping'  => ['nullable', Rule::in(['customer', 'store'])],
            'refund_days'      => ['required', 'integer', 'min:1', 'max:60'],
            'excluded'         => ['nullable', 'string', 'max:300'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'contact_email'    => ['nullable', 'email', 'max:120'],
        ];
    }

    public function save(array $d): void
    {
        $c = $this->config();
        if (!$c) {
            return;
        }

        $data = [
            'defect_days'      => (int) $d['defect_days'],
            'change_enabled'   => (bool) ($d['change_enabled'] ?? false),
            'change_days'      => !empty($d['change_enabled']) ? (int) $d['change_days'] : null,
            'change_shipping'  => $d['change_shipping'] ?? 'customer',
            'refund_days'      => (int) $d['refund_days'],
            'excluded'         => trim((string) ($d['excluded'] ?? '')),
            'contact_whatsapp' => trim((string) ($d['contact_whatsapp'] ?? '')),
            'contact_email'    => trim((string) ($d['contact_email'] ?? '')),
            'saved_at'         => now()->toDateTimeString(),
        ];

        // MERGE: preferences lo comparten otras pantallas (R4).
        $prefs = is_array($c->preferences) ? $c->preferences : [];
        $prefs[self::KEY] = $data;
        $c->preferences = $prefs;
        $c->cambios_devolucion = $this->render($data);
        $c->save();
        if (method_exists(ConfigurationEcommerce::class, 'flushCache')) {
            ConfigurationEcommerce::flushCache();
        }

        // La ficha del marketplace muestra «Cambios hasta N días».
        app(StoreShipping::class)->pushToMarketplace();
    }

    /** El texto de la página pública, a partir de los datos. HTML simple y escapado. */
    public function render(array $d): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $dias = fn (int $n) => $n . ' ' . ($n === 1 ? 'día' : 'días');
        $tienda = $e($this->storeName());

        $h  = '<p>Queremos que estés conforme con tu compra en <strong>' . $tienda . '</strong>. Si algo no salió como esperabas, estas son nuestras condiciones.</p>';
        $h .= '<h3>¿Cuándo puedes pedir un cambio o devolución?</h3><ul>';
        $h .= '<li><strong>Producto con falla o dañado:</strong> avísanos dentro de los <strong>' . $dias($d['defect_days']) . '</strong> siguientes a recibirlo. Te lo cambiamos por uno igual o, si no hay stock, te devolvemos el dinero.</li>';
        $h .= '<li><strong>Producto equivocado</strong> (no es el que pediste): te enviamos el correcto sin costo para ti.</li>';
        if (!empty($d['change_enabled'])) {
            $h .= '<li><strong>Cambio de talla, color o modelo:</strong> dentro de los <strong>' . $dias((int) $d['change_days']) . '</strong> de recibido, sujeto a stock.</li>';
        } else {
            $h .= '<li>No aceptamos cambios por talla, color o modelo si el producto llegó en buen estado y es el que pediste.</li>';
        }
        $h .= '</ul>';

        $h .= '<h3>Condiciones</h3><ul>';
        $h .= '<li>El producto debe estar <strong>sin uso</strong>, con sus etiquetas y su empaque original.</li>';
        $h .= '<li>Presenta tu <strong>boleta o factura</strong>, o el número de tu pedido.</li>';
        if (!empty($d['excluded'])) {
            $h .= '<li><strong>No aceptan cambio:</strong> ' . $e($d['excluded']) . '.</li>';
        }
        $h .= '</ul>';

        $h .= '<h3>¿Quién paga el envío?</h3><ul>';
        $h .= '<li>Si el producto llegó con falla o equivocado: <strong>lo pagamos nosotros</strong>.</li>';
        if (!empty($d['change_enabled'])) {
            $h .= '<li>Si es un cambio por talla, color o modelo: <strong>' . (($d['change_shipping'] ?? 'customer') === 'store' ? 'lo pagamos nosotros' : 'lo paga el cliente') . '</strong>.</li>';
        }
        $h .= '</ul>';

        $h .= '<h3>¿Cómo te devolvemos el dinero?</h3>';
        $h .= '<p>Por el mismo medio con el que pagaste, dentro de <strong>' . (int) $d['refund_days'] . ' días hábiles</strong> desde que recibimos y revisamos el producto.</p>';

        $contacto = array_filter([
            !empty($d['contact_whatsapp']) ? 'por WhatsApp al <strong>' . $e($d['contact_whatsapp']) . '</strong>' : null,
            !empty($d['contact_email']) ? 'a <strong>' . $e($d['contact_email']) . '</strong>' : null,
        ]);
        $h .= '<h3>¿Cómo lo solicitas?</h3>';
        $h .= '<p>Escríbenos ' . ($contacto ? implode(' o ', $contacto) : 'por nuestros canales de contacto') . ' con tu número de pedido y una foto del producto.</p>';

        $h .= '<p>Esto no limita tus derechos de <strong>garantía legal</strong> como consumidor. Si no quedas conforme, puedes registrar tu reclamo en nuestro <strong>Libro de Reclamaciones</strong>.</p>';

        return $h;
    }

    private function storeName(): string
    {
        try {
            $co = \Illuminate\Support\Facades\DB::connection('tenant')->table('companies')->first(['trade_name', 'name']);

            return (string) ($co->trade_name ?: $co->name ?: 'nuestra tienda');
        } catch (\Throwable $e) {
            return 'nuestra tienda';
        }
    }
}
