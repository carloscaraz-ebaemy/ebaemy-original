<?php

namespace App\Helpers;

class AuthorizationHelper
{
    /**
     * Check if user has admin-level access (backward compatible).
     * Works with both legacy type system and new RBAC.
     */
    public static function isAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // RBAC check first
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Legacy fallback
        return in_array($user->type ?? '', ['admin', 'superadmin']);
    }

    /**
     * Check if user can perform action on module.
     */
    public static function can(string $permissionKey): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Admin always can
        if (static::isAdmin()) return true;

        // RBAC check
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($permissionKey);
        }

        return false;
    }

    /**
     * Abort if user doesn't have permission.
     */
    public static function authorize(string $permissionKey): void
    {
        if (!static::can($permissionKey)) {
            abort(403, 'No tiene permiso para esta acción.');
        }
    }
}
