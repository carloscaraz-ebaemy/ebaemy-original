<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use App\Services\System\MarketplaceOrderDispatcher;
use Illuminate\Http\Request;

/**
 * Panel de moderación del marketplace central. Accesible solo para admins del
 * landlord. Permite revisar listings publicados por tenants, pausar/activar/
 * rechazar y ver los leads (solicitudes) generados desde el storefront.
 */
class MarketplaceAdminController extends Controller
{
    // ── Listings ──────────────────────────────────────────────────────────────

    public function listings(Request $request)
    {
        $query = MarketplaceListing::query()
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant')) {
            $query->where('tenant_fqdn', 'like', '%' . $request->tenant . '%');
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $listings = $query->paginate(20)->withQueryString();
        $stats = [
            'total'    => MarketplaceListing::count(),
            'active'   => MarketplaceListing::where('status', 'active')->count(),
            'pending'  => MarketplaceListing::where('status', 'pending_review')->count(),
            'rejected' => MarketplaceListing::where('status', 'rejected')->count(),
            'leads'    => MarketplaceLead::count(),
        ];

        return view('system.marketplace.listings', compact('listings', 'stats'));
    }

    public function updateListingStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,paused,rejected,pending_review',
            'rejection_reason' => 'nullable|string|max:250',
        ]);

        $listing = MarketplaceListing::findOrFail($id);
        $listing->status = $request->status;
        $listing->is_active = $request->status === 'active';
        if ($request->status === 'rejected') {
            $listing->rejection_reason = $request->rejection_reason;
        } else {
            $listing->rejection_reason = null;
        }
        $listing->save();

        return back()->with('ok', "Listing actualizado: {$request->status}");
    }

    // ── Leads ─────────────────────────────────────────────────────────────────

    public function leads(Request $request)
    {
        $query = MarketplaceLead::with('listing')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant')) {
            $query->where('tenant_fqdn', 'like', '%' . $request->tenant . '%');
        }

        $leads = $query->paginate(20)->withQueryString();

        return view('system.marketplace.leads', compact('leads'));
    }

    public function retryLead($id, MarketplaceOrderDispatcher $dispatcher)
    {
        $lead = MarketplaceLead::findOrFail($id);
        if (!in_array($lead->status, ['failed', 'new'])) {
            return back()->with('error', 'Solo se reintentan leads fallidos o nuevos');
        }

        $ok = $dispatcher->dispatchLead($lead);
        return back()->with($ok ? 'ok' : 'error', $ok ? 'Lead reenviado al tenant' : 'No se pudo reenviar: ' . $lead->sync_error);
    }

    public function archiveLead($id)
    {
        $lead = MarketplaceLead::findOrFail($id);
        $lead->update(['status' => 'archived']);
        return back()->with('ok', 'Lead archivado');
    }
}
