<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class DomainVerification extends Model
{
    use UsesSystemConnection;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_EXPIRED  = 'expired';

    public const METHOD_DNS_TXT   = 'dns_txt';
    public const METHOD_DNS_CNAME = 'dns_cname';
    public const METHOD_FILE      = 'file';

    protected $fillable = [
        'hostname_id', 'domain', 'method', 'verification_token',
        'status', 'verified_at', 'expires_at', 'last_error', 'attempts',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    // ── Relations ──

    public function hostname()
    {
        return $this->belongsTo(Hostname::class);
    }

    // ── Scopes ──

    public function scopePending($q) { return $q->where('status', self::STATUS_PENDING); }
    public function scopeVerified($q) { return $q->where('status', self::STATUS_VERIFIED); }
    public function scopeFailed($q) { return $q->where('status', self::STATUS_FAILED); }

    // ── State checks ──

    public function isVerified(): bool { return $this->status === self::STATUS_VERIFIED; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isFailed(): bool { return $this->status === self::STATUS_FAILED; }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // ── DNS helpers ──

    /**
     * Obtener el registro TXT esperado para verificación.
     */
    public function getDnsTxtRecord(): string
    {
        return $this->verification_token;
    }

    /**
     * Obtener el target CNAME esperado.
     */
    public function getDnsCnameTarget(): string
    {
        return config('tenant.verification_cname_target', 'verify.' . config('tenant.base_domain', 'ebaemy.com'));
    }

    /**
     * Marcar como verificado.
     */
    public function markAsVerified(): self
    {
        $this->update([
            'status'      => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'last_error'  => null,
        ]);
        return $this;
    }

    /**
     * Marcar como fallido con error.
     */
    public function markAsFailed(string $error): self
    {
        $this->update([
            'status'     => self::STATUS_FAILED,
            'last_error' => $error,
        ]);
        return $this;
    }

    /**
     * Instrucciones DNS para el usuario.
     */
    public function getVerificationInstructions(): array
    {
        return match ($this->method) {
            self::METHOD_DNS_TXT => [
                'type'    => 'TXT',
                'host'    => $this->domain,
                'value'   => $this->verification_token,
                'instructions' => "Agrega un registro TXT en tu DNS con el valor: {$this->verification_token}",
            ],
            self::METHOD_DNS_CNAME => [
                'type'    => 'CNAME',
                'host'    => $this->domain,
                'value'   => $this->getDnsCnameTarget(),
                'instructions' => "Agrega un registro CNAME que apunte a: {$this->getDnsCnameTarget()}",
            ],
            default => [
                'type' => 'unknown',
                'instructions' => 'Método de verificación no soportado.',
            ],
        };
    }

    /**
     * Generar token de verificación único.
     */
    public static function generateToken(): string
    {
        return 'ebaemy-verify-' . bin2hex(random_bytes(16));
    }
}
