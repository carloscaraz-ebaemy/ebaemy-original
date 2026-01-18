<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Configuration extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'locked_admin',
        'certificate',
        'soap_send_id',
        'soap_type_id',
        'soap_username',
        'soap_password',
        'soap_url',
        'token_public_culqui',
        'token_private_culqui',
        'url_apiruc',
        'token_apiruc',
        'apk_url',
        'login',
        'use_login_global',
        'enable_guest_register', // Añadir aquí
        'regex_password_client',
        'tenant_show_ads',
        'tenant_image_ads',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'qr_api_url',
        'qr_api_token',
        'qr_api_msg',
        'active_cron',
        'hour_generate_payment_order',
        'day_before_due',
        'send_notification_cron',
    ];

    
    protected $casts = [
        'regex_password_client' => 'boolean',
        'tenant_show_ads' => 'boolean',
        'enable_guest_register' => 'boolean', // Añadir aquí
        'active_cron' => 'boolean',
    ];


    public static function boot()
    {
        parent::boot();
        static::creating(function (self $item) {

            // if(empty($item->apk_url)) $item->apk_url = 'https://facturaloperu.com/apk/app-debug.apk';
        });
        static::retrieved(function (self $item) {

            // if (empty($item->apk_url)) $item->apk_url = 'https://facturaloperu.com/apk/app-debug.apk';
        });

    }

    public function getUseLoginGlobalAttribute($value)
    {
        return $value ? true : false;
    }

    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = is_null($value) ? null : json_encode($value);
    }

    public function getLoginAttribute($value)
    {
        return is_null($value) ? null : (object) json_decode($value);
    }


    public static function getApiServiceToken(){
        $configuration = self::first();
        // $api_service_token = $configuration->token_apiruc =! '' ? $configuration->token_apiruc : config('configuration.api_service_token');
        $api_service_token = $configuration->token_apiruc == 'false' ? config('configuration.api_service_token') : $configuration->token_apiruc;
        return $api_service_token;
    }

    public static function getDataModuleViewComposer()
    {
        return self::select([
                        'use_login_global',
                        'tenant_show_ads',
                        'tenant_image_ads'
                    ])
                    ->firstOrFail();
    }

    
    /**
     * 
     * Url de imagen para publicidad en clientes (header)
     *
     * @return string
     */
    public function getUrlTenantImageAds()
    {
        if($this->tenant_image_ads)
        {
            $separator = DIRECTORY_SEPARATOR;
            return asset("storage{$separator}uploads{$separator}system_ads{$separator}" . $this->tenant_image_ads);
        }

        return null;
    }

    public function validationConfigNotify()
    {
        $errors = [
            'ws' => null,
            'email' => null,
        ];
        // dd($this->qr_api_url, $this->qr_api_token, $this->mail_host, $this->mail_port, $this->mail_username, $this->mail_password, $this->mail_encryption);

        if (empty($this->qr_api_url) || empty($this->qr_api_token)) {
            $errors['ws'] = 'Falta configurar los parámetros para el envío de notificaciones por WhatsApp';
            return $errors;
        } else if (
            empty($this->mail_host) ||
            empty($this->mail_port) ||
            empty($this->mail_username) ||
            empty($this->mail_password) ||
            empty($this->mail_encryption)
        ) {
            $errors['email'] = 'Falta configurar los parámetros para el envío de notificaciones por email';
            return $errors;
        }

        return $errors;

    }

    public static function setConfigSmtpMail()
    {
        $config = self::first();
                if (
                    !empty($config->mail_host) &&
                    !empty($config->mail_port) &&
                    !empty($config->mail_username) &&
                    !empty($config->mail_password) &&
                    !empty($config->mail_encryption)
                ) {

                    Config::set('mail.host', $config->mail_host);
                    Config::set('mail.port', $config->mail_port);
                    Config::set('mail.username', $config->mail_username);
                    Config::set('mail.password', $config->mail_password);
                    Config::set('mail.encryption', $config->mail_encryption);
                }
    }



}
