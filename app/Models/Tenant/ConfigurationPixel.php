<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConfigurationPixel extends ModelTenant
{
    use HasFactory;

    protected $table = 'configuration_pixels';

    /**
     * Campos asignables (TENANT)
     */
    protected $fillable = [
        'title',
        'script',
        'position', // head | body
        'active',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Scope: solo activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: scripts en <head>
     */
    public function scopeHead($query)
    {
        return $query->where('position', 'head');
    }

    /**
     * Scope: scripts antes de </body>
     */
    public function scopeBody($query)
    {
        return $query->where('position', 'body');
    }
}
