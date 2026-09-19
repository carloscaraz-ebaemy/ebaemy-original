<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Claim;
use App\Services\Tenant\ClaimPdfService;
use App\Services\Tenant\ClaimService;
use Illuminate\Http\Request;
use Modules\LevelAccess\Models\ModuleLevel;

/**
 * Libro de Reclamaciones — panel del tenant.
 *
 * El aislamiento entre tenants lo da la conexión: `Claim` vive en la BD del
 * tenant autenticado, así que `/ecommerce/claims/123` sólo puede resolver un
 * registro propio. Un id de otro tenant simplemente no existe aquí y devuelve
 * 404. Lo que sí hace falta es la comprobación de PERMISO dentro del tenant,
 * y de eso se ocupa `guard()`.
 */
class ClaimAdminController extends Controller
{
    private const LEVEL = 'ecommerce_claims';

    private ClaimService $service;

    public function __construct(ClaimService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $this->guard();

        return view('ecommerce::claims.admin');
    }

    /**
     * Listado. Nunca devuelve el detalle completo ni el documento sin
     * enmascarar: para eso está `show()`, que deja rastro de quién lo abrió.
     */
    public function records(Request $request)
    {
        $this->guard();

        $query = Claim::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('document_number', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('order_reference', 'like', '%' . $search . '%')
                  ->orWhere('names', 'like', '%' . $search . '%')
                  ->orWhere('surnames', 'like', '%' . $search . '%');
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->input('deadline') === 'overdue') {
            $query->overdue();
        } elseif ($request->input('deadline') === 'open') {
            $query->open();
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $records = $query->orderByDesc('id')
            ->paginate((int) $request->input('limit', 25));

        $records->getCollection()->transform(function (Claim $claim) {
            return $this->rowData($claim);
        });

        return $records;
    }

    public function show($id)
    {
        $this->guard();

        $claim = Claim::with('events')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->rowData($claim), [
                'document_number'     => $claim->document_number,
                'document_type'       => $claim->document_type,
                'phone'               => $claim->phone,
                'address'             => $claim->address,
                'is_minor'            => $claim->is_minor,
                'guardian_name'       => $claim->guardian_name,
                'item_type'           => $claim->item_type,
                'purchase_date'       => optional($claim->purchase_date)->format('d/m/Y'),
                'currency'            => $claim->currency,
                'amount'              => $claim->amount,
                'product_description' => $claim->product_description,
                'detail'              => $claim->detail,
                'consumer_request'    => $claim->consumer_request,
                'answer'              => $claim->answer,
                'actions_taken'       => $claim->actions_taken,
                'request_accepted'    => $claim->request_accepted,
                'rejection_grounds'   => $claim->rejection_grounds,
                'answered_at'         => optional($claim->answered_at)->format('d/m/Y H:i'),
                'files'               => collect($claim->files ?? [])->map(function ($f) {
                    return ['name' => basename($f), 'url' => url('storage/' . $f)];
                })->values(),
                'mails'               => [
                    'customer' => [
                        'status'  => $claim->customer_mail_status,
                        'error'   => $claim->customer_mail_error,
                        'sent_at' => optional($claim->customer_mail_sent_at)->format('d/m/Y H:i'),
                    ],
                    'tenant'   => [
                        'status'  => $claim->tenant_mail_status,
                        'error'   => $claim->tenant_mail_error,
                        'to'      => $claim->tenant_mail_to,
                        'sent_at' => optional($claim->tenant_mail_sent_at)->format('d/m/Y H:i'),
                    ],
                    'answer'   => [
                        'status'  => $claim->answer_mail_status,
                        'error'   => $claim->answer_mail_error,
                        'sent_at' => optional($claim->answer_mail_sent_at)->format('d/m/Y H:i'),
                    ],
                ],
                'events'              => $claim->events->map(function ($e) {
                    return [
                        'action'      => $e->action,
                        'description' => $e->description,
                        'user_name'   => $e->user_name,
                        'created_at'  => $e->created_at->format('d/m/Y H:i'),
                    ];
                })->values(),
            ]),
        ]);
    }

    /**
     * Respuesta del proveedor. Es el único camino por el que un reclamo pasa a
     * «Respondido», porque respondido sin respuesta enviada no es nada.
     */
    public function answer(Request $request, $id)
    {
        $this->guard();

        $request->validate([
            'answer'            => 'required|string|min:10|max:4000',
            'actions_taken'     => 'nullable|string|max:2000',
            'request_accepted'  => 'required|boolean',
            'rejection_grounds' => 'required_if:request_accepted,0|nullable|string|max:2000',
        ], [
            'answer.required'              => 'Escribe la respuesta que se le enviará al consumidor.',
            'answer.min'                   => 'La respuesta es demasiado corta.',
            'request_accepted.required'    => 'Indica si se acoge o no el pedido del consumidor.',
            'rejection_grounds.required_if' => 'Si no se acoge el pedido, hay que fundamentar por qué.',
        ]);

        $claim = Claim::findOrFail($id);

        $this->service->answer($claim, [
            'answer'            => $request->input('answer'),
            'actions_taken'     => $request->input('actions_taken'),
            'request_accepted'  => (bool) $request->input('request_accepted'),
            'rejection_grounds' => $request->input('rejection_grounds'),
        ], auth()->user());

        $claim->refresh();

        return response()->json([
            'success' => true,
            'message' => $claim->answer_mail_status === 'sent'
                ? 'Respuesta registrada y enviada al consumidor.'
                : 'Respuesta registrada, pero el correo no pudo enviarse. Puedes reintentarlo desde el detalle.',
        ]);
    }

    public function changeStatus(Request $request, $id)
    {
        $this->guard();

        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Claim::STATUSES)),
        ], [
            'status.in' => 'Ese estado no existe.',
        ]);

        $claim = Claim::findOrFail($id);

        // Respondido no se pone a mano: lo pone la respuesta.
        if ($request->input('status') === Claim::STATUS_ANSWERED && !$claim->answered_at) {
            return response()->json([
                'success' => false,
                'message' => 'Para marcar como Respondido hay que registrar la respuesta.',
            ], 422);
        }

        $this->service->changeStatus($claim, $request->input('status'), auth()->user());

        return response()->json(['success' => true, 'message' => 'Estado actualizado.']);
    }

    /** Reintento manual de cualquiera de los tres correos. */
    public function resendMail(Request $request, $id)
    {
        $this->guard();

        $request->validate(['channel' => 'required|in:customer,tenant,answer']);

        $claim = Claim::findOrFail($id);

        $ok = match ($request->input('channel')) {
            'customer' => $this->service->sendCustomerMail($claim),
            'tenant'   => $this->service->sendTenantMail($claim),
            'answer'   => $claim->answered_at ? $this->service->sendAnswerMail($claim) : false,
        };

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Correo enviado.' : 'No se pudo enviar. Revisa la configuración de correo del Libro.',
        ]);
    }

    public function pdf($id)
    {
        $this->guard();

        $claim = Claim::findOrFail($id);
        $pdf   = app(ClaimPdfService::class);

        return response($pdf->render($claim, $this->service->provider()), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf->filename($claim) . '"',
        ]);
    }

    /** Resumen para la cabecera del panel. */
    public function summary()
    {
        $this->guard();

        return response()->json([
            'open'    => Claim::open()->count(),
            'overdue' => Claim::overdue()->count(),
            'recipient' => $this->service->recipientEmail(),
        ]);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────

    /**
     * Permiso dentro del tenant. El administrador pasa siempre; el resto
     * necesita el nivel `ecommerce_claims`, que la migración creó bajo el
     * módulo de Tienda Virtual.
     */
    private function guard(): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        if (in_array($user->type ?? '', ['admin', 'superadmin'], true)) {
            return;
        }

        if (!method_exists($user, 'getCurrentModuleLevelByTenant')) {
            abort(403, 'No tiene permiso para el Libro de Reclamaciones.');
        }

        $levelIds = $user->getCurrentModuleLevelByTenant()->pluck('module_level_id')->toArray();

        $allowed = ModuleLevel::whereIn('id', $levelIds)
            ->where('value', self::LEVEL)
            ->exists();

        if (!$allowed) {
            abort(403, 'No tiene permiso para el Libro de Reclamaciones.');
        }
    }

    /**
     * Fila del listado. El documento va enmascarado: una pantalla de listado
     * no necesita el DNI completo de nadie a la vista.
     */
    private function rowData(Claim $claim): array
    {
        return [
            'id'              => $claim->id,
            'code'            => $claim->code,
            'type'            => $claim->type,
            'type_label'      => $claim->typeLabel(),
            'customer'        => $claim->fullName(),
            'document'        => $claim->document_type . ' ' . $claim->maskedDocument(),
            'email'           => $claim->email,
            'order_reference' => $claim->order_reference,
            'created_at'      => $claim->created_at->format('d/m/Y H:i'),
            'due_date'        => $claim->due_date->format('d/m/Y'),
            'days_left'       => $claim->businessDaysLeft(),
            'deadline_state'  => $claim->deadlineState(),
            'status'          => $claim->status,
            'status_label'    => $claim->statusLabel(),
            'mail_ok'         => $claim->customer_mail_status === 'sent' && $claim->tenant_mail_status === 'sent',
        ];
    }
}
