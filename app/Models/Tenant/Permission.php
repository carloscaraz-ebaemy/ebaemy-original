<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['module', 'action', 'key', 'display_name'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
