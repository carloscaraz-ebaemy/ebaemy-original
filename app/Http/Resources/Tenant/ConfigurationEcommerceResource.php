<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class ConfigurationEcommerceResource extends JsonResource
{
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
            'token_private_culqui' => $this->token_private_culqui,
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

            // ESTILO
            'color_ecommerce' => $this->color_ecommerce,
            'preferences' => $this->preferences,

            // 🔥 SEO GENERAL
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'indexable' => (bool) $this->indexable,

            // 🔥 SEO REDES (Facebook / WhatsApp / TikTok)
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,

            // 🔥 SEO TWITTER
            'twitter_title' => $this->twitter_title,
            'twitter_description' => $this->twitter_description,
            'twitter_image' => $this->twitter_image,
        ];
    }
}
