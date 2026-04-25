<?php

namespace App\Http\Controllers;

use App\Models\System\MarketingContact;
use Illuminate\Http\Request;

/**
 * Endpoint público para que el receptor de un mensaje cancele su suscripción
 * sin necesidad de login. El token va en la URL: /unsubscribe/{token}
 * (incluido en cada mensaje por MarketingCampaignService).
 */
class MarketingOptOutController extends Controller
{
    public function show(string $token)
    {
        $contact = MarketingContact::where('opt_out_token', $token)->first();

        if (!$contact) {
            return response()->view('marketing.optout_invalid', [], 404);
        }

        return view('marketing.optout', [
            'contact'  => $contact,
            'already'  => $contact->opted_out,
        ]);
    }

    public function confirm(Request $request, string $token)
    {
        $contact = MarketingContact::where('opt_out_token', $token)->first();

        if (!$contact) {
            return response()->view('marketing.optout_invalid', [], 404);
        }

        if (!$contact->opted_out) {
            $contact->update([
                'opted_out'      => true,
                'opted_out_at'   => now(),
                'opt_out_reason' => mb_substr((string) $request->input('reason', ''), 0, 200) ?: null,
            ]);
        }

        return view('marketing.optout', [
            'contact'  => $contact,
            'already'  => true,
        ]);
    }
}
