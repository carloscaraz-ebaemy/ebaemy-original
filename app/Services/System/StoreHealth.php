<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\ClientAddon;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salud de cada tienda vista desde el SuperAdmin (2026-11-27).
 *
 * Una fila por tienda con lo que hasta ahora había que mirar por consola:
 * cuántos productos tiene publicados y cuándo se sincronizó por última vez,
 * si confirmó su política de envío y sus datos de pago (sin eso el comprador
 * del marketplace no sabe cómo pagarle), si su WhatsApp está conectado, qué
 * extras tiene vigentes y si le quedan pedidos del marketplace sin atender.
 *
 * Lo del sistema sale de las tablas centrales; lo de la tienda exige entrar
 * en su base (una por una, 16 hoy), por eso el resultado se cachea 5 minutos
 * y el botón «Actualizar» lo recalcula.
 */
class StoreHealth
{
    public const CACHE_KEY = 'store_health_v1';
    public const CACHE_TTL = 300;
    /** Horas sin sincronizar a partir de las cuales se avisa. */
    public const SYNC_WARN_HOURS = 24;
    /** Días con un pedido del marketplace en «Por confirmar» a partir de los cuales se avisa. */
    public const UNATTENDED_WARN_DAYS = 2;

    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->compute());
    }

    public function compute(): array
    {
        $clients = Client::query()->with('hostname.website')->orderBy('name')->get()
            ->filter(fn ($c) => $c->hostname);

        $hostIds = $clients->pluck('hostname_id')->filter()->values()->all();
        $listings = $this->listingStats($hostIds);
        $orders   = $this->orderStats($hostIds);
        $addons   = ClientAddon::query()->vigente()
            ->whereIn('client_id', $clients->pluck('id'))
            ->get(['client_id', 'addon_key'])
            ->groupBy('client_id')
            ->map(fn ($g) => $g->pluck('addon_key')->unique()->values()->all());

        $rows = [];
        foreach ($clients as $c) {
            $hid = (int) $c->hostname_id;
            $fqdn = (string) $c->hostname->fqdn;
            $t = $this->tenantFacts($c);
            $l = $listings[$hid] ?? ['activos' => 0, 'con_stock' => 0, 'ultimo_sync' => null];
            $o = $orders[$hid] ?? ['mes' => 0, 'pendientes' => 0, 'fallidos' => 0, 'ultimo' => null];

            $row = [
                'client_id'     => $c->id,
                'nombre'        => $c->name,
                'fqdn'          => $fqdn,
                'subdominio'    => explode('.', $fqdn)[0] ?? $fqdn,
                'bloqueada'     => (bool) ($c->locked_tenant || $c->locked),
                'verificada'    => (bool) $c->is_verified,
                'plan'          => optional($c->plan)->name,
                'listings'      => $l,
                'pedidos'       => $o,
                'addons'        => $addons[$c->id] ?? [],
                'tienda'        => $t,
                'avisos'        => [],
            ];
            $row['avisos'] = $this->avisos($row);
            $row['nivel']  = $row['bloqueada'] ? 'off' : (collect($row['avisos'])->contains(fn ($a) => $a['nivel'] === 'bad') ? 'bad'
                : ($row['avisos'] ? 'warn' : 'ok'));
            $rows[] = $row;
        }

        $resumen = [
            'total'      => count($rows),
            'ok'         => count(array_filter($rows, fn ($r) => $r['nivel'] === 'ok')),
            'avisos'     => count(array_filter($rows, fn ($r) => in_array($r['nivel'], ['warn', 'bad'], true))),
            'bloqueadas' => count(array_filter($rows, fn ($r) => $r['nivel'] === 'off')),
            'publicando' => count(array_filter($rows, fn ($r) => $r['listings']['activos'] > 0 && $r['nivel'] !== 'off')),
            'sin_pago'   => count(array_filter($rows, fn ($r) => $r['nivel'] !== 'off' && $r['listings']['activos'] > 0 && !$r['tienda']['pago'])),
        ];

        return ['generado' => now(), 'resumen' => $resumen, 'tiendas' => $rows];
    }

    /** @return array<int, array{activos:int, con_stock:int, ultimo_sync:?string}> */
    private function listingStats(array $hostIds): array
    {
        if (!$hostIds) {
            return [];
        }
        $out = [];
        $rows = DB::connection('system')->table('marketplace_listings')
            ->selectRaw('hostname_id,
                SUM(CASE WHEN is_active = 1 AND status = "active" THEN 1 ELSE 0 END) AS activos,
                SUM(CASE WHEN is_active = 1 AND status = "active" AND stock > 0 THEN 1 ELSE 0 END) AS con_stock,
                MAX(synced_at) AS ultimo_sync')
            ->whereIn('hostname_id', $hostIds)
            ->groupBy('hostname_id')
            ->get();
        foreach ($rows as $r) {
            $out[(int) $r->hostname_id] = [
                'activos'     => (int) $r->activos,
                'con_stock'   => (int) $r->con_stock,
                'ultimo_sync' => $r->ultimo_sync,
            ];
        }

        return $out;
    }

    /** @return array<int, array{mes:int, pendientes:int, fallidos:int, ultimo:?string}> */
    private function orderStats(array $hostIds): array
    {
        if (!$hostIds || !Schema::connection('system')->hasTable('tenant_marketplace_orders')) {
            return [];
        }
        $out = [];
        $desde = now()->subDays(30)->toDateTimeString();
        $rows = DB::connection('system')->table('tenant_marketplace_orders')
            ->selectRaw('hostname_id,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS mes,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS fallidos,
                MAX(created_at) AS ultimo', [$desde])
            ->whereIn('hostname_id', $hostIds)
            ->groupBy('hostname_id')
            ->get();
        foreach ($rows as $r) {
            $out[(int) $r->hostname_id] = [
                'mes'        => (int) $r->mes,
                'pendientes' => (int) $r->pendientes,
                'fallidos'   => (int) $r->fallidos,
                'ultimo'     => $r->ultimo,
            ];
        }

        return $out;
    }

    /**
     * Lo que solo se sabe entrando en la base de la tienda. Nunca tumba la
     * pantalla: una tienda con la base caída sale con `error` y se sigue.
     */
    private function tenantFacts(Client $c): array
    {
        $facts = [
            'envio'            => false,
            'pago'             => false,
            'pago_medios'      => [],
            'whatsapp'         => null,   // null = sin columna/instancia, 'open', 'connecting', 'disconnected'
            'whatsapp_phone'   => null,
            'sin_atender'      => 0,
            'sin_atender_dias' => null,
            'error'            => null,
        ];
        $website = optional($c->hostname)->website;
        if (!$website) {
            $facts['error'] = 'Sin base de datos';

            return $facts;
        }

        $env = app(Environment::class);
        $previo = $env->tenant();
        try {
            $env->tenant($website);
            $s = Schema::connection('tenant');
            $db = DB::connection('tenant');

            if ($s->hasTable('shipping_settings')) {
                $st = $db->table('shipping_settings')->first();
                if ($st) {
                    $facts['envio'] = $s->hasColumn('shipping_settings', 'shipping_confirmed_at') && !empty($st->shipping_confirmed_at);
                    if ($s->hasColumn('shipping_settings', 'pay_yape_phone')) {
                        $medios = array_keys(array_filter([
                            'Yape'          => !empty($st->pay_yape_phone),
                            'Plin'          => !empty($st->pay_plin_phone),
                            'Transferencia' => !empty($st->pay_transfer),
                            'Efectivo'      => !empty($st->pay_cash),
                            'Tarjeta'       => !empty($st->pay_card),
                        ]));
                        $facts['pago_medios'] = $medios;
                        $facts['pago'] = $medios !== [];
                    }
                }
            }

            if ($s->hasTable('configurations') && $s->hasColumn('configurations', 'qr_api_connection_state')) {
                $cfg = $db->table('configurations')->first(['qr_api_instance', 'qr_api_connection_state', 'qr_api_connected_phone']);
                if ($cfg && $cfg->qr_api_instance) {
                    $facts['whatsapp'] = $cfg->qr_api_connection_state ?: 'connecting';
                    $facts['whatsapp_phone'] = $cfg->qr_api_connected_phone;
                } else {
                    $facts['whatsapp'] = 'disconnected';
                }
            }

            if ($s->hasTable('orders') && $s->hasTable('sales_channels')) {
                $canal = $db->table('sales_channels')->where('code', 'MKP01')->value('id');
                if ($canal) {
                    $q = $db->table('orders')->where('channel_id', $canal)->where('status_order_id', 1);
                    $facts['sin_atender'] = (int) $q->count();
                    $masViejo = (clone $q)->min('created_at');
                    $facts['sin_atender_dias'] = $masViejo ? now()->diffInDays($masViejo) : null;
                }
            }
        } catch (\Throwable $e) {
            $facts['error'] = mb_substr($e->getMessage(), 0, 120);
        } finally {
            $env->tenant($previo ?: null);
        }

        return $facts;
    }

    /** @return array<int, array{nivel:string, texto:string}> */
    private function avisos(array $r): array
    {
        $a = [];
        $t = $r['tienda'];
        $publica = $r['listings']['activos'] > 0;

        if ($t['error']) {
            $a[] = ['nivel' => 'bad', 'texto' => 'No se pudo leer su base: ' . $t['error']];

            return $a;
        }
        if ($r['pedidos']['fallidos'] > 0) {
            $a[] = ['nivel' => 'bad', 'texto' => $r['pedidos']['fallidos'] . ' subpedido(s) del marketplace no llegaron a la tienda'];
        }
        if ($t['sin_atender'] > 0 && ($t['sin_atender_dias'] ?? 0) >= self::UNATTENDED_WARN_DAYS) {
            $a[] = ['nivel' => 'bad', 'texto' => $t['sin_atender'] . ' pedido(s) del marketplace sin atender desde hace ' . $t['sin_atender_dias'] . ' días'];
        } elseif ($t['sin_atender'] > 0) {
            $a[] = ['nivel' => 'warn', 'texto' => $t['sin_atender'] . ' pedido(s) del marketplace por confirmar'];
        }
        if ($publica && !$t['envio']) {
            $a[] = ['nivel' => 'warn', 'texto' => 'Publica sin política de envío confirmada'];
        }
        if ($publica && !$t['pago']) {
            $a[] = ['nivel' => 'warn', 'texto' => 'No indicó cómo le pagan (Yape, Plin, cuenta)'];
        }
        if ($publica && $r['listings']['ultimo_sync']) {
            $horas = now()->diffInHours($r['listings']['ultimo_sync']);
            if ($horas >= self::SYNC_WARN_HOURS) {
                $a[] = ['nivel' => 'warn', 'texto' => 'Sin sincronizar desde hace ' . ($horas >= 48 ? intdiv($horas, 24) . ' días' : $horas . ' h')];
            }
        }
        if ($publica && $r['listings']['con_stock'] === 0) {
            $a[] = ['nivel' => 'warn', 'texto' => 'Todo lo publicado está agotado'];
        }
        if ($publica && $t['whatsapp'] !== 'open') {
            $a[] = ['nivel' => 'warn', 'texto' => 'WhatsApp de la tienda no conectado'];
        }

        return $a;
    }
}
