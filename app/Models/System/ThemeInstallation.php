<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class ThemeInstallation extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'theme_id', 'hostname_id', 'version', 'status',
        'custom_settings', 'license_key', 'installed_at', 'expires_at',
    ];

    protected $casts = [
        'custom_settings' => 'array',
        'installed_at'    => 'datetime',
        'expires_at'      => 'datetime',
    ];

    public function theme() { return $this->belongsTo(Theme::class); }
    public function hostname() { return $this->belongsTo(Hostname::class); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Activar esta instalación y desactivar las demás del mismo hostname.
     */
    public function activate(): self
    {
        // Desactivar otras instalaciones activas del mismo hostname
        static::where('hostname_id', $this->hostname_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $this->update(['status' => 'active']);
        return $this;
    }

    /**
     * Desactivar esta instalación.
     */
    public function deactivate(): self
    {
        $this->update(['status' => 'inactive']);
        return $this;
    }

    /**
     * Obtener setting personalizado.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->custom_settings, $key, $default);
    }

    /**
     * Actualizar un setting personalizado.
     */
    public function setSetting(string $key, $value): self
    {
        $settings = $this->custom_settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['custom_settings' => $settings]);
        return $this;
    }
}
