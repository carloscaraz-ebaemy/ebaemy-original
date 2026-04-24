<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\SellerRegistrationRequest;
use App\Models\System\SellerApplication;
use App\Services\System\RucValidationService;
use App\Services\System\SellerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Formulario público de pre-registro de sellers (/seller/register).
 *
 * Flujo:
 *   GET  /seller/register                        → vista del form multi-paso
 *   POST /seller/register                        → crea SellerApplication
 *   GET  /seller/register/validate-ruc?ruc=...   → autocompleta datos SUNAT
 *   GET  /seller/register/check-subdomain?sub=.. → verifica disponibilidad
 *
 * Todas las rutas tienen rate limit (ver routes/web.php).
 * La lógica de creación se delega a SellerApplicationService.
 */
class SellerRegistrationController extends Controller
{
    public function __construct(
        private SellerApplicationService $service,
        private RucValidationService $rucValidator,
    ) {}

    public function create()
    {
        return view('seller.register');
    }

    public function store(SellerRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['source_ip'] = $request->ip();
        $data['source_ua'] = substr((string) $request->userAgent(), 0, 500);

        $result = $this->service->createApplication($data);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        /** @var SellerApplication $application */
        $application = $result['application'];

        return response()->json([
            'success'      => true,
            'message'      => $result['message'],
            'status'       => $application->status,
            'tracking_url' => $application->tracking_token
                ? url('/seller/application/' . $application->tracking_token)
                : null,
        ]);
    }

    /**
     * Valida un RUC contra SUNAT y devuelve datos normalizados para
     * autocompletar el form del usuario. Adicionalmente señala si el RUC
     * ya está asociado a un tenant existente o a una solicitud en pipeline,
     * para que el form pueda avisar al seller antes de que complete todo
     * el registro.
     */
    public function validateRuc(Request $request): JsonResponse
    {
        $ruc = trim((string) $request->input('ruc', ''));
        $result = $this->rucValidator->validate($ruc);

        // Check rápido: ¿el RUC ya está registrado?
        $existing = null;
        if ($result['valid'] ?? false) {
            $existing = $this->service->findExistingRegistration(['ruc' => $ruc]);
        }

        return response()->json([
            'valid'                  => $result['valid'],
            'error'                  => $result['error'],
            'status'                 => $result['status'],
            'condition'              => $result['condition'],
            'business_name'          => $result['business_name'],
            'fiscal_address'         => $result['fiscal_address'],
            'department'             => $result['department'],
            'province'               => $result['province'],
            'district'               => $result['district'],
            'requires_manual_review' => $result['requires_manual_review'],
            'already_registered'     => $existing ? [
                'type'    => $existing['type'],
                'subtype' => $existing['subtype'] ?? null,
                'message' => $existing['message'],
            ] : null,
        ]);
    }

    /**
     * Verifica si un subdominio está disponible en tiempo real.
     * Revisa: lista de reservados, websites existentes (Hyn), solicitudes
     * activas en el pipeline.
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $sub = strtolower(trim((string) $request->input('sub', '')));

        // Formato básico
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/', $sub)) {
            return response()->json([
                'available' => false,
                'reason'    => 'invalid_format',
                'message'   => 'Formato inválido. Solo letras minúsculas, números y guiones.',
            ]);
        }

        // Reservados
        $excluded = array_map('strtolower', config('tenant.excluded_subdomains', []));
        if (in_array($sub, $excluded, true)) {
            return response()->json([
                'available' => false,
                'reason'    => 'reserved',
                'message'   => 'Ese subdominio está reservado.',
            ]);
        }

        // Ya existe un website con ese uuid
        $uuid = config('tenant.prefix_database') . '_' . $sub;
        $websiteTaken = DB::connection('system')
            ->table('websites')
            ->where('uuid', $uuid)
            ->exists();
        if ($websiteTaken) {
            return response()->json([
                'available' => false,
                'reason'    => 'taken',
                'message'   => 'Ese subdominio ya está en uso.',
            ]);
        }

        // Solicitud activa con ese subdominio
        $pendingApp = SellerApplication::query()
            ->active()
            ->where('requested_subdomain', $sub)
            ->exists();
        if ($pendingApp) {
            return response()->json([
                'available' => false,
                'reason'    => 'pending_application',
                'message'   => 'Ese subdominio está reservado por otra solicitud en revisión.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message'   => 'Subdominio disponible.',
        ]);
    }
}
