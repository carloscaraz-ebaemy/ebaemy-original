<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Webhook;
use App\Models\Tenant\WebhookLog;
use App\Services\Tenant\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index()
    {
        return view('tenant.webhooks.index');
    }

    public function records()
    {
        $webhooks = Webhook::withCount('logs')
            ->orderByDesc('id')
            ->get()
            ->map(function ($w) {
                $w->success_rate = $w->logs_count > 0
                    ? round(WebhookLog::where('webhook_id', $w->id)->where('success', true)->count() / $w->logs_count * 100, 1)
                    : null;
                return $w;
            });

        return response()->json(['data' => $webhooks]);
    }

    public function tables()
    {
        return response()->json([
            'events' => WebhookDispatcher::EVENTS,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'url'      => 'required|url|max:500',
            'events'   => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', array_merge(WebhookDispatcher::EVENTS, ['*'])),
        ]);

        $data = $request->only(['name', 'url', 'events', 'is_active']);

        // Generar secret si es nuevo
        if (!$request->id) {
            $data['secret'] = Str::random(40);
        }

        if ($request->id) {
            $webhook = Webhook::findOrFail($request->id);
            $webhook->update($data);
            $msg = 'Webhook actualizado';
        } else {
            $webhook = Webhook::create($data);
            $msg = 'Webhook creado';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'webhook' => $webhook,
            'secret'  => !$request->id ? $data['secret'] : null,
        ]);
    }

    public function destroy($id)
    {
        Webhook::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Webhook eliminado']);
    }

    public function toggle($id)
    {
        $webhook = Webhook::findOrFail($id);
        $webhook->is_active = !$webhook->is_active;
        if ($webhook->is_active) {
            $webhook->failure_count = 0;
        }
        $webhook->save();

        return response()->json([
            'success'   => true,
            'is_active' => $webhook->is_active,
            'message'   => $webhook->is_active ? 'Webhook activado' : 'Webhook desactivado',
        ]);
    }

    public function logs($id)
    {
        $logs = WebhookLog::where('webhook_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function test($id)
    {
        $webhook = Webhook::findOrFail($id);

        WebhookDispatcher::dispatch('test.ping', [
            'message'   => 'Test webhook from ebaemy',
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ping de prueba enviado',
        ]);
    }
}
