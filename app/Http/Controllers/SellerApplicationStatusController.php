<?php

namespace App\Http\Controllers;

use App\Models\System\SellerApplication;
use App\Models\System\SellerApplicationLog;

/**
 * Portal público de seguimiento de solicitudes de seller.
 *
 * Ruta: GET /seller/application/{token}
 *
 * El token se genera al crear la solicitud (SellerApplication::generateTrackingToken)
 * y se envía al seller por correo. Acceso sin autenticación pero protegido
 * por la opacidad del token (48 chars aleatorios).
 *
 * NO muestra notas internas del SuperAdmin (review_notes se filtra en
 * la vista — solo se exponen los logs "públicos": created, status_changed,
 * approved, rejected, docs_requested).
 */
class SellerApplicationStatusController extends Controller
{
    public function show(string $token)
    {
        $application = SellerApplication::query()
            ->where('tracking_token', $token)
            ->firstOrFail();

        // Logs públicos: excluimos notes internas (action=note_added)
        $publicLogs = $application->logs()
            ->where('action', '!=', SellerApplicationLog::ACTION_NOTE_ADDED)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('seller.status', [
            'application' => $application,
            'publicLogs'  => $publicLogs,
        ]);
    }
}
