<?php

namespace Modules\Ecommerce\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Company;
use App\Http\Requests\Tenant\ConfigurationEcommerceRequest;
use App\Http\Resources\Tenant\ConfigurationEcommerceResource;
use Modules\Finance\Helpers\UploadFileHelper;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant\ConfigurationScript;


class ConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('ecommerce::configuration.index');
    }

    /**
     * Vista de plugins del ecommerce.
     */
    public function pluginsView()
    {
        return view('ecommerce::configuration.plugins');
    }

    public function notificationsView()
    {
        return view('ecommerce::configuration.notifications');
    }

    public function store_notifications(Request $request)
    {
        $config = ConfigurationEcommerce::firstOrCreate([]);
        $config->notification_interval    = $request->input('notification_interval', 5);
        $config->notify_new_order         = (bool) $request->input('notify_new_order', true);
        $config->notify_pending_reminder  = (bool) $request->input('notify_pending_reminder', true);
        $config->notify_order_confirmed   = (bool) $request->input('notify_order_confirmed', true);
        $config->notify_customer_order    = (bool) $request->input('notify_customer_order', true);
        $config->save();
        $config->flushCache();

        return ['success' => true, 'message' => 'Configuración de notificaciones guardada'];
    }

    public function testWhatsApp()
    {
        $wa = new \App\Services\Tenant\WhatsAppService();
        if (!$wa->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp no está configurado. Ve a Empresa → QR Api.'];
        }
        $econfig = ConfigurationEcommerce::first();
        $phone = $econfig->phone_whatsapp ?? null;
        if (!$phone) {
            return ['success' => false, 'message' => 'No hay número de WhatsApp configurado.'];
        }
        $result = $wa->send($phone, '✅ *Mensaje de prueba*' . "\n\n" . 'Las notificaciones de tu tienda están funcionando correctamente.' . "\n" . date('d/m/Y H:i:s'));
        return ['success' => $result, 'message' => $result ? 'Mensaje enviado' : 'Error al enviar. Verifica la conexión QR Api.'];
    }

    /**
     * Vista de galería de themes para el tenant.
     */
    public function themesGallery()
    {
        return view('ecommerce::configuration.themes');
    }

    public function record()
    {
        // $configuration = ConfigurationEcommerce::firstCached();
        // $record = new ConfigurationEcommerceResource($configuration);
        // return $record;
        $configuration = ConfigurationEcommerce::firstCached() ?? new ConfigurationEcommerceResource();
        return new ConfigurationEcommerceResource($configuration);
    }

    public function store_configuration_terms(Request $request)
    {
        // Buscamos el primer registro de configuración
        $configuration = ConfigurationEcommerce::firstCached();

        // Si por alguna razón no existe, lo creamos
        if (!$configuration) {
            $configuration = new ConfigurationEcommerce();
        }

        $configuration->fill($request->only([
            'politica_privacy', 'cambios_devolucion', 'politica_envio', 'termino_conditions',
        ]));
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Términos y políticas actualizados correctamente'
        ];
    }




    public function store_configuration(ConfigurationEcommerceRequest $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function getSocialScripts()
    {
        return ConfigurationScript::all();
    }

    public function saveSocialScripts(Request $request)
    {
        $scripts = $request->input('scripts', []);

       

        $ids = [];

        foreach ($scripts as $data) {
            $validated = validator($data, [
                'id' => 'nullable',
                'title' => 'required|string|max:255',
                'script' => 'required|string',
                'position' => 'required|in:head,body',
                'active' => 'boolean',
            ])->validate();

            $script = ConfigurationScript::updateOrCreate(
                ['id' => $data['id'] ?? null],
                $validated
            );

            $ids[] = $script->id;
        }

        // Eliminar scripts que ya no están (solo si se procesó al menos uno)
        if (!empty($ids)) {
            ConfigurationScript::whereNotIn('id', $ids)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Scripts actualizados y eliminados.']);
    }



    public function store_configuration_seo(Request $request)
    {
    $request->validate([
        'seo_title' => 'nullable|string|max:255',
        'seo_description' => 'nullable|string|max:255',
        'seo_keywords' => 'nullable|string|max:255',

        'og_title' => 'nullable|string|max:255',
        'og_description' => 'nullable|string|max:255',
        'og_image' => 'nullable|string|max:255',

        'twitter_title' => 'nullable|string|max:255',
        'twitter_description' => 'nullable|string|max:255',
        'twitter_image' => 'nullable|string|max:255',
        'google_site_verification' => 'nullable|string|max:255',

        'indexable' => 'nullable|boolean',
    ]);

    $configuration = ConfigurationEcommerce::firstCached(); // <--- AQUÍ
    if (!$configuration) {
        $configuration = ConfigurationEcommerce::create([]);
    }

      $payload = [
          'seo_title' => $request->seo_title,
          'seo_description' => $request->seo_description,
          'seo_keywords' => $request->seo_keywords,

        'og_title' => $request->og_title,
        'og_description' => $request->og_description,
        'og_image' => $request->og_image,

          'twitter_title' => $request->twitter_title,
          'twitter_description' => $request->twitter_description,
          'twitter_image' => $request->twitter_image,
          'google_site_verification' => $request->google_site_verification,
      ];

      // Solo actualizar indexable si llega explícitamente en el request
      // para evitar apagar indexación accidentalmente.
      if ($request->has('indexable')) {
          $payload['indexable'] = $request->boolean('indexable');
      }

      $configuration->update($payload);

    return [
        'success' => true,
        'message' => 'Configuración SEO actualizada correctamente'
    ];
}



    public function store_configuration_culqui(Request $request)
    {
        $configuration = ConfigurationEcommerce::firstCached();
        if (!$configuration) return ['success' => false, 'message' => 'No existe configuración'];
        $configuration->fill($request->only([
            'token_private_culqui', 'token_public_culqui',
        ]));
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Culqui actualizada'
        ];
    }

    public function store_configuration_paypal(Request $request)
    {
        $configuration = ConfigurationEcommerce::firstCached();
        if (!$configuration) return ['success' => false, 'message' => 'No existe configuración'];
        $configuration->fill($request->only([
            'script_paypal',
        ]));
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Paypal actualizada'
        ];
    }

    public function store_configuration_tag(Request $request)
    {
        $configuration = ConfigurationEcommerce::firstCached();
        if (!$configuration) return ['success' => false, 'message' => 'No existe configuración'];
        $configuration->fill($request->only([
            'tag_shipping', 'tag_dollar', 'tag_support',
            'title_one_customised_link', 'title_two_customised_link', 'title_three_customised_link',
            'customised_link_one', 'customised_link_two', 'customised_link_three',
        ]));
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración Tags actualizada'
        ];
    }

    public function store_configuration_social(Request $request)
    {
        $configuration = ConfigurationEcommerce::firstCached();
        if (!$configuration) return ['success' => false, 'message' => 'No existe configuración'];
        $configuration->fill($request->only([
            'link_youtube', 'link_twitter', 'link_facebook', 'link_tiktok', 'link_instagram',
            'phone_whatsapp',
            'whatsapp_api_token', 'whatsapp_phone_id', 'whatsapp_vendor_number',
            'google_client_id', 'google_client_secret', 'google_login_enabled',
        ]));
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de Redes Sociales actualizada'
        ];
    }
