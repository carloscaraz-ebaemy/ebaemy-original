<?php
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Modules\Ecommerce\Http\Controllers\ConfigurationController;
use Modules\Ecommerce\Http\Controllers\EcommerceController;
use Modules\Ecommerce\Http\Controllers\ProductFeedController;



// ========== PWA ==========
Route::get('/ecommerce/manifest.json', 'EcommerceController@manifest')->name('ecommerce.manifest');
Route::get('/ecommerce/offline',       'EcommerceController@offline')->name('ecommerce.offline');

// ========== SEO - Rutas públicas (sin auth ni middleware) ==========
Route::get('/sitemap.xml', '\Modules\Ecommerce\Http\Controllers\SitemapController@index');
Route::get('/robots.txt', '\Modules\Ecommerce\Http\Controllers\RobotsController@index');
Route::get('/ecommerce/sitemap.xml', '\Modules\Ecommerce\Http\Controllers\SitemapController@index');

// ========== Product Feeds ==========
Route::get('/ecommerce/feed/google',   [ProductFeedController::class, 'googleMerchant'])->name('ecommerce.feed.google');
Route::get('/ecommerce/feed/facebook', [ProductFeedController::class, 'facebookCatalog'])->name('ecommerce.feed.facebook');
Route::get('/ecommerce/feed/tiktok',   [ProductFeedController::class, 'tiktokCatalog'])->name('ecommerce.feed.tiktok');
Route::get('/ecommerce/feed/csv',      [ProductFeedController::class, 'csvFeed'])->name('ecommerce.feed.csv');

// ========== Social Proof ==========
Route::get('/ecommerce/social-proof', function () {
    return response()->json(app(\App\Services\Tenant\SocialProofService::class)->getRecentPurchases());
});

// ========== Tracking público ==========
Route::get('/ecommerce/tracking', 'EcommerceController@tracking')
    ->name('ecommerce.tracking')
    ->middleware('throttle:15,1');

// ========== Confirmación de pedido ==========
Route::get('/ecommerce/order/confirmation/{external_id}', '\Modules\Ecommerce\Http\Controllers\EcommerceController@orderConfirmation')
    ->name('ecommerce.order.confirmation')
    ->middleware(['locked.tenant', 'set.theme']);

// ========== Estado de pago (polling L2 pre-auth Culqi) ==========
Route::get('/ecommerce/order/{external_id}/payment-status', '\Modules\Ecommerce\Http\Controllers\EcommerceController@paymentStatus')
    ->name('ecommerce.order.payment-status')
    ->middleware(['locked.tenant', 'throttle:30,1']);

// ========== Price history ==========
Route::get('/ecommerce/price-history/{itemId}', function ($itemId) {
    $history = \App\Models\Tenant\ItemPriceHistory::where('item_id', $itemId)
        ->orderByDesc('created_at')
        ->limit(50)
        ->get(['old_price', 'new_price', 'source', 'created_at']);
    return response()->json($history);
})->middleware('throttle:30,1');

// ========== Stock notifications ==========
Route::post('/ecommerce/stock-notify', '\Modules\Ecommerce\Http\Controllers\StockNotificationController@subscribe')
    ->name('ecommerce.stock_notify')
    ->middleware('throttle:10,1');

// ========== Newsletter subscription ==========
Route::post('/ecommerce/newsletter-subscribe', '\Modules\Ecommerce\Http\Controllers\StockNotificationController@newsletterSubscribe')
    ->name('ecommerce.newsletter_subscribe')
    ->middleware('throttle:5,1');

// ========== Google OAuth (fuera del middleware de auth) ==========
Route::middleware(['locked.tenant', 'set.theme'])->prefix('ecommerce')->group(function () {
    Route::get('auth/google', 'EcommerceController@googleRedirect')->name('ecommerce.google.redirect');
    Route::get('auth/google/callback', 'EcommerceController@googleCallback')->name('ecommerce.google.callback');
});

