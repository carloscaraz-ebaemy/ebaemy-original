<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\AddNoteSellerApplicationRequest;
use App\Http\Requests\System\ApproveSellerApplicationRequest;
use App\Http\Requests\System\RejectSellerApplicationRequest;
use App\Http\Requests\System\RequestDocumentsSellerApplicationRequest;
use App\Models\System\Plan;
use App\Models\System\SellerApplication;
use App\Services\System\SellerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panel SuperAdmin: gestión de solicitudes de onboarding de sellers.
 *
 * Ruta padre: /admin/seller-applications (protegida por auth:admin).
 *
 * Lógica de negocio delegada a SellerApplicationService — este controller
 * es un wrapper delgado que autentica, valida y ruteea.
 */
class SellerApplicationController extends Controller
{
    public function __construct(
        private SellerApplicationService $service,
    ) {}

    public function index()
    {
        return view('system.seller_applications.index');
    }

    /**
     * Listado paginado con filtros. Usado por la tabla del panel (JSON).
     */
    public function records(Request $request): JsonResponse
    {
        $query = SellerApplication::query()
            ->orderByDesc('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ruc', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('requested_subdomain', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $records = $query->paginate(max(5, min($perPage, 100)));

        return response()->json([
            'data'         => $records->items(),
            'current_page' => $records->currentPage(),
            'last_page'    => $records->lastPage(),
            'per_page'     => $records->perPage(),
            'total'        => $records->total(),
        ]);
    }

    /**
     * Detalle completo de una solicitud — incluye logs e información auxiliar.
     */
    public function show($id): JsonResponse
    {
        $application = SellerApplication::query()
            ->with(['logs' => fn ($q) => $q->orderByDesc('id')])
            ->findOrFail($id);

        return response()->json([
            'application' => $application,
            'logs'        => $application->logs,
            'plans'       => Plan::query()->get(['id', 'name', 'pricing', 'limit_documents', 'limit_users']),
        ]);
    }

    public function markUnderReview($id): JsonResponse
    {
        $application = SellerApplication::query()->findOrFail($id);
        $result = $this->service->markUnderReview($application, auth('admin')->id());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function approve(ApproveSellerApplicationRequest $request, $id): JsonResponse
    {
        $application = SellerApplication::query()->findOrFail($id);

        if (!$application->isReviewable()) {
            return response()->json([
                'success' => false,
                'message' => "La solicitud no está en un estado revisable (actual: {$application->status})",
            ], 422);
        }

        $planId = (int) ($request->input('plan_id') ?: 0);
        $options = [
            'type'               => $request->input('type', 'admin'),
            'modules'            => $request->input('modules', []),
            'levels'             => $request->input('levels', []),
            'email_override'     => $request->input('email_override'),
            'password_override'  => $request->input('password_override'),
            'subdomain_override' => $request->input('subdomain_override'),
        ];

        // Pre-check de duplicados ANTES de marcar approving — si hay RUC/email/
        // subdomain repetido, fallar rápido con 422 y mensaje claro.
        $precheck = $this->service->precheckApproval($application, $options);
        if ($precheck !== null) {
            return response()->json(['success' => false, 'message' => $precheck], 422);
        }

        // Marcar como "approving" para que la UI muestre el estado intermedio
        // mientras el job hace el migrate (que tarda 30-90s).
        $application->update([
            'status' => 'approving',
            'reviewed_by' => auth('admin')->id(),
        ]);

        // Dispatch AFTER RESPONSE: la HTTP response se cierra primero (evita
        // timeout de nginx en 30s), el job corre en el mismo proceso PHP-FPM
        // pero sin cliente esperando. Para queue real (background worker),
        // cambiar a dispatch() con QUEUE_CONNECTION=database/redis + supervisor.
        \App\Jobs\System\ProcessSellerApprovalJob::dispatchAfterResponse(
            $application->id,
            auth('admin')->id(),
            $planId,
            $options
        );

        return response()->json([
            'success' => true,
            'message' => 'Creando tenant en segundo plano. Recarga la lista en 1-2 minutos para ver el resultado.',
            'status'  => 'approving',
        ]);
    }

    public function reject(RejectSellerApplicationRequest $request, $id): JsonResponse
    {
        $application = SellerApplication::query()->findOrFail($id);

        $result = $this->service->reject(
            $application,
            auth('admin')->id(),
            $request->input('rejection_reason')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function requestDocuments(RequestDocumentsSellerApplicationRequest $request, $id): JsonResponse
    {
        $application = SellerApplication::query()->findOrFail($id);

        $result = $this->service->requestDocuments(
            $application,
            auth('admin')->id(),
            $request->input('documents_requested')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function addNote(AddNoteSellerApplicationRequest $request, $id): JsonResponse
    {
        $application = SellerApplication::query()->findOrFail($id);

        $result = $this->service->addNote(
            $application,
            auth('admin')->id(),
            $request->input('note')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
