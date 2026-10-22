<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\UserFeatureTour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Guías de novedades: si el usuario ya vio una y marcarla como vista.
 * Solo toca filas del propio usuario autenticado.
 */
class FeatureTourController extends Controller
{
    private const KEY = '/^[a-z0-9][a-z0-9\-_.]{0,79}$/';

    public function show(string $key)
    {
        abort_unless(preg_match(self::KEY, $key), 404);

        // Tenant sin la migración todavía: se da por vista para no mostrar
        // una guía que después no se puede cerrar para siempre.
        if (!Schema::connection('tenant')->hasTable('user_feature_tours')) {
            return ['seen' => true];
        }

        return [
            'seen' => UserFeatureTour::where('user_id', auth()->id())
                ->where('tour_key', $key)
                ->exists(),
        ];
    }

    public function store(Request $request, string $key)
    {
        abort_unless(preg_match(self::KEY, $key), 404);

        $data = $request->validate([
            'status' => 'nullable|in:completed,dismissed',
        ]);

        if (!Schema::connection('tenant')->hasTable('user_feature_tours')) {
            return ['success' => false];
        }

        UserFeatureTour::updateOrCreate(
            ['user_id' => auth()->id(), 'tour_key' => $key],
            ['status' => $data['status'] ?? 'completed']
        );

        return ['success' => true];
    }
}
