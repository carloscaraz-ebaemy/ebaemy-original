<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'name', 'slug', 'path', 'css_template', 'description',
        'preview_image', 'category', 'is_active', 'is_premium', 'sort_order',
        'version', 'author', 'price', 'default_settings', 'supported_modes',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_premium'       => 'boolean',
        'price'            => 'decimal:2',
        'default_settings' => 'array',
        'supported_modes'  => 'array',
    ];

    // ── Scopes ──

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeFree($q) { return $q->where('is_premium', false); }
    public function scopeNiche($q) { return $q->where('category', 'nicho'); }

    public function scopeAvailableForTenants($q)
    {
        return $q->active()->orderBy('sort_order');
    }

    // ── Relationships ──

    public function installations()
    {
        return $this->hasMany(ThemeInstallation::class);
    }

    // ── Helpers ──

    public function folderExists(): bool
    {
        return is_dir(resource_path('views/themes/' . $this->path));
    }

    public function cssExists(): bool
    {
        if (!$this->css_template || $this->css_template === 'generic') return false;
        return file_exists(public_path("porto-light/css/themes/{$this->css_template}.css"));
    }

    /**
     * ¿Soporta un modo de ecommerce específico?
     */
    public function supportsMode(string $mode): bool
    {
        $modes = $this->supported_modes;
        if (empty($modes)) return true; // Sin restricción = soporta todos
        return in_array($mode, $modes);
    }

    /**
     * Obtener setting por defecto del theme.
     */
    public function getDefaultSetting(string $key, $default = null)
    {
        return data_get($this->default_settings, $key, $default);
    }

    public static function getDefault(): ?self
    {
        return static::where('slug', 'default')->first();
    }
}
