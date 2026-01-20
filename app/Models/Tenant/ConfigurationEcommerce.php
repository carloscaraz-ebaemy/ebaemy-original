<?php

namespace App\Models\Tenant;

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

        // PAGOS
        'script_paypal',
        'token_private_culqui',
        'token_public_culqui',

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

        // SEO GENERAL
        'seo_title',
        'seo_description',
        'seo_keywords',
        'indexable',

        // SEO SOCIAL (Facebook / WhatsApp / TikTok)
        'og_title',
        'og_description',
        'og_image',

        // SEO TWITTER
        'twitter_title',
        'twitter_description',
        'twitter_image',

        // OTROS
        'preferences'
    ];

    protected $casts = [
        'preferences' => 'array',
        'indexable'   => 'boolean'
    ];
}

