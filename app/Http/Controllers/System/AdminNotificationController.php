<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\SystemAdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de notificaciones del SuperAdmin (campanita del topbar).
 *
 * Endpoints:
 *   GET  /admin/notifications/feed       — últimas 10 + contador no leídas
 *   GET  /admin/notifications            — listado completo paginado
 *   POST /admin/notifications/{id}/read  — marca una como leída
 *   POST /admin/notifications/read-all   — marca todas como leídas
 */
class AdminNotificationController extends Controller
{
    /**
     * Feed para el polling del topbar: 10 más recientes + contador.
     */
    public function feed(): JsonResponse
    {
        $items = SystemAdminNotification::orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type', 'title', 'body', 'icon', 'link', 'is_read', 'created_at']);

        $unread = SystemAdminNotification::where('is_read', false)->count();

        return response()->json([
            'success'      => true,
            'unread_count' => $unread,
            'items'        => $items->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'icon'       => $n->icon ?: '🔔',
                'link'       => $n->link,
                'is_read'    => (bool) $n->is_read,
                'created_at' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Listado completo paginado para la página "Ver todas".
     */
    public function index(Request $request)
    {
        $items = SystemAdminNotification::orderByDesc('created_at')
            ->paginate(30);

        return view('system.admin_notifications.index', compact('items'));
    }

    public function markRead(int $id): JsonResponse
    {
        $n = SystemAdminNotification::find($id);
        if (!$n) {
            return response()->json(['success' => false, 'message' => 'No encontrada'], 404);
        }
        if (!$n->is_read) {
            $n->is_read = true;
            $n->read_at = now();
            $n->save();
        }
        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        SystemAdminNotification::where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true]);
    }
}
