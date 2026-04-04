<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\Referral;
use App\Models\Tenant\Person;
use Illuminate\Support\Str;

class ReferralService
{
    const REFERRED_DISCOUNT = 10; // 10%
    const REFERRER_DISCOUNT = 10; // 10%
    const COUPON_DAYS = 30;

    /**
     * Generar o retornar el codigo de referido de un Person (cliente ecommerce).
     */
    public static function getOrCreateCode(Person $person): string
    {
        if ($person->referral_code) {
            return $person->referral_code;
        }

        $code = 'REF' . strtoupper(Str::random(5));
        while (Person::where('referral_code', $code)->exists()) {
            $code = 'REF' . strtoupper(Str::random(5));
        }

        $person->referral_code = $code;
        $person->save();

        return $code;
    }

    /**
     * Procesar un referido cuando un nuevo cliente se registra.
     */
    public static function processReferralForPerson(string $referralCode, Person $newPerson): ?Referral
    {
        $referrer = Person::where('referral_code', $referralCode)->first();
        if (!$referrer || $referrer->id === $newPerson->id) {
            return null;
        }

        // Evitar doble referido
        if (Referral::where('referred_user_id', $newPerson->id)->exists()) {
            return null;
        }

        // Crear cupon para el referido (nuevo cliente)
        $referredCoupon = Coupon::create([
            'code'       => 'REF-' . strtoupper(Str::random(6)),
            'type'       => 'percentage',
            'value'      => self::REFERRED_DISCOUNT,
            'min_amount' => 0,
            'max_uses'   => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays(self::COUPON_DAYS),
            'active'     => true,
        ]);

        // Crear cupon para el que refirio
        $referrerCoupon = Coupon::create([
            'code'       => 'RWD-' . strtoupper(Str::random(6)),
            'type'       => 'percentage',
            'value'      => self::REFERRER_DISCOUNT,
            'min_amount' => 0,
            'max_uses'   => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays(self::COUPON_DAYS),
            'active'     => true,
        ]);

        $referral = Referral::create([
            'referrer_user_id'   => $referrer->id,
            'referred_user_id'   => $newPerson->id,
            'referral_code'      => $referralCode,
            'status'             => 'rewarded',
            'referrer_coupon_id' => $referrerCoupon->id,
            'referred_coupon_id' => $referredCoupon->id,
        ]);

        // Notificar al que refirio por WhatsApp
        try {
            $wa = app(WhatsAppService::class);
            if ($wa->isEnabled() && $referrer->telephone) {
                $wa->send(
                    $referrer->telephone,
                    "Alguien uso tu codigo de referido *{$referralCode}*.\n\n"
                    . "Tu cupon de *{$referrerCoupon->value}% descuento* es: *{$referrerCoupon->code}*\n"
                    . "Valido por " . self::COUPON_DAYS . " dias."
                );
            }
        } catch (\Throwable $e) {
            // No bloquear registro
        }

        return $referral;
    }

    /**
     * Obtener estadisticas de referidos de un cliente.
     */
    public static function stats(Person $person): array
    {
        $code = self::getOrCreateCode($person);

        return [
            'referral_code'   => $code,
            'share_url'       => url('/ecommerce/register?ref=' . $code),
            'total_referrals' => Referral::where('referrer_user_id', $person->id)->count(),
            'rewarded'        => Referral::where('referrer_user_id', $person->id)->where('status', 'rewarded')->count(),
        ];
    }
}