// ========== Checkout + Pago + Reviews — accesibles sin login ==========
Route::middleware(['locked.tenant', 'set.theme'])->prefix('ecommerce')->group(function () {
    Route::get('checkout',       'EcommerceController@checkout')->name('tenant_ecommerce_checkout');
    Route::get('detail_cart',    'EcommerceController@detailCart')->name('tenant_detail_cart');
    Route::post('payment_cash',  'EcommerceController@paymentCash')->name('tenant_ecommerce_payment_cash')->middleware('throttle:10,1');
    Route::post('culqi',         'CulqiController@payment')->name('tenant_ecommerce_culqui')->middleware('throttle:10,1');
    Route::post('apply-coupon',  'EcommerceController@applyCoupon')->name('tenant_ecommerce_apply_coupon')->middleware('throttle:20,1');
    Route::post('preview-discounts',  'EcommerceController@previewDiscounts')->name('tenant_ecommerce_preview_discounts');
    Route::get('reviews/{id}',         'EcommerceController@getReviews')->name('ecommerce.reviews');
    Route::post('rating_item',         'EcommerceController@ratingItem')->name('tenant_ecommerce_rating_item')->middleware('throttle:10,1');
    Route::get('rating_item/{id}',     'EcommerceController@getRating');
    // Ubigeo en cascada
    Route::get('ubigeo/departments',         'EcommerceController@ubigeoGetDepartments');
    Route::get('ubigeo/provinces/{dep_id}',  'EcommerceController@ubigeoGetProvinces');
    Route::get('ubigeo/districts/{prov_id}', 'EcommerceController@ubigeoGetDistricts');

    // Carrito abandonado — persistencia y restauración
    Route::post('cart/save',    'EcommerceController@saveCart')->name('ecommerce.cart.save')->middleware('throttle:30,1');
    Route::get('cart/restore',  'EcommerceController@restoreCart')->name('ecommerce.cart.restore');

    // Real-time stock validation for cart
    Route::post('stock-check',  'EcommerceController@stockCheck')->name('ecommerce.stock_check')->middleware('throttle:20,1');

    // Shipping calculator — cost por distrito/pickup
    Route::post('calculate-shipping', 'EcommerceController@calculateShipping')
         ->name('ecommerce.calculate_shipping')
         ->middleware('throttle:30,1');

    // Ubigeo autocomplete (cached, public)
    Route::get('ubigeo-search', 'EcommerceController@ubigeoSearch')->middleware('throttle:10,1');
});

// ========== Rutas PÚBLICAS del ecommerce (accesibles por Googlebot y visitantes) ==========
Route::middleware(['locked.tenant', 'set.theme'])->prefix('ecommerce')->group(function () {
    Route::get('/', 'EcommerceController@index')->name('tenant.ecommerce.index');
    Route::get('/category/{category}', 'EcommerceController@category')->name('tenant.ecommerce.category');

    Route::get('item/{id}/{promotion_id?}', function ($id, $promotion_id = null) {
        $item = \App\Models\Tenant\Item::findOrFail($id);
        $params = ['slug' => $item->slug];
        if ($promotion_id) $params['promotion_id'] = $promotion_id;
        return redirect()->route('tenant.ecommerce.item', $params, 301);
    })->where('id', '[0-9]+');

    Route::get('item/{slug}/{promotion_id?}', 'EcommerceController@item')
        ->name('tenant.ecommerce.item')
        ->where('slug', '[a-z0-9-]+');

    Route::get('items', 'EcommerceController@items')->name('tenant.ecommerce.item.index');
    Route::get('compare', 'EcommerceController@compare')->name('tenant.ecommerce.compare');
    Route::get('api/items-compare', 'EcommerceController@itemsForCompare');
    Route::get('api/search', 'EcommerceController@advancedSearch');
});

