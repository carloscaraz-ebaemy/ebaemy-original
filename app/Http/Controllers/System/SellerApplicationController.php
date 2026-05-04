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

        // Activación no requiere plan; onboarding sí. Si onboarding llegara
        // sin plan, el service retorna error claro.
        $planId = (int) ($request->input('plan_id') ?: 0);

        $result = $this->service->approve(
            $application,
            auth('admin')->id(),
            $planId,
            [
                'type'              => $request->input('type', 'admin'),
                'modules'           => $request->input('modules', []),
                'levels'            => $request->input('levels', []),
                'email_override'    => $request->input('email_override'),
                'password_override' => $request->input('password_override'),
            ]
        );

        // Limpiamos la key 'tenant' del response para no exponer atributos
        // internos del Client al frontend del panel.
        if (isset($result['tenant'])) {
            $result['tenant_id'] = $result['tenant']->id;
            unset($result['tenant']);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
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
