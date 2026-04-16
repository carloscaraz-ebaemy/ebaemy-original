<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class ConfigurationEcommerceResource extends JsonResource
{
    protected function maskedSecret($value)
    {
        return filled($value) ? '********' : null;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,

            // CONTACTO
            'information_contact_name' => $this->information_contact_name,
            'information_contact_email' => $this->information_contact_email,
            'information_contact_phone' => $this->information_contact_phone,
            'information_contact_address' => $this->information_contact_address,
            'phone_whatsapp' => $this->phone_whatsapp,

            // PAGOS
            'script_paypal' => $this->script_paypal,
            'token_private_culqui' => $this->maskedSecret($this->token_private_culqui),
            'token_private_culqui_configured' => filled($this->token_private_culqui),
            'token_public_culqui' => $this->token_public_culqui,

            // LOGO
            'logo' => $this->logo,

            // REDES SOCIALES
            'link_youtube' => $this->link_youtube,
            'link_twitter' => $this->link_twitter,
            'link_facebook' => $this->link_facebook,
            'link_tiktok' => $this->link_tiktok,
            'link_instagram' => $this->link_instagram,

            // TAGS
            'tag_shipping' => $this->tag_shipping,
            'tag_dollar' => $this->tag_dollar,
            'tag_support' => $this->tag_support,

            // LINKS PERSONALIZADOS
            'title_one_customised_link' => $this->title_one_customised_link,
            'title_two_customised_link' => $this->title_two_customised_link,
            'title_three_customised_link' => $this->title_three_customised_link,
            'customised_link_one' => $this->customised_link_one,
            'customised_link_two' => $this->customised_link_two,
            'customised_link_three' => $this->customised_link_three,

            // PÍXELES DE PUBLICIDAD
            'facebook_pixel_id'   => $this->facebook_pixel_id,
            'facebook_capi_token' => $this->facebook_capi_token
                ? str_repeat('*', 8) . substr($this->facebook_capi_token, -6)
                : null,
            'facebook_capi_active' => !empty($this->facebook_capi_token),
            'tiktok_pixel_id'     => $this->tiktok_pixel_id,
            'ga4_measurement_id'  => $this->ga4_measurement_id,

            // ESTILO
            'color_ecommerce' => $this->color_ecommerce,
            'preferences' => $this->preferences,

            // 🔥 SEO GENERAL
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_author' => $this->seo_author,
            'seo_robots' => $this->seo_robots ?? 'index, follow',
            'canonical_url' => $this->canonical_url,
            'indexable' => (bool) $this->indexable,

            // 🔥 OPEN GRAPH
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image 
                ? asset('storage/uploads/logos/' . $this->og_image) 
                : null,
            'og_type' => $this->og_type ?? 'website',

            // 🔥 TWITTER
            'twitter_title' => $this->twitter_title,
            'twitter_description' => $this->twitter_description,
            'twitter_image' => $this->twitter_image 
                ? asset('storage/uploads/logos/' . $this->twitter_image) 
                : null,
            'twitter_card' => $this->twitter_card ?? 'summary_large_image',

            // 🔥 STRUCTURED DATA
            'schema_json' => $this->schema_json,


            // TERMINOS Y CONDICIONES
            'politica_privacy' => $this->politica_privacy,
            'politica_envio' => $this->politica_envio,  
           'termino_conditions' => $this->termino_conditions,
            'cambios_devolucion' => $this->cambios_devolucion,

            // GOOGLE SITE VERIFICATION
            'google_site_verification' => $this->google_site_verification,

            // GOOGLE OAUTH LOGIN
            'google_client_id'     => $this->google_client_id,
            'google_client_secret' => $this->maskedSecret($this->google_client_secret),
            'google_client_secret_configured' => filled($this->google_client_secret),
            'google_login_enabled' => (bool) $this->google_login_enabled,

            // NEWSLETTER POPUP
            'newsletter_popup_enabled' => (bool) $this->newsletter_popup_enabled,
            'newsletter_popup_title'   => $this->newsletter_popup_title,
            'newsletter_popup_desc'    => $this->newsletter_popup_desc,
            'newsletter_discount_code' => $this->newsletter_discount_code,
            'newsletter_popup_image'   => $this->newsletter_popup_image
                ? asset('storage/uploads/logos/' . $this->newsletter_popup_image)
                : null,
        ];
    }
}
