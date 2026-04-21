<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\StockNotification;
use App\Models\Tenant\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class StockNotificationController extends Controller
{
    /**
     * POST /ecommerce/stock-notify
     * Registers an email to be notified when an item is back in stock.
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'email'   => 'required|email|max:180',
            'name'    => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $item = Item::find($request->item_id);
        if (!$item || !$item->apply_store) {
            return response()->json(['success' => false, 'message' => 'Producto no disponible.'], 404);
        }

        // Verify the item is actually out of stock
        $stock = $item->warehouses->sum('stock');
        if ($stock > 0) {
            return response()->json(['success' => false, 'message' => 'El producto ya está disponible.'], 409);
        }

        try {
            StockNotification::updateOrCreate(
                ['item_id' => $item->id, 'email' => strtolower($request->email)],
                ['name' => $request->name ?? null, 'notified' => false, 'notified_at' => null]
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo registrar la notificación.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '¡Listo! Te avisaremos a ' . $request->email . ' cuando haya stock.',
        ]);
    }

    /**
     * POST /ecommerce/newsletter-subscribe
     * Registers an email for the newsletter popup (no item required).
     */
    public function newsletterSubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // Log the subscription (no dedicated table — just acknowledge)
        \Log::info('Newsletter subscription: ' . $request->email);

        return response()->json(['success' => true, 'message' => '¡Suscripción registrada!']);
    }

    /**
     * Dispatch notification emails for all pending subscribers of items that came back in stock.
     * Called via artisan schedule or manually.
     */
    public static function dispatchPendingNotifications(): int
    {
        $company = Company::first();
        $sent    = 0;

        $pending = StockNotification::where('notified', false)
            ->with('item.warehouses')
            ->get();

        foreach ($pending as $notification) {
            $item  = $notification->item;
            if (!$item) continue;

            $stock = $item->warehouses->sum('stock');
            if ($stock <= 0) continue;

            try {
                Mail::send([], [], function ($message) use ($notification, $item, $company) {
                    $storeName   = $company->trade_name ?? $company->name ?? 'Nuestra tienda';
                    $productUrl  = url('/ecommerce/item/' . ($item->slug ?: $item->id));
                    $productName = $item->description;
                    $name        = $notification->name ? ', ' . $notification->name : '';

                    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;color:#333;max-width:560px;margin:0 auto;padding:24px">';
                    $html .= '<h2 style="color:#333">¡Ya hay stock disponible!</h2>';
                    $html .= '<p>Hola' . htmlspecialchars($name) . ',</p>';
                    $html .= '<p>Te escribimos desde <strong>' . htmlspecialchars($storeName) . '</strong> para avisarte que el producto que te interesaba ya está disponible:</p>';
                    $html .= '<div style="background:#f9f9f9;border:1px solid #eee;border-radius:8px;padding:16px;margin:16px 0">';
                    $html .= '<strong>' . htmlspecialchars($productName) . '</strong>';
                    $html .= '</div>';
                    $html .= '<a href="' . $productUrl . '" style="display:inline-block;background:#333;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;margin-top:8px">Ver producto</a>';
                    $html .= '<p style="margin-top:24px;font-size:12px;color:#999">Si no solicitaste este aviso, ignora este mensaje.</p>';
                    $html .= '</body></html>';

                    // Sanitizar el productName antes de usarlo en subject:
                    // - remover line breaks (previene header injection SMTP)
                    // - recortar a 100 chars (evita asuntos gigantes)
                    // - colapsar espacios en blanco repetidos
                    $safeSubject = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', (string) $productName)));
                    $safeSubject = mb_substr($safeSubject, 0, 100);

                    $message->to($notification->email)
                            ->subject('¡Ya está disponible! — ' . $safeSubject)
                            ->setBody($html, 'text/html');
                });

                $notification->update(['notified' => true, 'notified_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                \Log::warning('StockNotification email failed: ' . $e->getMessage(), [
                    'notification_id' => $notification->id,
                    'email'           => $notification->email,
                ]);
            }
        }

        return $sent;
    }

    // ── Admin ──────────────────────────────────────────────────────────────

    public function adminIndex()
    {
        return view('ecommerce::stock_notifications.index');
    }

    public function adminRecords()
    {
        try {
            $rows = StockNotification::with(['item.warehouses'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($n) {
                    $item  = $n->item;
                    $stock = $item ? $item->warehouses->sum('stock') : 0;
                    return [
                        'id'               => $n->id,
                        'item_description' => $item ? $item->description : '(producto eliminado)',
                        'item_internal_id' => $item ? $item->internal_id : null,
                        'item_stock'       => $stock,
                        'email'            => $n->email,
                        'name'             => $n->name,
                        'notified'         => $n->notified,
                        'notified_at'      => $n->notified_at ? $n->notified_at->format('Y-m-d H:i') : null,
                        'created_at'       => $n->created_at->format('Y-m-d H:i'),
                    ];
                });
            return response()->json(['data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['data' => []]);
        }
    }

    public function adminSend()
    {
        $sent = self::dispatchPendingNotifications();
        return response()->json([
            'success' => true,
            'message' => $sent > 0
                ? "Se enviaron {$sent} notificación(es)."
                : 'No hay notificaciones pendientes para enviar.',
        ]);
    }

    public function adminDestroy($id)
    {
        StockNotification::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Eliminado']);
    }
}
