<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'user_id', 'user_type', 'action', 'module', 'description',
        'auditable_type', 'auditable_id', 'old_values', 'new_values',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // ── Relationships ──

    public function auditable()
    {
        return $this->morphTo();
    }

    // ── Static Helpers ──

    public static function record(
        string $action,
        string $module,
        ?string $description = null,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        $user = auth()->user() ?? auth('admin')->user();

        return static::create([
            'user_id'         => $user?->id,
            'user_type'       => $user ? class_basename($user) : 'system',
            'action'          => $action,
            'module'          => $module,
            'description'     => $description,
            'auditable_type'  => $auditable ? get_class($auditable) : null,
            'auditable_id'    => $auditable?->id,
            'old_values'      => $oldValues,
            'new_values'      => $newValues,
            'ip_address'      => request()->ip(),
            'user_agent'      => substr(request()->userAgent() ?? '', 0, 500),
        ]);
    }

    public static function login(string $description = 'Login exitoso'): self
    {
        return static::record('login', 'auth', $description);
    }

    public static function logout(): self
    {
        return static::record('logout', 'auth', 'Cierre de sesión');
    }

    public static function export(string $module, string $description): self
    {
        return static::record('export', $module, $description);
    }

    // ── Scopes ──

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
