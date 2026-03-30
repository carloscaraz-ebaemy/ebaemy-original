<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, UsesSystemConnection;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'whatsapp_number', 'address_contact', 'introduction',
        'two_factor_secret', 'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret',
    ];

    protected $dates = ['two_factor_confirmed_at'];

    /** El secreto TOTP se guarda encriptado en BD. */
    public function getTwoFactorSecretAttribute(?string $value): ?string
    {
        if (empty($value)) return null;
        try { return decrypt($value); } catch (\Throwable $e) { return $value; }
    }

    public function setTwoFactorSecretAttribute(?string $value): void
    {
        $this->attributes['two_factor_secret'] = $value ? encrypt($value) : null;
    }

    /** True si 2FA está activo y confirmado. */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->attributes['two_factor_secret'])
            && $this->two_factor_confirmed_at !== null;
    }

    
    /**
     * 
     * Retorna nombre de la conexión
     *
     * @return string
     */
    public function getDbConnectionName()
    {
        return $this->getConnection()->getName();
    }

}
