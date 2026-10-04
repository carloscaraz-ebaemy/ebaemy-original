<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
        'qr_api_instance',
        'qr_api_msg',
        'active_cron',
        'hour_generate_payment_order',
        'day_before_due',
        'send_notification_cron',
        'auto_approve_sellers',
        'seller_default_plan_id',
        'seller_requires_active_ruc',
        // Open Graph / SEO del marketplace público (ebaemy.com/marketplace).
        // Editables desde /admin/marketplace/seo. Fallbacks razonables si NULL.
        'marketplace_og_title',
        'marketplace_og_description',
        'marketplace_og_image',
        'marketplace_meta_keywords',
        // Redes sociales del marketplace (footer + página tienda). Si NULL,
        // el icono respectivo no se renderiza.
        'marketplace_facebook_url',
        'marketplace_instagram_url',
        'marketplace_whatsapp_url',
        'marketplace_tiktok_url',
    ];


    protected $casts = [
        'regex_password_client' => 'boolean',
        'tenant_show_ads' => 'boolean',
        'enable_guest_register' => 'boolean', // Añadir aquí
        'active_cron' => 'boolean',
        'auto_approve_sellers' => 'boolean',
        'seller_default_plan_id' => 'integer',
        'seller_requires_active_ruc' => 'boolean',
        'marketplace_ads_enabled' => 'boolean',
    ];


    public static function boot()
    {
        parent::boot();
        static::saved(fn () => Cache::forget('system_config'));
        static::deleted(fn () => Cache::forget('system_config'));
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


    private const CACHE_TTL = 600;

    public static function firstCached(): ?self
    {
        return Cache::remember('system_config', self::CACHE_TTL, fn () => self::first());
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

    /**
     * URL absoluta del og:image del marketplace. Si NULL, devolvemos el logo
     * por default. WhatsApp/Facebook necesitan URL HTTPS absoluta.
     *
     * Append ?v={timestamp} de updated_at para forzar a las redes sociales
     * a reescrapeear cuando el SuperAdmin actualiza la imagen. Sin esto el
     * og:image queda cacheado en WhatsApp/FB hasta 24h y no se ve el cambio.
     */
    public function getMarketplaceOgImageUrlAttribute(): string
    {
        $v = $this->updated_at ? $this->updated_at->timestamp : time();
        if ($this->marketplace_og_image) {
            return asset('storage/uploads/system/' . $this->marketplace_og_image) . '?v=' . $v;
        }
        return asset('logo/logo.jpg') . '?v=' . $v;
    }

    /**
     * Dimensiones, peso y ratio REALES de la imagen de compartido del
     * marketplace, o null si no se puede leer el archivo.
     *
     * Hace falta porque el layout declaraba `og:image:width` y `height` fijos
     * en 1200x630 pase lo que pase. Cuando lo declarado no coincide con la
     * imagen de verdad, WhatsApp y Facebook **descartan la preview** y el
     * enlace sale sin foto. Verificado el 2026-10-03: la imagen subida era
     * 1678x419 (ratio 4:1) y se anunciaba como 1200x630.
     *
     * Cacheado un dia: es un getimagesize() sobre disco y el archivo solo
     * cambia cuando el SuperAdmin sube otro. La clave incluye el nombre, asi
     * que al subir una imagen nueva la entrada vieja queda huerfana y expira
     * sola, sin necesidad de invalidarla.
     */
    public function marketplaceOgImageMeta(): ?array
    {
        $relativo = $this->marketplace_og_image
            ? 'app/public/uploads/system/' . $this->marketplace_og_image
            : null;

        $ruta = $relativo ? storage_path($relativo) : public_path('logo/logo.jpg');

        return Cache::remember(
            'mp_og_image_meta:' . ($this->marketplace_og_image ?: 'default'),
            86400,
            function () use ($ruta) {
                if (!is_file($ruta)) {
                    return null;
                }

                $info = @getimagesize($ruta);
                if (!$info || empty($info[0]) || empty($info[1])) {
                    return null;
                }

                [$ancho, $alto] = $info;

                return [
                    'width'  => (int) $ancho,
                    'height' => (int) $alto,
                    'bytes'  => (int) @filesize($ruta),
                    'ratio'  => round($ancho / max(1, $alto), 2),
                    // 1.91:1 es el formato de la tarjeta grande de WhatsApp y
                    // Facebook. Fuera de ese margen la imagen se recorta.
                    'ok'     => $ancho >= 600
                                && abs(($ancho / max(1, $alto)) - 1.91) <= 0.25,
                ];
            }
        );
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
