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
use Modules\Ecommerce\Http\Controllers\ConfigurationPixelController;
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
Route::get('/ecommerce/feed/csv',      [ProductFeedController::class, 'csvFeed'])->name('ecommerce.feed.csv');

// ========== Tracking público ==========
Route::get('/ecommerce/tracking', 'EcommerceController@tracking')->name('ecommerce.tracking');

// ========== Confirmación de pedido ==========
Route::get('/ecommerce/order/confirmation/{external_id}', '\Modules\Ecommerce\Http\Controllers\EcommerceController@orderConfirmation')
    ->name('ecommerce.order.confirmation')
    ->middleware(['locked.tenant']);

// ========== Stock notifications ==========
Route::post('/ecommerce/stock-notify', '\Modules\Ecommerce\Http\Controllers\StockNotificationController@subscribe')
    ->name('ecommerce.stock_notify')
    ->middleware('throttle:10,1');

// ========== Newsletter subscription ==========
Route::post('/ecommerce/newsletter-subscribe', '\Modules\Ecommerce\Http\Controllers\StockNotificationController@newsletterSubscribe')
    ->name('ecommerce.newsletter_subscribe')
    ->middleware('throttle:5,1');

// ========== Google OAuth (fuera del middleware de auth) ==========
Route::middleware(['locked.tenant'])->prefix('ecommerce')->group(function () {
    Route::get('auth/google', 'EcommerceController@googleRedirect')->name('ecommerce.google.redirect');
    Route::get('auth/google/callback', 'EcommerceController@googleCallback')->name('ecommerce.google.callback');
});

// ========== Checkout + Pago + Reviews — accesibles sin login ==========
Route::middleware(['locked.tenant'])->prefix('ecommerce')->group(function () {
    Route::get('checkout',       'EcommerceController@checkout')->name('tenant_ecommerce_checkout');
    Route::get('detail_cart',    'EcommerceController@detailCart')->name('tenant_detail_cart');
    Route::post('payment_cash',  'EcommerceController@paymentCash')->name('tenant_ecommerce_payment_cash');
    Route::post('culqi',         'CulqiController@payment')->name('tenant_ecommerce_culqui');
    Route::post('apply-coupon',  'EcommerceController@applyCoupon')->name('tenant_ecommerce_apply_coupon');
    Route::get('reviews/{id}',         'EcommerceController@getReviews')->name('ecommerce.reviews');
    Route::post('rating_item',         'EcommerceController@ratingItem')->name('tenant_ecommerce_rating_item');
    Route::get('rating_item/{id}',     'EcommerceController@getRating');
    // Ubigeo en cascada
    Route::get('ubigeo/departments',         'EcommerceController@ubigeoGetDepartments');
    Route::get('ubigeo/provinces/{dep_id}',  'EcommerceController@ubigeoGetProvinces');
    Route::get('ubigeo/districts/{prov_id}', 'EcommerceController@ubigeoGetDistricts');
});

