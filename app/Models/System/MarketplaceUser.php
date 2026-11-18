<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Comprador del marketplace — entidad cross-tenant.
 *
 * Vive en la BD system. Reconocido por ebaemy.com y todos los
 * subdominios via cookie de sesion cross-domain.
 *
 * Implementa Authenticatable para usar el sistema de auth de Laravel
 * con un guard propio (config/auth.php → guards.marketplace).
 */
class MarketplaceUser extends Model implements AuthenticatableContract
{
    use UsesSystemConnection, Authenticatable, Notifiable;

    protected $table = 'marketplace_users';

    protected $fillable = [
        'email', 'name', 'phone', 'password_hash',
        'email_verified_at', 'phone_verified_at',
        'locale', 'timezone', 'status',
        'last_login_at', 'last_seen_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'last_seen_at'      => 'datetime',
    ];

    /**
     * Laravel espera getAuthPassword() para credenciales de password.
     * Como usamos password_hash en vez de password, lo mapeamos.
     */
    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    public function consents()
    {
        return $this->hasMany(MarketplaceUserConsent::class, 'user_id');
    }

    public function preferences()
    {
        return $this->hasOne(MarketplaceUserPreference::class, 'user_id');
    }

    /**
     * ¿Tiene consentimiento vigente para (channel, purpose)?
     * Mira la ultima fila de consents y verifica que revoked_at sea null.
     */
    public function hasActiveConsent(string $channel, string $purpose): bool
    {
        $latest = $this->consents()
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->first();
        if (!$latest) return false;
        return $latest->revoked_at === null && $latest->granted_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
