<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['name', 'display_name', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\Tenant\User::class, 'user_role')
                    ->withPivot('establishment_id');
    }

    public function hasPermission(string $key): bool
    {
        return $this->permissions->contains('key', $key);
    }

    public function givePermission(string ...$keys): void
    {
        $ids = Permission::whereIn('key', $keys)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function revokePermission(string ...$keys): void
    {
        $ids = Permission::whereIn('key', $keys)->pluck('id');
        $this->permissions()->detach($ids);
    }
}
