<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Casts\Attribute;

class ConfigurationEcommerce extends ModelTenant
{
    protected $table = "configuration_ecommerce";

    protected $fillable = [
        // CONTACTO
        'information_contact_name',
        'information_contact_email',
        'information_contact_phone',
        'information_contact_address',
        'phone_whatsapp',
        'notification_interval',
        'notify_new_order',
        'notify_pending_reminder',
        'notify_order_confirmed',
        'notify_customer_order',

        // WHATSAPP CLOUD API
        'whatsapp_api_token',
        'whatsapp_phone_id',
        'whatsapp_vendor_number',
        'whatsapp_driver',
        'whatsapp_notifications_enabled',

        // PAGOS
        'script_paypal',
        'token_private_culqui',
        'token_public_culqui',
        'mp_access_token',
        'mp_public_key',
        'mp_sandbox',
        'mp_enabled',

        // REDES
        'link_youtube',
        'link_twitter',
        'link_facebook',
        'link_tiktok',
        'link_instagram',

        // TAGS / UBICACIÓN
        'tag_shipping',
        'tag_dollar',
        'tag_support',

        // LINKS PERSONALIZADOS
        'title_one_customised_link',
        'title_two_customised_link',
        'title_three_customised_link',
        'customised_link_one',
        'customised_link_two',
        'customised_link_three',

        // ESTILO
        'color_ecommerce',
        'theme_template', // generic, fashion, tech, food, sports, luxury, pharmacy, hardware
        'theme_id',        // FK a themes del sistema
        'ecommerce_mode',  // general | nicho
        'business_type',   // ropa, tecnologia, alimentos, etc.
        'marketplace_config', // JSON: credenciales de Saga, ML, Meta, TikTok

        // SEO GENERAL
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_author',
        'seo_robots',
        'canonical_url',
        'indexable',

        // =========================
        // OPEN GRAPH
        // =========================
        'og_title',
        'og_description',
        'og_image',
        'og_type',

        // =========================
        // TWITTER
        // =========================
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_card',
        // =========================
        // SCHEMA
        // =========================
        'schema_json',

        // POLÍTICAS
        'politica_privacy', 
        'cambios_devolucion',
        'politica_envio',
        'termino_conditions',

        // GOOGLE SITE VERIFICATION
        'google_site_verification',

        // PÍXELES DE PUBLICIDAD
        'facebook_pixel_id',
        'facebook_capi_token',
        'tiktok_pixel_id',
        'ga4_measurement_id',

        // GOOGLE OAUTH LOGIN
        'google_client_id',
        'google_client_secret',
        'google_login_enabled',

        // NEWSLETTER POPUP
        'newsletter_popup_enabled',
        'newsletter_popup_title',
        'newsletter_popup_desc',
        'newsletter_discount_code',
        'newsletter_popup_image',

        // OTROS
        'preferences'
    ];

    protected $casts = [
        'preferences' => 'array',
        'indexable'   => 'boolean',
        'marketplace_config' => 'encrypted:array',
        'whatsapp_notifications_enabled' => 'array',
        'mp_sandbox'  => 'boolean',
        'mp_enabled'  => 'boolean',
    ];

    // ── Cache helpers ─────────────────────────────────────────────────────────
    private const CACHE_TTL = 600;

    private static function cacheKey(): string
    {
        try {
            $uuid = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default';
        } catch (\Throwable) {
            $uuid = 'default';
        }
        return "tenant_config_ecommerce_{$uuid}";
    }

    public static function firstCached(): ?self
    {
        return \Illuminate\Support\Facades\Cache::remember(
            self::cacheKey(),
            self::CACHE_TTL,
            fn () => self::first()
        );
    }

    public static function flushCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget(self::cacheKey());
    }

    protected static function booted(): void
    {
        static::saved(fn ()   => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }
    // ─────────────────────────────────────────────────────────────────────────

    // ── Tokens Culqi encriptados en BD ────────────────────────────────────────
    // La clave privada se encripta con APP_KEY al guardar y se desencripta al leer.
    // Si el valor en BD es texto plano (instalaciones anteriores) y decrypt() falla,
    // se devuelve el valor crudo para no romper la funcionalidad existente.

    protected function tokenPrivateCulqui(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return decrypt($value);
                } catch (\Throwable) {
                    return $value; // valor legacy en texto plano
                }
            },
            set: fn ($value) => empty($value) ? null : encrypt($value),
        );
    }

    protected function tokenPublicCulqui(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return decrypt($value);
                } catch (\Throwable) {
                    return $value; // valor legacy en texto plano
                }
            },
            set: fn ($value) => empty($value) ? null : encrypt($value),
        );
    }

    protected function facebookCapiToken(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return decrypt($value);
                } catch (\Throwable) {
                    return $value;
                }
            },
            set: fn ($value) => empty($value) ? null : encrypt($value),
        );
    }
}