Route::middleware(['check.permission', 'locked.tenant', 'check.email.verified'])->prefix('ecommerce')->group(function () {
    // Route::get('/', 'EcommerceController@index');

    Route::get('/', 'EcommerceController@index')->name('tenant.ecommerce.index');
    Route::get('/category/{category}', 'EcommerceController@category')->name('tenant.ecommerce.category');

  // ANTES
// Route::get('item/{id}/{promotion_id?}', 'EcommerceController@item')->name('tenant.ecommerce.item');

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
    Route::post('login', 'EcommerceController@login');
    Route::post('storeUser', 'EcommerceController@storeUser')->name('tenant_ecommerce_store_user');
    Route::get('color-ecommerce', 'ConfigurationController@getColorEcommerce');
    

    Route::get('terminos-condiciones', 'EcommerceController@terminosCondiciones')->name('tenant.terminos_condiciones');
    Route::get('cambios-devolucion', 'EcommerceController@cambiosDevolucion')->name('tenant.cambios_devolucion');
    Route::get('politica-privacidad', 'EcommerceController@politicaPrivacy')->name('tenant.politica_privacidad');
    Route::get('politica-envio', 'EcommerceController@politicaEnvio')->name('tenant.politica_envio');






    // culqi, payment_cash y apply-coupon movidos a grupo guest-accessible arriba
    Route::post('transaction_finally', 'EcommerceController@transactionFinally')->name('tenant_ecommerce_transaction_finally');

    Route::get('configuration', 'ConfigurationController@index')->middleware('redirect.module')->name('tenant_ecommerce_configuration');
    Route::post('configuration', 'ConfigurationController@store_configuration');
    Route::post('configuration_culqui', 'ConfigurationController@store_configuration_culqui');
    Route::post('configuration_paypal', 'ConfigurationController@store_configuration_paypal');
    Route::post('configuration_social', 'ConfigurationController@store_configuration_social');
    Route::post('configuration_tags', 'ConfigurationController@store_configuration_tag');
    Route::post('configuration_color', 'ConfigurationController@store_configuration_color');
    Route::post('configuration_newsletter', 'ConfigurationController@store_configuration_newsletter');
    Route::get('profile', 'EcommerceController@profile')->name('tenant.ecommerce.profile');
    Route::post('saveDataUser', 'EcommerceController@saveDataUser')->name('tenant_ecommerce_user_data');
    Route::post('change-password', 'EcommerceController@changePassword')->name('tenant.ecommerce.change_password');
    Route::post('configuration_links', 'ConfigurationController@store_configuration_links');


    Route::post('configuration/seo', 'ConfigurationController@store_configuration_seo');


    /*terminos y condiciones  */
    Route::post('configuration_terms', 'ConfigurationController@store_configuration_terms');

        
    Route::get('libro-reclamaciones', 'EcommerceController@libroReclamaciones')->name('tenant.libro_reclamaciones');
    Route::post('libro-reclamaciones', 'EcommerceController@enviarReclamo')->name('tenant.libro_reclamaciones_enviar');


    Route::get('record', 'ConfigurationController@record');

    // Programa de puntos
    Route::get('points', 'EcommerceController@pointsBalance')->name('tenant.ecommerce.points');

    // Bundle/Pack landing page
    Route::get('bundle/{slug}', 'EcommerceController@bundleLanding')->name('tenant.ecommerce.bundle');

    // Flash Sales
    Route::get('flash-sales', 'FlashSaleController@index')->name('tenant.ecommerce.flash_sales');
    Route::get('flash-sales/records', 'FlashSaleController@records');
    Route::post('flash-sales', 'FlashSaleController@store');
    Route::put('flash-sales/{id}', 'FlashSaleController@update');
    Route::delete('flash-sales/{id}', 'FlashSaleController@destroy');

    // Cupones
    Route::get('coupons', 'CouponController@index')->name('tenant.ecommerce.coupons');
    Route::get('coupons/records', 'CouponController@records');
    Route::post('coupons', 'CouponController@store');
    Route::put('coupons/{id}', 'CouponController@update');
    Route::delete('coupons/{id}', 'CouponController@destroy');

    // Avisos de Stock (admin)
    Route::get('stock-notifications', 'StockNotificationController@adminIndex')->name('tenant.ecommerce.stock_notifications');
    Route::get('stock-notifications/records', 'StockNotificationController@adminRecords');
    Route::post('stock-notifications/send', 'StockNotificationController@adminSend');
    Route::delete('stock-notifications/{id}', 'StockNotificationController@adminDestroy');

    Route::post('uploads', 'ConfigurationController@uploadFile');


    // configuration pixel    

    // Modules/Ecommerce/Routes/web.php (o api.php según tu proyecto)
    // Route::get('configuration/pixels', 'ConfigurationPixelController@index')->name('tenant.ecommerce.configuration.pixels');
    // Route::post('configuration/pixels', 'ConfigurationPixelController@store')->name('tenant.ecommerce.configuration.pixels.store');
  
    Route::get('social-scripts', 'ConfigurationController@getSocialScripts');
    Route::post('social-scripts/save-all', 'ConfigurationController@saveSocialScripts');

    //Item Sets
    Route::prefix('item-sets')->group(function () {

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


Route::middleware(['locked.tenant'])->group(function () {
    // ecommerce
    Route::get('/ecommerce/{name?}', 'EcommerceController@index');
});
