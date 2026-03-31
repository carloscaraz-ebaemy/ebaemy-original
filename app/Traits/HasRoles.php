<?php

namespace App\Traits;

use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;

trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role')
                    ->withPivot('establishment_id');
    }

    public function hasRole(string $roleName, ?int $establishmentId = null): bool
    {
        try {
            $query = $this->roles()->where('name', $roleName);
            if ($establishmentId) {
                $query->wherePivot('establishment_id', $establishmentId);
            }
            return $query->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasAnyRole(array $roleNames): bool
    {
        try {
            return $this->roles()->whereIn('name', $roleNames)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasPermission(string $permissionKey, ?int $establishmentId = null): bool
    {
        try {
            return $this->roles()
                ->when($establishmentId, fn($q) => $q->wherePivot('establishment_id', $establishmentId))
                ->whereHas('permissions', fn($q) => $q->where('key', $permissionKey))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasModuleAccess(string $module): bool
    {
        try {
            return $this->roles()
                ->whereHas('permissions', fn($q) => $q->where('module', $module))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function assignRole(string $roleName, ?int $establishmentId = null): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching([
            $role->id => ['establishment_id' => $establishmentId]
        ]);
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        try {
            return $this->roles->flatMap->permissions->unique('id');
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