// ========== Rutas AUTENTICADAS del ecommerce (panel del usuario logueado) ==========
Route::middleware(['check.permission', 'locked.tenant', 'check.email.verified', 'set.theme'])->prefix('ecommerce')->group(function () {
    Route::get('item_partial/{id}', 'EcommerceController@partialItem')->name('item_partial');
    Route::get('item_quick/{id}', 'EcommerceController@quickView')->name('ecommerce.quick_view');
    // detail_cart y checkout movidos a grupo guest-accessible arriba
    Route::get('wishlist', function () {
        $company = \App\Models\Tenant\Company::first();
        return view('ecommerce::wishlist.index', compact('company'));
    })->name('tenant.ecommerce.wishlist');
    Route::get('document_list', 'EcommerceController@documentList')->name('tenant_document_list');
    Route::get('documents', 'EcommerceController@documents')->name('tenant_document');
    Route::get('orders', 'EcommerceController@orders')->name('tenant_orders');

    Route::get('order_list', 'EcommerceController@orderList')->name('tenant_order_list');
    Route::get('pay_cart', 'EcommerceController@pay')->name('tenant_pay_cart');
    Route::get('login', 'EcommerceController@showLogin')->name('tenant_ecommerce_login');
    Route::post('logout', 'EcommerceController@logout')->name('tenant_ecommerce_logout');
    Route::get('items_bar', 'EcommerceController@itemsBar');
    Route::post('login', 'EcommerceController@login')->middleware('throttle:5,3');
    Route::post('storeUser', 'EcommerceController@storeUser')->name('tenant_ecommerce_store_user')->middleware('throttle:5,1');
    Route::get('color-ecommerce', 'ConfigurationController@getColorEcommerce');
    

    Route::get('terminos-condiciones', 'EcommerceController@terminosCondiciones')->name('tenant.terminos_condiciones');
    Route::get('cambios-devolucion', 'EcommerceController@cambiosDevolucion')->name('tenant.cambios_devolucion');
    Route::get('politica-privacidad', 'EcommerceController@politicaPrivacy')->name('tenant.politica_privacidad');
    Route::get('politica-envio', 'EcommerceController@politicaEnvio')->name('tenant.politica_envio');






    // culqi, payment_cash y apply-coupon movidos a grupo guest-accessible arriba
    Route::post('transaction_finally', 'EcommerceController@transactionFinally')->name('tenant_ecommerce_transaction_finally');

    Route::middleware(['auth', 'redirect.module'])->group(function () {
        Route::get('configuration', 'ConfigurationController@index')->name('tenant_ecommerce_configuration');
        Route::post('configuration', 'ConfigurationController@store_configuration');
        Route::post('configuration_culqui', 'ConfigurationController@store_configuration_culqui');
        Route::post('configuration_mercadopago', 'ConfigurationController@store_configuration_mercadopago');
        Route::post('configuration_paypal', 'ConfigurationController@store_configuration_paypal');
        Route::post('configuration_social', 'ConfigurationController@store_configuration_social');
        Route::post('configuration_tags', 'ConfigurationController@store_configuration_tag');
        Route::post('configuration_color', 'ConfigurationController@store_configuration_color');
        Route::get('configuration_themes', 'ConfigurationController@available_themes');
        Route::post('configuration_theme', 'ConfigurationController@store_theme');
        Route::get('themes', 'ConfigurationController@themesGallery')->name('tenant.ecommerce.themes');
        Route::get('plugins', 'ConfigurationController@pluginsView')->name('tenant.ecommerce.plugins');
        Route::get('notifications', 'ConfigurationController@notificationsView')->name('tenant.ecommerce.notifications');
        Route::post('configuration_notifications', 'ConfigurationController@store_notifications');
        Route::post('test_whatsapp', 'ConfigurationController@testWhatsApp');
        Route::post('configuration_newsletter', 'ConfigurationController@store_configuration_newsletter');
        Route::get('configuration_marketplaces', 'ConfigurationController@get_marketplace_config');
        Route::post('configuration_marketplaces', 'ConfigurationController@store_marketplace_config');
        Route::post('test_marketplace_connection', 'ConfigurationController@test_marketplace_connection');
        Route::post('regenerate_feed', 'ConfigurationController@regenerate_feed');
        Route::post('configuration_links', 'ConfigurationController@store_configuration_links');
        Route::post('configuration/seo', 'ConfigurationController@store_configuration_seo');
        Route::post('configuration_terms', 'ConfigurationController@store_configuration_terms');
        Route::get('record', 'ConfigurationController@record');
        Route::post('uploads', 'ConfigurationController@uploadFile');
        Route::post('configuration/pixels', 'ConfigurationController@store_configuration_pixels');
        Route::post('configuration/pixels/test-capi', 'ConfigurationController@test_capi_connection');
        Route::get('social-scripts', 'ConfigurationController@getSocialScripts');
        Route::post('social-scripts/save-all', 'ConfigurationController@saveSocialScripts');
    });
    Route::get('profile', 'EcommerceController@profile')->name('tenant.ecommerce.profile');
    Route::post('saveDataUser', 'EcommerceController@saveDataUser')->name('tenant_ecommerce_user_data');
    Route::post('change-password', 'EcommerceController@changePassword')->name('tenant.ecommerce.change_password');
    Route::get('referral', 'EcommerceController@referralInfo')->name('tenant.ecommerce.referral');
    /*terminos y condiciones  */
    Route::get('libro-reclamaciones', 'EcommerceController@libroReclamaciones')->name('tenant.libro_reclamaciones');
    Route::post('libro-reclamaciones', 'EcommerceController@enviarReclamo')->name('tenant.libro_reclamaciones_enviar')->middleware('throttle:5,1');

    // Programa de puntos
    Route::get('points', 'EcommerceController@pointsBalance')->name('tenant.ecommerce.points');

    // Bundle/Pack landing page
    Route::get('bundle/{slug}', 'EcommerceController@bundleLanding')->name('tenant.ecommerce.bundle');

    // Flash Sales (requiere auth)
    Route::middleware('auth')->group(function () {
        Route::get('flash-sales', 'FlashSaleController@index')->name('tenant.ecommerce.flash_sales');
        Route::get('flash-sales/records', 'FlashSaleController@records');
        Route::post('flash-sales', 'FlashSaleController@store');
        Route::put('flash-sales/{id}', 'FlashSaleController@update');
        Route::post('flash-sales/{id}/send-whatsapp', 'FlashSaleController@sendWhatsApp');
        Route::delete('flash-sales/{id}', 'FlashSaleController@destroy');

        // Campanas WhatsApp
        Route::get('whatsapp-campaigns', 'WhatsAppCampaignController@index')->name('tenant.ecommerce.whatsapp_campaigns');
        Route::get('whatsapp-campaigns/records', 'WhatsAppCampaignController@records');
        Route::get('whatsapp-campaigns/{id}/messages', 'WhatsAppCampaignController@messages');
        Route::post('whatsapp-campaigns/{id}/retry-failed', 'WhatsAppCampaignController@retryFailed');

        // Cupones
        Route::get('coupons', 'CouponController@index')->name('tenant.ecommerce.coupons');
        Route::get('coupons/records', 'CouponController@records');
        Route::post('coupons', 'CouponController@store');
        Route::put('coupons/{id}', 'CouponController@update');
        Route::patch('coupons/{id}/toggle-active', 'CouponController@toggleActive');
        Route::delete('coupons/{id}', 'CouponController@destroy');

        // Avisos de Stock (admin)
        Route::get('stock-notifications', 'StockNotificationController@adminIndex')->name('tenant.ecommerce.stock_notifications');
        Route::get('stock-notifications/records', 'StockNotificationController@adminRecords');
        Route::post('stock-notifications/send', 'StockNotificationController@adminSend');
        Route::delete('stock-notifications/{id}', 'StockNotificationController@adminDestroy');

        // Marketplace — productos y canales
        Route::get('marketplace', '\App\Http\Controllers\Tenant\MarketplaceController@index')->name('tenant.ecommerce.marketplace');
        Route::get('marketplace/channels', '\App\Http\Controllers\Tenant\MarketplaceController@channels');
        Route::get('marketplace/channels/{channelId}/products', '\App\Http\Controllers\Tenant\MarketplaceController@products');
        Route::post('marketplace/channels/{channelId}/sync-products', '\App\Http\Controllers\Tenant\MarketplaceController@syncProducts');
        Route::post('marketplace/channels/{channelId}/sync-stock', '\App\Http\Controllers\Tenant\MarketplaceController@syncStock');
        Route::post('marketplace/channels/{channelId}/auto-map', '\App\Http\Controllers\Tenant\MarketplaceController@autoMapProducts');
        Route::post('marketplace/map-product', '\App\Http\Controllers\Tenant\MarketplaceController@mapProduct');
        Route::post('marketplace/orders/{id}/convert', '\App\Http\Controllers\Tenant\MarketplaceController@convertToOrder');
        Route::get('marketplace/products-by-channel', '\App\Http\Controllers\Tenant\MarketplaceController@productsByChannel')->name('tenant.ecommerce.marketplace.products');
        Route::post('marketplace/channels/{channelId}/save-products', '\App\Http\Controllers\Tenant\MarketplaceController@saveChannelProducts');
        Route::get('items_ecommerce/records_all', '\App\Http\Controllers\Tenant\ItemController@recordsAllSimple');
        Route::get('marketplace/orders', '\App\Http\Controllers\Tenant\MarketplaceController@orders');
        Route::get('marketplace/channels/{channelId}/fetch-orders', '\App\Http\Controllers\Tenant\MarketplaceController@fetchOrders');
    });

    //Item Sets
    Route::prefix('item-sets')->middleware(['auth', 'redirect.module'])->group(function () {

        Route::get('', 'ItemSetController@index')->name('tenant.ecommerce.item_sets.index')->middleware('redirect.level');
        Route::get('columns', 'ItemSetController@columns');
        Route::get('records', 'ItemSetController@records');
        Route::get('tables', 'ItemSetController@tables');
        Route::get('record/{item}', 'ItemSetController@record');
        Route::post('', 'ItemSetController@store');
        Route::delete('{item}', 'ItemSetController@destroy');
        Route::delete('item-unit-type/{item}', 'ItemSetController@destroyItemUnitType');
        Route::post('import', 'ItemSetController@import');
        Route::post('upload', 'ItemSetController@upload');
        Route::post('visible_store', 'ItemSetController@visibleStore');
        Route::get('item/tables', 'ItemSetController@item_tables');
    });
});


// Fallback eliminado — las rutas públicas del ecommerce ya están en el grupo sin auth arriba