public function uploadFile(Request $request)
{
    if ($request->hasFile('file')) {

        $config = ConfigurationEcommerce::firstCached();
        $company = Company::first();

        // El 'type' viene de Vue — whitelist para evitar sobreescribir columnas arbitrarias
        $allowedTypes = ['logo_store', 'og_image', 'twitter_image', 'banner_image', 'favicon'];
        $type = $request->input('type');
        if (!in_array($type, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Tipo de imagen no permitido.'];
        }

        $file = $request->file('file');

        if (!$file->isValid() || empty($file->getPathname()) || !is_file($file->getPathname())) {
            return [
                'success' => false,
                'message' =>  __('app.actions.upload.error'),
            ];
        }

        $ext = $file->getClientOriginalExtension();
        $name = $type . '_' . $company->number . '.' . $ext;

        request()->validate(['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048']);

        UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);

        $stream = fopen($file->getPathname(), 'r');
        Storage::put('public/uploads/logos/' . $name, $stream);
        if (is_resource($stream)) fclose($stream);

        $columnMap = [
            'logo_store'   => 'logo',
            'og_image'     => 'og_image',
            'twitter_image'=> 'twitter_image',
            'banner_image' => 'banner_image',
            'favicon'      => 'favicon',
        ];
        $column = $columnMap[$type];
        $config->{$column} = $name;

        $config->save();

        return [
            'success' => true,
            'message' => __('app.actions.upload.success'),
            'name' => $name,
            'type' => $type
        ];
    }
    
    return [
        'success' => false,
        'message' =>  __('app.actions.upload.error'),
    ];
}

    public function store_configuration_links(Request $request)
    {
        $id = $request->input('id');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->fill($request->all());
        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de links personalizados actualizado'
        ];
    }

    public function store_configuration_color(Request $request)
    {

        $id = $request->input('id');
        $color = $request->input('color_ecommerce');
        $configuration = ConfigurationEcommerce::find($id);
        $configuration->color_ecommerce = $color;

        // Guardar preferencias (el cast a array maneja automáticamente el json_encode)
        $configuration->preferences = [
            'show_description' => (int) $request->input('show_description', 1),
            'show_stock' => (int) $request->input('show_stock', 0),
            'only_available_products' => (int) $request->input('only_available_products', 0),
            'full_width_banner' => (int) $request->input('full_width_banner', 0),
        ];

        $configuration->save();

        return [
            'success' => true,
            'message' => 'Configuración de color y preferencias actualizadas correctamente'
        ];
    }


    public function store_configuration_newsletter(Request $request)
    {
        $config = ConfigurationEcommerce::firstCached();
        if (!$config) {
            return ['success' => false, 'message' => 'No existe configuración'];
        }

        $config->newsletter_popup_enabled  = (bool) $request->input('newsletter_popup_enabled', false);
        $config->newsletter_popup_title    = $request->input('newsletter_popup_title');
        $config->newsletter_popup_desc     = $request->input('newsletter_popup_desc');
        $config->newsletter_discount_code  = $request->input('newsletter_discount_code');

        // Imagen opcional: si viene como base64 la guardamos en storage
        if ($request->filled('newsletter_popup_image') && str_starts_with($request->input('newsletter_popup_image'), 'data:image')) {
            $imageData = $request->input('newsletter_popup_image');
            $decoded   = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $imageData));

            // Verificar MIME real con finfo (no confiar en el header del data URI)
            $tmpFile = tempnam(sys_get_temp_dir(), 'nl_img_');
            file_put_contents($tmpFile, $decoded);
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);
            @unlink($tmpFile);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($realMime, $allowedMimes)) {
                return ['success' => false, 'message' => 'Tipo de imagen no permitido. Use JPG, PNG, GIF o WEBP.'];
            }
            $extMap    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $extension = $extMap[$realMime];
            $filename  = 'newsletter_' . time() . '.' . $extension;
            $imagePath = 'storage/uploads/logos/' . $filename;
            file_put_contents(public_path($imagePath), $decoded);
            $config->newsletter_popup_image = $filename;
        } elseif ($request->filled('newsletter_popup_image') && !str_starts_with($request->input('newsletter_popup_image'), 'data:image')) {
            // Se envió un nombre de archivo existente
            $config->newsletter_popup_image = $request->input('newsletter_popup_image');
        }

        $config->save();

        return [
            'success' => true,
            'message' => 'Configuración del pop-up de newsletter actualizada'
        ];
    }

    public function getColorEcommerce()
    {
        $config = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
        $color = $config ? $config->color_ecommerce : null;
        return response()->json(['color' => $color]);
    }

    public function store_configuration_pixels(Request $request)
    {
        $config = ConfigurationEcommerce::firstCached();
        if (!$config) {
            return ['success' => false, 'message' => 'No existe configuración'];
        }

        $config->facebook_pixel_id  = $request->input('facebook_pixel_id')  ?: null;
        if ($request->has('facebook_capi_token')) {
            $config->facebook_capi_token = $request->input('facebook_capi_token') ?: null;
        }
        $config->tiktok_pixel_id    = $request->input('tiktok_pixel_id')    ?: null;
        $config->ga4_measurement_id = $request->input('ga4_measurement_id') ?: null;
        $config->save();

        return ['success' => true, 'message' => 'Píxeles de publicidad guardados correctamente'];
    }

    public function test_capi_connection()
    {
        $capi = \App\Services\Tenant\FacebookConversionsApiService::fromConfig();

        if (!$capi) {
            return ['success' => false, 'message' => 'Configura el Pixel ID y el CAPI Token primero'];
        }

        return $capi->testConnection();
    }

    // ══════════════════════════════════════════════════════════════
    // MARKETPLACES CONFIGURATION
    // ══════════════════════════════════════════════════════════════

    public function get_marketplace_config()
    {
        $config = ConfigurationEcommerce::first();
        $data = $config ? ($config->marketplace_config ?? []) : [];

        return response()->json(['data' => $data]);
    }

    /**
     * Guardar theme y modo de ecommerce seleccionado por la empresa.
     */
    public function store_theme(Request $request)
    {
        $config = ConfigurationEcommerce::firstOrCreate([]);
        $oldThemeId = $config->theme_id;

        $config->theme_id       = $request->input('theme_id');
        $config->ecommerce_mode = $request->input('ecommerce_mode', 'general');
        $config->business_type  = $request->input('business_type');

        // Sincronizar theme_template con el CSS del theme seleccionado
        $themePath = 'default';
        if ($config->theme_id) {
            $theme = \App\Models\System\Theme::find($config->theme_id);
            if ($theme) {
                $config->theme_template = $theme->css_template ?? 'generic';
                $themePath = $theme->path;
            }
        } else {
            $config->theme_template = 'generic';
        }

        $config->save();
        $config->flushCache();

        // Disparar evento de cambio de theme
        $tenantUuid = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? '';
        event(new \App\Events\ThemeChanged($tenantUuid, $oldThemeId, $config->theme_id, $themePath));

        return ['success' => true, 'message' => 'Theme actualizado correctamente'];
    }

    /**
     * Obtener themes disponibles para la empresa.
     */
    public function available_themes()
    {
        $themes = \App\Models\System\Theme::availableForTenants()
            ->get(['id', 'name', 'slug', 'description', 'category', 'is_premium', 'preview_image', 'css_template']);

        $config = ConfigurationEcommerce::firstCached();

        return response()->json([
            'themes'          => $themes,
            'current_theme_id' => $config->theme_id,
            'ecommerce_mode'  => $config->ecommerce_mode ?? 'general',
            'business_type'   => $config->business_type,
        ]);
    }

    public function store_marketplace_config(Request $request)
    {
        $config = ConfigurationEcommerce::firstOrCreate([]);

        // Guardar como JSON encriptado en un campo
        $config->marketplace_config = [
            'falabella_active' => $request->boolean('falabella_active'),
            'falabella_user_id' => $request->input('falabella_user_id', ''),
            'falabella_api_key' => $request->input('falabella_api_key', ''),
            'falabella_api_url' => $request->input('falabella_api_url', 'https://sellercenter-api.falabella.com'),
            'mercadolibre_active' => $request->boolean('mercadolibre_active'),
            'mercadolibre_token' => $request->input('mercadolibre_token', ''),
            'mercadolibre_seller_id' => $request->input('mercadolibre_seller_id', ''),
            'meta_active' => $request->boolean('meta_active'),
            'meta_catalog_id' => $request->input('meta_catalog_id', ''),
            'meta_access_token' => $request->input('meta_access_token', ''),
            'tiktok_active' => $request->boolean('tiktok_active'),
            'tiktok_app_key' => $request->input('tiktok_app_key', ''),
            'tiktok_app_secret' => $request->input('tiktok_app_secret', ''),
        ];
        $config->save();

        // Actualizar canales de venta según configuración
        $this->syncMarketplaceChannels($config->marketplace_config);

        return ['success' => true, 'message' => 'Configuración de marketplaces guardada'];
    }

    protected function syncMarketplaceChannels(array $config): void
    {
        $channelMap = [
            'falabella' => [
                'field' => 'falabella_active', 'code' => 'SAGA', 'name' => 'Saga Falabella',
                'creds' => ['user_id' => 'falabella_user_id', 'api_key' => 'falabella_api_key', 'api_url' => 'falabella_api_url'],
            ],
            'mercadolibre' => [
                'field' => 'mercadolibre_active', 'code' => 'MELI', 'name' => 'MercadoLibre',
                'creds' => ['access_token' => 'mercadolibre_token', 'seller_id' => 'mercadolibre_seller_id'],
            ],
            'meta' => [
                'field' => 'meta_active', 'code' => 'FBSHOP', 'name' => 'Meta Commerce Feed',
                'creds' => ['catalog_id' => 'meta_catalog_id', 'access_token' => 'meta_access_token'],
            ],
            'tiktok' => [
                'field' => 'tiktok_active', 'code' => 'TIKTOK', 'name' => 'TikTok Shop',
                'creds' => ['app_key' => 'tiktok_app_key', 'app_secret' => 'tiktok_app_secret'],
            ],
        ];

        foreach ($channelMap as $platform => $info) {
            $isActive = $config[$info['field']] ?? false;

            // Actualizar SalesChannel
            \App\Models\Tenant\SalesChannel::where('code', $info['code'])
                ->update(['is_active' => $isActive ? 1 : 0]);

            // Crear o actualizar MarketplaceChannel
            $credentials = [];
            foreach ($info['creds'] as $credKey => $configKey) {
                $credentials[$credKey] = $config[$configKey] ?? '';
            }

            \App\Models\Tenant\MarketplaceChannel::updateOrCreate(
                ['platform' => $platform],
                [
                    'name' => $info['name'],
                    'status' => $isActive ? 'active' : 'inactive',
                    'credentials' => $credentials,
                ]
            );
        }
    }

    public function test_marketplace_connection(Request $request)
    {
        $platform = $request->input('platform');
        $creds = $request->input('credentials', []);

        try {
            if ($platform === 'falabella') {
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get($creds['falabella_api_url'] ?? 'https://sellercenter-api.falabella.com', [
                        'Action' => 'GetProducts',
                        'Format' => 'JSON',
                        'UserID' => $creds['falabella_user_id'] ?? '',
                        'Timestamp' => now()->toIso8601String(),
                        'Version' => '1.0',
                        'Signature' => hash_hmac('sha256', 'test', $creds['falabella_api_key'] ?? ''),
                    ]);
                return ['success' => $response->status() < 500];
            }

            if ($platform === 'mercadolibre') {
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($creds['mercadolibre_token'] ?? '')
                    ->get('https://api.mercadolibre.com/users/me');
                return ['success' => $response->successful()];
            }

            return ['success' => false, 'message' => 'Plataforma no soportada'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function regenerate_feed()
    {
        try {
            $config = ConfigurationEcommerce::first();
            $channel = \App\Models\Tenant\MarketplaceChannel::where('platform', 'meta')->first();

            if (!$channel) {
                $channel = \App\Models\Tenant\MarketplaceChannel::create([
                    'platform' => 'meta',
                    'name' => 'Meta Commerce Feed',
                    'status' => 'active',
                ]);
            }

            $service = new \App\Services\Marketplace\MetaFeedService($channel);
            $service->generateXmlFeed();
            $service->generateCsvFeed();

            return ['success' => true, 'message' => 'Feed generado correctamente', 'feeds' => [
                'xml' => asset('storage/feeds/meta-catalog.xml'),
                'csv' => asset('storage/feeds/meta-catalog.csv'),
            ]];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
