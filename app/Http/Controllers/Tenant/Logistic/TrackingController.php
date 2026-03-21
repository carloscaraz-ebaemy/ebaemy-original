<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SaleNote;
use App\Enums\LogisticStatusEnum;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Página pública de tracking — no requiere autenticación.
     */
    public function index(Request $request)
    {
        $saleNote = null;
        $error    = null;
        $query    = $request->input('q');

        if ($query) {
            $saleNote = $this->findOrder($query);

            if (!$saleNote) {
                $error = 'No encontramos ningún pedido con ese número. Verifica e intenta nuevamente.';
            }
        }

        $timeline = $saleNote ? $this->buildTimeline($saleNote) : [];

        return view('tenant.logistic.tracking', compact('saleNote', 'timeline', 'error', 'query'));
    }

    /**
     * Busca por número de tracking del courier O por número de documento (NV-001).
     */
    private function findOrder(string $query): ?SaleNote
    {
        $query = trim($query);

        // 1. Buscar por tracking number del courier
        $order = SaleNote::where('tracking_number', $query)
            ->with(['person', 'items.relation_item'])
            ->first();

        if ($order) {
            return $order;
        }

        // 2. Buscar por número de documento (ej: NV-00001 o solo el número)
        if (str_contains($query, '-')) {
            [$series, $number] = explode('-', $query, 2);
            $order = SaleNote::where('series', trim($series))
                ->where('number', (int) trim($number))
                ->with(['person', 'items.relation_item'])
                ->first();
        } else {
            $order = SaleNote::where('number', (int) $query)
                ->with(['person', 'items.relation_item'])
                ->first();
        }

        return $order;
    }

    /**
     * Construye la línea de tiempo según el estado actual.
     */
    private function buildTimeline(SaleNote $saleNote): array
    {
        $status = $saleNote->logistic_status;

        // Orden de estados para el flujo provincia
        $steps = [
            LogisticStatusEnum::PENDIENTE,
            LogisticStatusEnum::PREPARANDO,
            LogisticStatusEnum::LISTO_DESPACHO,
            LogisticStatusEnum::DESPACHADO,
        ];

        // Si fue recogido en tienda
        if ($status === LogisticStatusEnum::RECOGIDO || $status === LogisticStatusEnum::ENTREGA_INMEDIATA) {
            return [
                [
                    'label'       => 'Pedido registrado',
                    'description' => 'Tu pedido fue registrado en el sistema.',
                    'icon'        => '📋',
                    'completed'   => true,
                    'active'      => false,
                ],
                [
                    'label'       => $status === LogisticStatusEnum::RECOGIDO ? 'Recogido en tienda' : 'Entrega inmediata',
                    'description' => $status === LogisticStatusEnum::RECOGIDO
                        ? 'El pedido fue retirado en tienda.'
                        : 'Tu pedido fue entregado de inmediato.',
                    'icon'        => '✅',
                    'completed'   => true,
                    'active'      => true,
                ],
            ];
        }

        $currentIndex = $status ? array_search($status, $steps) : -1;

        $timeline = [
            [
                'label'       => 'Pedido registrado',
                'description' => 'Tu pedido fue recibido y está en cola.',
                'icon'        => '📋',
                'completed'   => true,
                'active'      => $currentIndex === 0,
            ],
            [
                'label'       => 'En preparación',
                'description' => 'El equipo de almacén está preparando tu pedido.',
                'icon'        => '📦',
                'completed'   => $currentIndex >= 1,
                'active'      => $currentIndex === 1,
            ],
            [
                'label'       => 'Listo para envío',
                'description' => 'Tu pedido está empacado y listo para ser despachado.',
                'icon'        => '🏷️',
                'completed'   => $currentIndex >= 2,
                'active'      => $currentIndex === 2,
            ],
            [
                'label'       => 'Despachado',
                'description' => $saleNote->courier_name
                    ? "Enviado con {$saleNote->courier_name}." . ($saleNote->tracking_number ? " N° {$saleNote->tracking_number}" : '')
                    : 'Tu pedido está en camino.',
                'icon'        => '🚚',
                'completed'   => $currentIndex >= 3,
                'active'      => $currentIndex === 3,
            ],
        ];

        return $timeline;
    }
}
