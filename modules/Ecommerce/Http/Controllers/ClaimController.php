<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ClaimRequest;
use App\Models\Tenant\Claim;
use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\ClaimPdfService;
use App\Services\Tenant\ClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Libro de Reclamaciones — lado público de la tienda.
 *
 * Todo lo que se lee y se escribe aquí vive en la BD del tenant del dominio
 * por el que entró la petición. No hay ningún parámetro capaz de apuntar a
 * otro tenant, ni falta que hace.
 */
class ClaimController extends Controller
{
    private ClaimService $service;

    public function __construct(ClaimService $service)
    {
        $this->service = $service;
    }

    /**
     * Formulario. Si el visitante viene identificado desde la tienda, se le
     * ahorran los datos que ya conocemos; nunca al revés.
     */
    public function form(Request $request)
    {
        $user = auth('ecommerce')->user();

        $prefill = [
            'names'    => '',
            'surnames' => '',
            'email'    => $user->email ?? '',
            'phone'    => '',
            'address'  => '',
        ];

        if ($user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);
            $prefill['names']    = $parts[0] ?? '';
            $prefill['surnames'] = $parts[1] ?? '';
        }

        return view('ecommerce::claims.form', [
            'provider'         => $this->service->provider(),
            'prefill'          => $prefill,
            'orders'           => $this->customerOrders($user),
            'selected_order'   => $request->query('order'),
            // Token de un solo uso contra el doble envío: el botón deshabilitado
            // del navegador no sirve cuando alguien refresca el POST.
            'submission_token' => Str::random(40),
        ]);
    }

    /**
     * Registra la hoja.
     *
     * El orden importa: primero se persiste y sólo después se intenta el
     * correo. Si SMTP falla, el consumidor ya tiene su código.
     */
    public function store(ClaimRequest $request)
    {
        $data = $request->validated();

        // Idempotencia: el mismo token dos veces devuelve el mismo registro en
        // lugar de duplicar la hoja (doble clic, reintento del navegador,
        // conexión móvil que reenvía el POST).
        $existing = Claim::where('submission_token', $data['submission_token'])->first();

        if ($existing) {
            return $this->confirmation($request, $existing);
        }

        $files = [];
        foreach ((array) $request->file('files', []) as $file) {
            $files[] = $file->store('claims/' . now()->format('Y/m'), 'public');
        }

        $order = $this->resolveOrder($data['order_id'] ?? null);

        $claim = $this->service->create([
            'type'                => $data['type'],
            'item_type'           => $data['item_type'],
            'document_type'       => $data['document_type'],
            'document_number'     => strtoupper(trim($data['document_number'])),
            'names'               => trim($data['names']),
            'surnames'            => trim($data['surnames']),
            'email'               => strtolower(trim($data['email'])),
            'phone'               => $data['phone'] ?? null,
            'address'             => $data['address'],
            'department_id'       => $data['department_id'] ?? null,
            'province_id'         => $data['province_id'] ?? null,
            'district_id'         => $data['district_id'] ?? null,
            'is_minor'            => (bool) ($data['is_minor'] ?? false),
            'guardian_name'       => $data['guardian_name'] ?? null,
            'order_id'            => $order->id ?? null,
            'order_reference'     => $order ? $this->orderReference($order) : null,
            'purchase_date'       => $data['purchase_date'] ?? null,
            'currency'            => $data['currency'],
            'amount'              => $data['amount'] ?? null,
            'product_description' => $data['product_description'],
            'detail'              => $data['detail'],
            'consumer_request'    => $data['consumer_request'],
            'files'               => $files ?: null,
            'ip'                  => $request->ip(),
            'user_agent'          => Str::limit((string) $request->userAgent(), 250, ''),
            'submission_token'    => $data['submission_token'],
        ]);

        $this->service->sendRegistrationMails($claim);

        return $this->confirmation($request, $claim);
    }

    private function confirmation(Request $request, Claim $claim)
    {
        return redirect()
            ->route('tenant.libro_reclamaciones')
            ->with('claim_code', $claim->code)
            ->with('claim_mail_sent', $claim->customer_mail_status === 'sent')
            ->with('claim_email', $claim->email);
    }

    // ── Consulta del registro ─────────────────────────────────────────────

    /**
     * Seguimiento. Pide código Y correo: el código es correlativo y por tanto
     * adivinable, así que por sí solo no abre nada.
     */
    public function track(Request $request)
    {
        return view('ecommerce::claims.track', [
            'provider' => $this->service->provider(),
            'claim'    => null,
        ]);
    }

    public function trackSearch(Request $request)
    {
        $request->validate([
            'code'  => 'required|string|max:20',
            'email' => 'required|email',
        ], [
            'code.required'  => 'Ingresa el código de tu registro (por ejemplo LR-2026-000001).',
            'email.required' => 'Ingresa el correo electrónico con el que registraste el reclamo.',
            'email.email'    => 'Ingresa un correo electrónico válido.',
        ]);

        $claim = Claim::where('code', strtoupper(trim($request->code)))
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($request->email))])
            ->first();

        if (!$claim) {
            return back()
                ->withInput()
                // Mensaje único a propósito: decir «ese código existe pero el
                // correo no coincide» convertiría el buscador en un oráculo.
                ->with('error', 'No encontramos ningún registro con ese código y ese correo electrónico.');
        }

        return view('ecommerce::claims.track', [
            'provider' => $this->service->provider(),
            'claim'    => $claim,
            'token'    => $claim->public_token,
        ]);
    }

    /**
     * Copia en PDF. La llave es el token aleatorio, no el código.
     */
    public function pdf(Request $request, string $code)
    {
        $token = (string) $request->query('t');

        $claim = Claim::where('code', strtoupper($code))->first();

        if (!$claim || $token === '' || !hash_equals($claim->public_token, $token)) {
            abort(404);
        }

        $pdf = app(ClaimPdfService::class);

        return response($pdf->render($claim, $this->service->provider()), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf->filename($claim) . '"',
        ]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────

    /**
     * Pedidos que el formulario puede ofrecer para precargar.
     *
     * Sólo los del consumidor identificado, cruzados por su correo — que es
     * como la tienda relaciona pedido y cliente. Un visitante anónimo no
     * obtiene lista alguna: escribe el número a mano.
     */
    private function customerOrders($user)
    {
        if (!$user || !$user->email) {
            return collect();
        }

        return Order::where(function ($q) use ($user) {
                $q->where('customer', 'LIKE', '%' . $user->email . '%')
                  ->orWhereJsonContains('customer->correo_electronico', $user->email);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'created_at', 'total'])
            ->map(function ($order) {
                return [
                    'id'        => $order->id,
                    'reference' => $this->orderReference($order),
                    'date'      => optional($order->created_at)->format('d/m/Y'),
                    'total'     => $order->total,
                ];
            });
    }

    /**
     * Sólo se acepta un `order_id` que pertenezca al consumidor identificado.
     * Sin esa comprobación, cambiar el número en la URL enseñaría el pedido de
     * otra persona.
     */
    private function resolveOrder($orderId): ?Order
    {
        if (!$orderId) {
            return null;
        }

        $user = auth('ecommerce')->user();

        if (!$user || !$user->email) {
            return null;
        }

        return Order::where('id', $orderId)
            ->where(function ($q) use ($user) {
                $q->where('customer', 'LIKE', '%' . $user->email . '%')
                  ->orWhereJsonContains('customer->correo_electronico', $user->email);
            })
            ->first();
    }

    /** La referencia que el cliente ve del pedido es el N° de pedido, acolchado. */
    private function orderReference(Order $order): string
    {
        return str_pad((string) $order->id, ShippingRequest::ORDER_REF_PAD, '0', STR_PAD_LEFT);
    }
}
