<?php

namespace App\Console\Commands;

use App\Models\Tenant\ScheduledReport;
use App\Models\Tenant\Order;
use App\Models\Tenant\Item;
use App\Models\Tenant\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled {--dry-run : Solo muestra qué se enviaría}';
    protected $description = 'Enviar reportes programados por email';

    public function handle(): int
    {
        $reports = ScheduledReport::active()->get();
        $sent = 0;

        foreach ($reports as $report) {
            if (!$report->shouldSendNow()) continue;

            try {
                $data = $this->generateReportData($report);
                $html = $this->buildHtml($report, $data);

                if ($this->option('dry-run')) {
                    $this->info("DRY-RUN: {$report->name} → " . $report->send_to);
                    continue;
                }

                foreach ($report->getRecipients() as $email) {
                    Mail::html($html, function ($msg) use ($email, $report) {
                        $company = \App\Models\Tenant\Company::first();
                        $msg->to($email)
                            ->subject("[{$company->name}] {$report->name}")
                            ->from(config('mail.from.address'), $company->name);
                    });
                }

                $report->update(['last_sent_at' => now(), 'last_error' => null]);
                $sent++;

            } catch (\Throwable $e) {
                $report->update(['last_error' => substr($e->getMessage(), 0, 500)]);
                $this->error("Error en {$report->name}: {$e->getMessage()}");
            }
        }

        $this->info("{$sent} reportes enviados.");
        return 0;
    }

    private function generateReportData(ScheduledReport $report): array
    {
        return match ($report->report_type) {
            'daily_sales'     => $this->dailySalesData(),
            'weekly_summary'  => $this->weeklySummaryData(),
            'monthly_kpis'    => $this->monthlyKpisData(),
            'stock_alert'     => $this->stockAlertData(),
            'top_products'    => $this->topProductsData(),
            default           => [],
        };
    }

    private function dailySalesData(): array
    {
        $yesterday = Carbon::yesterday();
        $orders = Order::whereDate('created_at', $yesterday)->where('status_order_id', '!=', 5);

        return [
            'period'   => $yesterday->format('d/m/Y'),
            'orders'   => $orders->count(),
            'revenue'  => round($orders->sum('total'), 2),
            'avg'      => round($orders->avg('total'), 2),
            'docs'     => Document::whereDate('date_of_issue', $yesterday)->count(),
        ];
    }

    private function weeklySummaryData(): array
    {
        $from = Carbon::now()->subWeek()->startOfWeek();
        $to   = Carbon::now()->subWeek()->endOfWeek();
        $orders = Order::whereBetween('created_at', [$from, $to])->where('status_order_id', '!=', 5);

        return [
            'period'   => $from->format('d/m') . ' - ' . $to->format('d/m/Y'),
            'orders'   => $orders->count(),
            'revenue'  => round($orders->sum('total'), 2),
            'avg'      => round($orders->avg('total'), 2),
        ];
    }

    private function monthlyKpisData(): array
    {
        $from = Carbon::now()->subMonth()->startOfMonth();
        $to   = Carbon::now()->subMonth()->endOfMonth();
        $orders = Order::whereBetween('created_at', [$from, $to])->where('status_order_id', '!=', 5);

        return [
            'period'     => $from->format('M Y'),
            'orders'     => $orders->count(),
            'revenue'    => round($orders->sum('total'), 2),
            'avg'        => round($orders->avg('total'), 2),
            'new_customers' => $orders->distinct('person_id')->count('person_id'),
        ];
    }

    private function stockAlertData(): array
    {
        $critical = Item::where('apply_store', 1)
            ->where('stock', '<=', 5)
            ->where('stock', '>', 0)
            ->orderBy('stock')
            ->limit(20)
            ->get(['description', 'internal_id', 'stock']);

        $outOfStock = Item::where('apply_store', 1)
            ->where('stock', '<=', 0)
            ->limit(20)
            ->get(['description', 'internal_id', 'stock']);

        return [
            'critical'     => $critical->toArray(),
            'out_of_stock' => $outOfStock->toArray(),
        ];
    }

    private function topProductsData(): array
    {
        $from = Carbon::now()->subDays(7);
        $orders = Order::where('created_at', '>=', $from)
            ->where('status_order_id', '!=', 5)
            ->get(['items']);

        $products = collect();
        foreach ($orders as $order) {
            $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                $key = $item['item_id'] ?? $item['id'] ?? 'x';
                $existing = $products->get($key, ['description' => $item['item']['description'] ?? $item['description'] ?? 'N/A', 'qty' => 0, 'revenue' => 0]);
                $existing['qty']     += $item['quantity'] ?? 0;
                $existing['revenue'] += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $products->put($key, $existing);
            }
        }

        return [
            'period'   => 'Ultimos 7 dias',
            'products' => $products->sortByDesc('revenue')->take(10)->values()->toArray(),
        ];
    }

    private function buildHtml(ScheduledReport $report, array $data): string
    {
        $company = \App\Models\Tenant\Company::first();
        $title = $report->name;

        $body = '<div style="font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:20px">';
        $body .= '<div style="background:#4F46E5;color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center">';
        $body .= '<h2 style="margin:0">' . e($title) . '</h2>';
        $body .= '<p style="margin:4px 0 0;opacity:.8">' . e($company->name) . '</p>';
        $body .= '</div>';
        $body .= '<div style="background:#fff;border:1px solid #e5e7eb;border-top:none;padding:24px;border-radius:0 0 12px 12px">';

        if (isset($data['period'])) {
            $body .= '<p style="color:#6b7280;margin:0 0 16px">Periodo: <strong>' . $data['period'] . '</strong></p>';
        }

        // KPIs
        if (isset($data['revenue'])) {
            $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:16px">';
            $body .= '<tr>';
            $body .= '<td style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px"><div style="font-size:12px;color:#6b7280">Ingresos</div><div style="font-size:22px;font-weight:700;color:#4F46E5">S/ ' . number_format($data['revenue'], 2) . '</div></td>';
            $body .= '<td style="width:8px"></td>';
            $body .= '<td style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px"><div style="font-size:12px;color:#6b7280">Pedidos</div><div style="font-size:22px;font-weight:700;color:#10B981">' . ($data['orders'] ?? 0) . '</div></td>';
            $body .= '<td style="width:8px"></td>';
            $body .= '<td style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px"><div style="font-size:12px;color:#6b7280">Ticket prom.</div><div style="font-size:22px;font-weight:700;color:#F59E0B">S/ ' . number_format($data['avg'] ?? 0, 2) . '</div></td>';
            $body .= '</tr></table>';
        }

        // Stock alerts
        if (isset($data['critical'])) {
            if (!empty($data['out_of_stock'])) {
                $body .= '<h3 style="color:#EF4444;margin:16px 0 8px">Sin stock (' . count($data['out_of_stock']) . ')</h3>';
                $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
                foreach ($data['out_of_stock'] as $item) {
                    $body .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">' . e($item['description']) . '</td><td style="padding:4px 8px;text-align:right;color:#EF4444;font-weight:600;border-bottom:1px solid #f1f5f9">0</td></tr>';
                }
                $body .= '</table>';
            }
            if (!empty($data['critical'])) {
                $body .= '<h3 style="color:#F59E0B;margin:16px 0 8px">Stock critico (' . count($data['critical']) . ')</h3>';
                $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
                foreach ($data['critical'] as $item) {
                    $body .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">' . e($item['description']) . '</td><td style="padding:4px 8px;text-align:right;color:#F59E0B;font-weight:600;border-bottom:1px solid #f1f5f9">' . $item['stock'] . '</td></tr>';
                }
                $body .= '</table>';
            }
        }

        // Top products
        if (isset($data['products'])) {
            $body .= '<h3 style="margin:16px 0 8px">Top productos</h3>';
            $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
            $body .= '<tr style="background:#f8fafc"><th style="padding:6px 8px;text-align:left">Producto</th><th style="padding:6px 8px;text-align:right">Uds.</th><th style="padding:6px 8px;text-align:right">Ingresos</th></tr>';
            foreach ($data['products'] as $p) {
                $body .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">' . e($p['description']) . '</td><td style="padding:4px 8px;text-align:right;border-bottom:1px solid #f1f5f9">' . $p['qty'] . '</td><td style="padding:4px 8px;text-align:right;border-bottom:1px solid #f1f5f9">S/ ' . number_format($p['revenue'], 2) . '</td></tr>';
            }
            $body .= '</table>';
        }

        $body .= '<p style="color:#9ca3af;font-size:11px;margin-top:24px;text-align:center">Reporte generado automaticamente el ' . now()->format('d/m/Y H:i') . '</p>';
        $body .= '</div></div>';

        return $body;
    }
}
