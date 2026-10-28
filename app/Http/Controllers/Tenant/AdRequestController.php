<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceAdRequest;
use App\Models\System\SystemAdminNotification;
use App\Models\Tenant\Company;
use Illuminate\Http\Request;

/**
 * «Publicidad» en la Tienda virtual: el dueño de la tienda pide que ebaemy
 * anuncie sus productos en Facebook e Instagram. La solicitud va a la base
 * de sistema y ebaemy la gestiona en /admin/marketplace/publicidad.
 *
 * Aquí no se cobra nada: ebaemy confirma presupuesto y pago por WhatsApp
 * antes de empezar, y la solicitud pasa a «pagada».
 */
class AdRequestController extends Controller
{
    public function index()
    {
        $hostname = $this->hostname();
        $requests = MarketplaceAdRequest::where('hostname_id', $hostname->id)
            ->orderByDesc('id')->limit(20)->get();

        $categories = \DB::connection('system')->table('marketplace_listings as l')
            ->join('marketplace_categories as c', 'c.id', '=', 'l.marketplace_category_id')
            ->where('l.hostname_id', $hostname->id)
            ->where('l.is_active', true)->where('l.status', 'active')
            ->distinct()->orderBy('c.name')->pluck('c.name')->values();

        return [
            'published'  => \App\Models\System\MarketplaceListing::published()->where('hostname_id', $hostname->id)->count(),
            'categories' => $categories,
            'phone'      => optional(\App\Models\Tenant\Establishment::first())->telephone,
            'records'    => $requests->map(fn ($r) => [
                'id'         => $r->id,
                'created_at' => $r->created_at->format('d/m/Y'),
                'status'     => $r->status,
                'status_label' => $r->statusLabel(),
                'scope'      => MarketplaceAdRequest::SCOPES[$r->scope] ?? $r->scope,
                'detail'     => $r->scope_detail,
                'budget'     => $r->budget,
                'days'       => $r->days,
                'spent'      => $r->spent,
                'starts_at'  => optional($r->starts_at)->format('d/m/Y'),
                'ends_at'    => optional($r->ends_at)->format('d/m/Y'),
                'results'    => in_array($r->status, ['activa', 'terminada'], true) ? $r->results() : null,
            ]),
        ];
    }

    public function store(Request $request)
    {
        abort_unless(optional(auth()->user())->type === 'admin', 403, 'Solo el administrador de la tienda puede pedir publicidad.');

        $d = $request->validate([
            'scope'         => 'required|in:tienda,categoria,productos',
            'scope_detail'  => 'nullable|required_unless:scope,tienda|string|max:500',
            'zone'          => 'required|in:lima,peru',
            'budget'        => 'required|numeric|min:20|max:20000',
            'days'          => 'required|integer|min:3|max:60',
            'contact_phone' => 'required|string|max:30',
            'notes'         => 'nullable|string|max:500',
        ], [
            'scope_detail.required_unless' => 'Indica la categoría o los productos que quieres anunciar.',
            'budget.required'              => 'Escribe cuánto quieres invertir.',
            'budget.min'                   => 'El presupuesto mínimo es S/ 20.',
            'contact_phone.required'       => 'Déjanos un WhatsApp para coordinar contigo.',
        ]);

        $hostname = $this->hostname();
        $published = \App\Models\System\MarketplaceListing::published()->where('hostname_id', $hostname->id)->count();
        if ($published === 0) {
            return response()->json(['message' => 'Primero publica tus productos en ebaemy.com: la publicidad lleva a tus productos del marketplace.'], 422);
        }
        $open = MarketplaceAdRequest::where('hostname_id', $hostname->id)->whereIn('status', MarketplaceAdRequest::OPEN)->exists();
        if ($open) {
            return response()->json(['message' => 'Ya tienes una solicitud en curso. Te avisaremos por WhatsApp; cuando termine podrás pedir otra.'], 422);
        }

        $company = Company::first();
        $subdomain = strtolower(strtok($hostname->fqdn, '.'));
        $store = $company->trade_name ?: ($company->name ?: $subdomain);

        $r = MarketplaceAdRequest::create($d + [
            'hostname_id'  => $hostname->id,
            'subdomain'    => $subdomain,
            'store_name'   => $store,
            'requested_by' => optional(auth()->user())->name,
            'status'       => 'solicitada',
        ]);

        try {
            SystemAdminNotification::notify(
                'ad_request',
                'Publicidad: ' . $store . ' pide una campaña',
                'S/ ' . number_format($r->budget, 2) . ' · ' . $r->days . ' días · ' . (MarketplaceAdRequest::SCOPES[$r->scope] ?? $r->scope) . ' · WhatsApp ' . $r->contact_phone,
                '/admin/marketplace/publicidad',
                '📣',
                MarketplaceAdRequest::class,
                $r->id
            );
        } catch (\Throwable $e) {
            \Log::warning('[AdRequest] no se pudo avisar al administrador', ['id' => $r->id, 'error' => $e->getMessage()]);
        }

        return [
            'success' => true,
            'message' => 'Solicitud enviada. Te escribiremos por WhatsApp para confirmar el presupuesto y el pago antes de empezar.',
        ];
    }

    private function hostname()
    {
        $hostname = app(\Hyn\Tenancy\Contracts\CurrentHostname::class);
        abort_unless($hostname, 404);

        return $hostname;
    }
}
