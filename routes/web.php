<?php

use Illuminate\Support\Facades\Route;
use App\Models\System\Configuration;

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if ($hostname) {
    Route::domain($hostname->fqdn)->group(function () {
        Auth::routes([
            'register' => false,
            'verify'   => false
        ]);

        // ── Búsqueda de documentos — requiere autenticación ─────────────
        Route::middleware('auth')->group(function () {
            Route::get('search', 'Tenant\SearchController@index')->name('search.index');
            Route::get('buscar', 'Tenant\SearchController@index');
            Route::get('search/tables', 'Tenant\SearchController@tables');
            Route::post('search', 'Tenant\SearchController@store');
        });

        // ── Impresión/descarga de documentos — auth O URL firmada ────────
        // Los links por email/WhatsApp usan URL firmada; el panel usa auth
        Route::middleware('auth.or.signed')->group(function () {
            Route::get('downloads/{model}/{type}/{external_id}/{format?}', 'Tenant\DownloadController@downloadExternal')->name('tenant.download.external_id');
            Route::get('print/{model}/{external_id}/{format}/{filename?}', 'Tenant\DownloadController@toPrint')->name('tenant.print');
            Route::get('print/{model}/{external_id}/{format?}', 'Tenant\DownloadController@toPrint');
            Route::get('printticket/{model}/{external_id}/{format?}', 'Tenant\DownloadController@toTicket')->where('external_id', '[0-9]+');
            Route::get('sale-notes/print/{external_id}/{format?}', 'Tenant\SaleNoteController@toPrint')->name('tenant.salenote.print');
            Route::get('sale-notes/ticket/{id}/{format?}', 'Tenant\SaleNoteController@toTicket')->where('id', '[0-9]+');
            Route::get('purchases/print/{external_id}/{format?}', 'Tenant\PurchaseController@toPrint');
            Route::get('quotations/print/{external_id}/{format?}', 'Tenant\QuotationController@toPrint');
        });

        // ── Redirect raíz (/) según autenticación ────────────────────────
        // Visitantes anónimos → storefront ecommerce (/ecommerce).
        // Usuarios autenticados del panel → su dashboard (HomeController lo resuelve).
        // Si el tenant no tiene ecommerce, el EcommerceController maneja fallback a login.
        Route::get('/', function () {
            if (auth()->check()) {
                return redirect('/dashboard');
            }
            return redirect('/ecommerce');
        });

        // ── Rutas públicas legítimas (rate-limited) ─────────────────────
        Route::get('/exchange_rate/ecommence/{date}', 'Tenant\Api\ServiceController@exchangeRateTest')
             ->middleware('throttle:30,1');

        // ── Tracking público de pedidos (sin auth, rate-limited) ────────
        Route::get('logistic/tracking', [\App\Http\Controllers\Tenant\Logistic\TrackingController::class, 'index'])
             ->name('logistic.tracking')
             ->middleware('throttle:20,1');
        // Route::get('/ecommerce/color-ecommerce', [\App\Http\Controllers\Tenant\ConfigurationController::class, 'getColorEcommerce']);

        Route::middleware(['auth', 'redirect.module', 'locked.tenant','check.email.verified'])->group(function () {
            // Route::get('catalogs', 'Tenant\CatalogController@index')->name('tenant.catalogs.index');
            Route::get('list-reports', 'Tenant\SettingController@listReports');
            Route::get('list-extras', 'Tenant\SettingController@listExtras');
            Route::get('list-settings', 'Tenant\SettingController@indexSettings')->name('tenant.general_configuration.index');
            Route::get('list-banks', 'Tenant\SettingController@listBanks');
            Route::get('list-bank-accounts', 'Tenant\SettingController@listAccountBanks');
            Route::get('list-currencies', 'Tenant\SettingController@listCurrencies');
            Route::get('list-cards', 'Tenant\SettingController@listCards');
            Route::get('list-platforms', 'Tenant\SettingController@listPlatforms');
            Route::get('list-attributes', 'Tenant\SettingController@listAttributes');
            Route::get('list-detractions', 'Tenant\SettingController@listDetractions');
            Route::get('list-units', 'Tenant\SettingController@listUnits');
            Route::get('list-payment-methods', 'Tenant\SettingController@listPaymentMethods');
            Route::get('list-incomes', 'Tenant\SettingController@listIncomes');
            Route::get('list-payments', 'Tenant\SettingController@listPayments');
            Route::get('list-vouchers-type', 'Tenant\SettingController@listVouchersType');
            Route::get('list-transfer-reason-types', 'Tenant\SettingController@listTransferReasonTypes');
            Route::get('list-item-affectations', 'Tenant\SettingController@listItemAffectations');

            Route::get('advanced', 'Tenant\AdvancedController@index')->name('tenant.advanced.index')->middleware('redirect.level');

            Route::get('tasks', 'Tenant\TaskController@index')->name('tenant.tasks.index')->middleware('redirect.level');
            Route::post('tasks/commands', 'Tenant\TaskController@listsCommand');
            Route::post('tasks/tables', 'Tenant\TaskController@tables');
            Route::post('tasks', 'Tenant\TaskController@store');
            Route::delete('tasks/{task}', 'Tenant\TaskController@destroy');

            //Orders
            Route::get('orders', 'Tenant\OrderController@index')->name('tenant_orders_index');
            Route::get('orders/columns', 'Tenant\OrderController@columns');
            Route::get('orders/stats', 'Tenant\OrderController@stats');
            Route::get('orders/channels', 'Tenant\OrderController@channels');
            Route::get('orders/channel-report', 'Tenant\OrderController@channelReport');
            Route::post('orders/manual', 'Tenant\OrderController@storeManual');

            // Reglas de descuento automático
            Route::get('discount-rules',                'Tenant\DiscountRuleController@index')->name('tenant.discount_rules.index');
            Route::get('discount-rules/records',        'Tenant\DiscountRuleController@records');
            Route::get('discount-rules/tables',         'Tenant\DiscountRuleController@tables');
            Route::get('discount-rules/items/search',   'Tenant\DiscountRuleController@searchItems');
            Route::post('discount-rules',               'Tenant\DiscountRuleController@store');
            Route::delete('discount-rules/{id}',        'Tenant\DiscountRuleController@destroy');
            Route::post('discount-rules/{id}/toggle',   'Tenant\DiscountRuleController@toggle');

            // Webhooks
            Route::prefix('webhooks')->group(function () {
                Route::get('/', 'Tenant\WebhookController@index');
                Route::get('/records', 'Tenant\WebhookController@records');
                Route::get('/tables', 'Tenant\WebhookController@tables');
                Route::post('/', 'Tenant\WebhookController@store');
                Route::delete('/{id}', 'Tenant\WebhookController@destroy');
                Route::post('/{id}/toggle', 'Tenant\WebhookController@toggle');
                Route::get('/{id}/logs', 'Tenant\WebhookController@logs');
                Route::post('/{id}/test', 'Tenant\WebhookController@test');
            });

            // Product Reviews
            Route::prefix('reviews')->group(function () {
                Route::get('/', 'Tenant\ProductReviewController@index');
                Route::post('/', 'Tenant\ProductReviewController@store');
                Route::get('/product/{itemId}', 'Tenant\ProductReviewController@forProduct');
                Route::post('/{id}/moderate', 'Tenant\ProductReviewController@moderate');
            });

            // Ecommerce Reports
            Route::prefix('reports/ecommerce')->group(function () {
                Route::get('/', 'Tenant\EcommerceReportController@index');
                Route::get('/kpis', 'Tenant\EcommerceReportController@kpis');
                Route::get('/daily-sales', 'Tenant\EcommerceReportController@dailySales');
                Route::get('/top-products', 'Tenant\EcommerceReportController@topProducts');
                Route::get('/channels', 'Tenant\EcommerceReportController@channelBreakdown');
                Route::get('/abandoned-carts', 'Tenant\EcommerceReportController@abandonedCartAnalysis');
                Route::get('/sales-by-hour', 'Tenant\EcommerceReportController@salesByHour');
                Route::get('/customer-ltv', 'Tenant\EcommerceReportController@customerLtv');
            });

            // CEO Dashboard
            Route::prefix('dashboard/ceo')->group(function () {
                Route::get('/kpis', function () {
                    return response()->json(app(\App\Services\Tenant\CeoDashboardService::class)->getStrategicKpis(request('establishment_id')));
                });
                Route::get('/cohorts', function () {
                    return response()->json(app(\App\Services\Tenant\CeoDashboardService::class)->getCustomerCohorts());
                });
            });

            // Recommendations
            Route::prefix('recommendations')->group(function () {
                Route::get('/fbt/{itemId}', function ($itemId) {
                    return response()->json(app(\App\Services\Tenant\RecommendationService::class)->frequentlyBoughtTogether((int)$itemId));
                });
                Route::get('/category/{itemId}', function ($itemId) {
                    return response()->json(app(\App\Services\Tenant\RecommendationService::class)->topInCategory((int)$itemId));
                });
                Route::get('/trending', function () {
                    return response()->json(app(\App\Services\Tenant\RecommendationService::class)->trending());
                });
            });

            Route::get('orders/records', 'Tenant\OrderController@records');
            Route::get('orders/record/{order}', 'Tenant\OrderController@record');
            Route::get('orders/{order}/status-logs', 'Tenant\OrderController@statusLogs')->where('order', '[0-9]+');
            Route::get('orders/payment-catalogs', 'Tenant\OrderController@paymentCatalogs');

            // WhatsApp — Configuración y panel de control
            // RBAC: whatsapp.view (ver), whatsapp.config (editar), whatsapp.send_test (probar).
            // super-admin y type=admin legacy bypasean (ver CheckPermission middleware).
            Route::middleware('permission:whatsapp.view')->group(function () {
                Route::get('whatsapp/settings', 'Tenant\WhatsAppSettingsController@index');
                Route::get('whatsapp/settings/data', 'Tenant\WhatsAppSettingsController@data');
                Route::get('whatsapp/settings/templates', 'Tenant\WhatsAppSettingsController@templates');
                Route::get('whatsapp/settings/logs', 'Tenant\WhatsAppSettingsController@logs');
                Route::get('whatsapp/dashboard', 'Tenant\WhatsAppSettingsController@dashboardIndex');
                Route::get('whatsapp/dashboard/data', 'Tenant\WhatsAppSettingsController@dashboardData');
            });
            Route::middleware('permission:whatsapp.config')->group(function () {
                Route::put('whatsapp/settings', 'Tenant\WhatsAppSettingsController@update');
            });
            Route::middleware('permission:whatsapp.send_test')->group(function () {
                Route::post('whatsapp/settings/test', 'Tenant\WhatsAppSettingsController@test');
            });
            //Route::get('orders/print/{external_id}/{format?}', 'Tenant\OrderController@toPrint');
            Route::post('statusOrder/update/', 'Tenant\OrderController@updateStatusOrders');
            Route::get('orders/pdf/{id}', 'Tenant\OrderController@pdf')->where('id', '[0-9]+');

            //warehouse
            Route::post('orders/warehouse', 'Tenant\OrderController@searchWarehouse');
            Route::get('orders/tables', 'Tenant\OrderController@tables');

            // ─── Sistema Logístico — Cola del Almacén ──────────────────────────────
            Route::prefix('logistic')->group(function () {
                // Panel Vue del almacenero
                Route::get('warehouse-queue', function () {
                    $tenancy = app(\Hyn\Tenancy\Environment::class);
                    return view('tenant.logistic.warehouse_queue', [
                        'tenant_uuid'  => $tenancy->tenant()?->uuid ?? '',
                        'warehouse_id' => auth()->user()?->establishment?->warehouse?->id,
                    ]);
                })->name('logistic.warehouse_queue');

                // ── Cola de Notas de Venta — módulo almacén ──────────────────
                // Roles: admin, warehouse
                Route::prefix('sale-notes')->group(function () {
                    Route::get('queue', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'index'])
                         ->name('logistic.sale_notes.queue');
                    Route::get('queue-count', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'queueCount'])
                         ->name('logistic.sale_notes.queue_count');

                    Route::get('queue/labels/batch', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'printBatchLabels'])
                         ->name('logistic.sale_notes.labels_batch');
                    Route::get('queue/{saleNote}/label-html', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'labelHtml'])
                         ->name('logistic.sale_notes.label_html');

                    Route::get('queue/{saleNote}', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'show'])
                         ->name('logistic.sale_notes.show');
                    Route::get('queue/{saleNote}/detail', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'detail'])
                         ->name('logistic.sale_notes.detail');
                    Route::get('stock-by-item/{item}', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'stockByItem'])
                         ->name('logistic.stock_by_item');

                    Route::post('queue/{saleNote}/process', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'process'])
                         ->name('logistic.sale_notes.process');

                    Route::post('queue/{saleNote}/ready', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'ready'])
                         ->name('logistic.sale_notes.ready');

                    Route::post('queue/{saleNote}/dispatch', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'dispatchOrder'])
                         ->name('logistic.sale_notes.dispatch');
                    Route::post('queue/{saleNote}/pickup', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'confirmPickup'])
                         ->name('logistic.sale_notes.pickup');
                    Route::post('queue/{saleNote}/cancel', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'cancelPreparation'])
                         ->name('logistic.sale_notes.cancel');
                    Route::post('queue/{saleNote}/annul', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'annulDispatch'])
                         ->name('logistic.sale_notes.annul');

                    Route::get('history', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'history'])
                         ->name('logistic.sale_notes.history');
                    Route::get('queue/{saleNote}/label', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'printLabel'])
                         ->name('logistic.sale_notes.label');
                    Route::get('shipping-guide/{guide}/pdf', [\App\Http\Controllers\Tenant\Logistic\WarehouseDispatchController::class, 'downloadGuidePdf'])
                         ->name('logistic.shipping_guide.pdf');
                });

                // Gestión de couriers (admin)
                Route::prefix('courier-companies')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Tenant\Logistic\CourierCompanyController::class, 'index'])
                         ->name('logistic.couriers.index');
                    Route::get('/list', [\App\Http\Controllers\Tenant\Logistic\CourierCompanyController::class, 'list'])
                         ->name('logistic.couriers.list');
                    Route::post('/', [\App\Http\Controllers\Tenant\Logistic\CourierCompanyController::class, 'store'])
                         ->name('logistic.couriers.store');
                    Route::put('{courier}', [\App\Http\Controllers\Tenant\Logistic\CourierCompanyController::class, 'update'])
                         ->name('logistic.couriers.update');
                    Route::delete('{courier}', [\App\Http\Controllers\Tenant\Logistic\CourierCompanyController::class, 'destroy'])
                         ->name('logistic.couriers.destroy');
                });

                // ── Dashboard ────────────────────────────────────────────────
                Route::get('dashboard', [\App\Http\Controllers\Tenant\Logistic\DashboardController::class, 'index'])
                     ->name('logistic.dashboard');

                // ── API JSON para el Vue (usa sesión web, no token) ───────────
                Route::get('queue-json', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'index'])
                     ->name('logistic.queue_json');
                Route::post('queue-json/{saleNote}/start-preparation', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'startPreparation'])
                     ->name('logistic.queue_json.start');
                Route::post('queue-json/{saleNote}/cancel', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'cancel'])
                     ->name('logistic.queue_json.cancel');
                Route::patch('queue-json/{saleNote}/update-shipping', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'updateShipping'])
                     ->name('logistic.queue_json.update_shipping');
                Route::post('queue-json/{saleNote}/ready', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'markReady'])
                     ->name('logistic.queue_json.ready');
                Route::post('queue-json/{saleNote}/dispatch', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'dispatchOrder'])
                     ->name('logistic.queue_json.dispatch');
                Route::get('queue-json/{saleNote}/stock-movements', [\App\Http\Controllers\Tenant\Logistic\WarehouseQueueController::class, 'stockMovements'])
                     ->name('logistic.queue_json.stock_movements');

                // ── Devoluciones ─────────────────────────────────────────────
                Route::prefix('returns')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'index'])
                         ->name('logistic.returns.index');
                    Route::get('/create', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'create'])
                         ->name('logistic.returns.create');
                    Route::get('/search', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'searchOrder'])
                         ->name('logistic.returns.search');
                    Route::post('/', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'store'])
                         ->name('logistic.returns.store');
                    Route::get('/{return}', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'show'])
                         ->name('logistic.returns.show');
                    Route::patch('/{return}/process', [\App\Http\Controllers\Tenant\Logistic\ReturnController::class, 'process'])
                         ->name('logistic.returns.process');
                });
            });

            Route::get('orders/tables/item/{internal_id}', 'Tenant\OrderController@item');

            //Status Orders
            Route::get('statusOrder/records', 'Tenant\StatusOrdersController@records');

            //Company
            Route::get('companies/create', 'Tenant\CompanyController@create')->name('tenant.companies.create')->middleware('redirect.level');
            Route::get('companies/tables', 'Tenant\CompanyController@tables');
            Route::get('companies/record', 'Tenant\CompanyController@record');
            Route::post('companies', 'Tenant\CompanyController@store');
            Route::post('companies/uploads', 'Tenant\CompanyController@uploadFile');
            Route::post('companies/uploads', 'Tenant\CompanyController@uploadFile');
            Route::delete('companies/delete-logo', 'Tenant\CompanyController@deleteLogo');
            Route::get('companies/pse-providers', 'Tenant\CompanyController@getPseProviders');

            //configuracion envio documento a pse
            Route::post('companies/store-send-pse', 'Tenant\CompanyController@storeSendPse');
            Route::get('companies/record-send-pse', 'Tenant\CompanyController@recordSendPse');

            //configuracion WhatsApp Api
            Route::post('companies/store-whatsapp-api', 'Tenant\CompanyController@storeWhatsAppApi');
            Route::get('companies/record-whatsapp-api', 'Tenant\CompanyController@recordWhatsAppApi');


            //Card Brands
            Route::get('card_brands/records', 'Tenant\CardBrandController@records');
            Route::get('card_brands/record/{card_brand}', 'Tenant\CardBrandController@record');
            Route::post('card_brands', 'Tenant\CardBrandController@store');
            Route::delete('card_brands/{card_brand}', 'Tenant\CardBrandController@destroy');

            //Configurations
            Route::get('configurations/sale-notes', 'Tenant\SaleNoteController@SetAdvanceConfiguration')->name('tenant.sale_notes.configuration')->middleware('redirect.level');
            Route::post('configurations/sale-notes', 'Tenant\SaleNoteController@SaveSetAdvanceConfiguration');
            Route::get('configurations/addSeeder', 'Tenant\ConfigurationController@addSeeder');
            Route::get('configurations/preprinted/addSeeder', 'Tenant\ConfigurationController@addPreprintedSeeder');
            Route::get('configurations/getFormats', 'Tenant\ConfigurationController@getFormats');
            Route::get('configurations/preprinted/getFormats', 'Tenant\ConfigurationController@getPreprintedFormats');
            Route::get('configurations/create', 'Tenant\ConfigurationController@create')->name('tenant.configurations.create');
            Route::get('configurations/record', 'Tenant\ConfigurationController@record');
            Route::post('configurations', 'Tenant\ConfigurationController@store');
            Route::post('configurations/apiruc', 'Tenant\ConfigurationController@storeApiRuc');
            Route::post('configurations/icbper', 'Tenant\ConfigurationController@icbper');
            Route::post('configurations/changeFormat', 'Tenant\ConfigurationController@changeFormat');
            Route::post('configurations/saveColumnsConfig', 'Tenant\ConfigurationController@saveColumnsConfig');
            Route::get('configurations/getColumnsConfig', 'Tenant\ConfigurationController@getColumnsConfig');
            Route::get('configurations/tables', 'Tenant\ConfigurationController@tables');
            Route::get('configurations/visual_defaults', 'Tenant\ConfigurationController@visualDefaults')->name('visual_defaults');
            Route::get('configurations/visual/get_menu', 'Tenant\ConfigurationController@visualGetMenu')->name('visual_get_menu');
            Route::post('configurations/visual/set_menu', 'Tenant\ConfigurationController@visualSetMenu')->name('visual_set_menu');
            Route::post('configurations/visual_settings', 'Tenant\ConfigurationController@visualSettings')->name('visual-settings');
            Route::post('configurations/visual/upload_skin', 'Tenant\ConfigurationController@visualUploadSkin')->name('visual_upload_skin');
            Route::post('configurations/visual/delete_skin', 'Tenant\ConfigurationController@visualDeleteSkin')->name('visual_delete_skin');
            Route::get('configurations/pdf_templates', 'Tenant\ConfigurationController@pdfTemplates')->name('tenant.advanced.pdf_templates');
            Route::get('configurations/pdf_guide_templates', 'Tenant\ConfigurationController@pdfGuideTemplates')->name('tenant.advanced.pdf_guide_templates');
            Route::get('configurations/pdf_preprinted_templates', 'Tenant\ConfigurationController@pdfPreprintedTemplates')->name('tenant.advanced.pdf_preprinted_templates');
            Route::post('configurations/uploads', 'Tenant\ConfigurationController@uploadFile');
            Route::post('configurations/preprinted/generateDispatch', 'Tenant\ConfigurationController@generateDispatch');
            Route::get('configurations/preprinted/{template}', 'Tenant\ConfigurationController@show');
            Route::get('configurations/change-mode', 'Tenant\ConfigurationController@changeMode')->name('settings.change_mode');

            Route::get('configurations/templates/ticket/refresh', 'Tenant\ConfigurationController@refreshTickets');
            Route::get('configurations/pdf_templates/ticket', 'Tenant\ConfigurationController@pdfTicketTemplates')->name('tenant.advanced.pdf_ticket_templates');
            Route::get('configurations/templates/ticket/records', 'Tenant\ConfigurationController@getTicketFormats');
            Route::post('configurations/templates/ticket/update', 'Tenant\ConfigurationController@changeTicketFormat');
            Route::get('configurations/apiruc', 'Tenant\ConfigurationController@apiruc');

            Route::post('configurations/pdf-footer-images', 'Tenant\ConfigurationController@pdfFooterImages');
            Route::get('configurations/get-pdf-footer-images', 'Tenant\ConfigurationController@getPdfFooterImages');

            //Certificates
            Route::get('certificates/record', 'Tenant\CertificateController@record');
            Route::post('certificates/uploads', 'Tenant\CertificateController@uploadFile');
            Route::delete('certificates', 'Tenant\CertificateController@destroy');

            //Certificates Qz Tray
            Route::get('certificates-qztray/record', 'Tenant\CertificateQzTrayController@record');
            Route::post('certificates-qztray/uploads', 'Tenant\CertificateQzTrayController@uploadFileQzTray');
            Route::delete('certificates-qztray', 'Tenant\CertificateQzTrayController@destroy');
            Route::get('certificates-qztray/private', 'Tenant\CertificateQzTrayController@private');
            Route::get('certificates-qztray/digital', 'Tenant\CertificateQzTrayController@digital');
            Route::get('certificates-qztray/download', 'Tenant\CertificateQzTrayController@download');

            //Establishments
            Route::get('establishments', 'Tenant\EstablishmentController@index')->name('tenant.establishments.index');
            Route::get('establishments/create', 'Tenant\EstablishmentController@create');
            Route::get('establishments/tables', 'Tenant\EstablishmentController@tables');
            Route::get('establishments/record/{establishment}', 'Tenant\EstablishmentController@record');
            Route::post('establishments', 'Tenant\EstablishmentController@store');
            Route::get('establishments/records', 'Tenant\EstablishmentController@records');
            Route::delete('establishments/{establishment}', 'Tenant\EstablishmentController@destroy');
            Route::get('establishments/getEstablishmentActive', 'Tenant\EstablishmentController@getEstablishmentActive');
            Route::get('establishments/codes', 'Tenant\EstablishmentController@getCodes');

            //Bank Accounts
            Route::get('bank_accounts', 'Tenant\BankAccountController@index')->name('tenant.bank_accounts.index');
            Route::get('bank_accounts/records', 'Tenant\BankAccountController@records');
            Route::get('bank_accounts/create', 'Tenant\BankAccountController@create');
            Route::get('bank_accounts/tables', 'Tenant\BankAccountController@tables');
            Route::get('bank_accounts/record/{bank_account}', 'Tenant\BankAccountController@record');
            Route::post('bank_accounts', 'Tenant\BankAccountController@store');
            Route::delete('bank_accounts/{bank_account}', 'Tenant\BankAccountController@destroy');

            //Series
            Route::get('series/records/{establishment}/{document_type?}', 'Tenant\SeriesController@records');
            Route::get('series/create', 'Tenant\SeriesController@create');
            Route::get('series/tables', 'Tenant\SeriesController@tables');
            Route::post('series', 'Tenant\SeriesController@store');
            Route::delete('series/{series}', 'Tenant\SeriesController@destroy');

            //Users
            Route::get('users', 'Tenant\UserController@index')->name('tenant.users.index');
            Route::get('users/create', 'Tenant\UserController@create')->name('tenant.users.create');
            Route::get('users/tables', 'Tenant\UserController@tables');
            Route::get('users/record/{user}', 'Tenant\UserController@record');
            Route::post('users', 'Tenant\UserController@store');
            Route::post('users/token/{user}', 'Tenant\UserController@regenerateToken');
            Route::get('users/records', 'Tenant\UserController@records');
            Route::delete('users/{user}', 'Tenant\UserController@destroy');
            Route::post('users/change-active', 'Tenant\UserController@changeActive');

            //ChargeDiscounts
            Route::get('charge_discounts', 'Tenant\ChargeDiscountController@index')->name('tenant.charge_discounts.index');
            Route::get('charge_discounts/records/{type}', 'Tenant\ChargeDiscountController@records');
            Route::get('charge_discounts/create', 'Tenant\ChargeDiscountController@create');
            Route::get('charge_discounts/tables/{type}', 'Tenant\ChargeDiscountController@tables');
            Route::get('charge_discounts/record/{charge}', 'Tenant\ChargeDiscountController@record');
            Route::post('charge_discounts', 'Tenant\ChargeDiscountController@store');
            Route::delete('charge_discounts/{charge}', 'Tenant\ChargeDiscountController@destroy');

            //Items Ecommerce
            Route::get('items_ecommerce', 'Tenant\ItemController@index_ecommerce')->name('tenant.items_ecommerce.index');

            //Items
            Route::get('items', 'Tenant\ItemController@index')->name('tenant.items.index')->middleware('redirect.level');
            Route::get('services', 'Tenant\ItemController@indexServices')->name('tenant.services')->middleware('redirect.level');
            Route::get('items/columns', 'Tenant\ItemController@columns');
            Route::get('items/records', 'Tenant\ItemController@records');
            Route::get('items/tables', 'Tenant\ItemController@tables');
            Route::get('items/record/{item}', 'Tenant\ItemController@record');
            Route::post('items', 'Tenant\ItemController@store');
            Route::post('items/destroyMassive', 'Tenant\ItemController@destroyMassive');
            Route::delete('items/{item}', 'Tenant\ItemController@destroy');
            Route::delete('items/item-unit-type/{item}', 'Tenant\ItemController@destroyItemUnitType');
            Route::post('items/import', 'Tenant\ItemController@import');
            Route::post('items/import/restaurant', 'Tenant\ItemController@importRestaurant');
            Route::post('items/catalog', 'Tenant\ItemController@catalog');
            Route::get('items/import/tables', 'Tenant\ItemController@tablesImport');
            Route::post('items/upload', 'Tenant\ItemController@upload');
            Route::post('items/visible_store', 'Tenant\ItemController@visibleStore');
            Route::post('items/marketplace-toggle', 'Tenant\ItemController@marketplaceToggle');
            Route::get('items/marketplace-stats',    'Tenant\ItemController@marketplaceStats');
            Route::post('items/bulk-channel',        'Tenant\ItemController@bulkChannel');
            Route::get('items/channel-stats',        'Tenant\ItemController@channelStats');

            // Shipping zones (configuración del tenant)
            Route::get('shipping-zones',              'Tenant\ShippingZoneController@index');
            Route::get('shipping-zones/records',      'Tenant\ShippingZoneController@records');
            Route::get('shipping-zones/tables',       'Tenant\ShippingZoneController@tables');
            Route::get('shipping-zones/record/{id}',  'Tenant\ShippingZoneController@record');
            Route::post('shipping-zones',             'Tenant\ShippingZoneController@store');
            Route::put('shipping-zones/{id}',         'Tenant\ShippingZoneController@update');
            Route::delete('shipping-zones/{id}',      'Tenant\ShippingZoneController@destroy');

            // Categorías oficiales del marketplace (árbol desde system DB)
            Route::get('marketplace-categories/tree',         'Tenant\MarketplaceCategoryController@tree');
            Route::get('marketplace-categories/flat',         'Tenant\MarketplaceCategoryController@flat');
            Route::get('marketplace-categories/suggest',      'Tenant\MarketplaceCategoryController@suggest');
            Route::post('marketplace-categories/request-new', 'Tenant\MarketplaceCategoryController@requestNew');

            Route::post('items/duplicate', 'Tenant\ItemController@duplicate');
            Route::get('items/disable/{item}', 'Tenant\ItemController@disable');
            Route::post('items/disableMassive', 'Tenant\ItemController@disableMassive');
            Route::get('items/enable/{item}', 'Tenant\ItemController@enable');
            Route::post('items/enableMassive', 'Tenant\ItemController@enableMassive');
            Route::get('items/images/{item}', 'Tenant\ItemController@images');
            Route::get('items/images/delete/{id}', 'Tenant\ItemController@delete_images');
            Route::get('items/without-image', 'Tenant\ItemController@withoutImage');
            Route::get('items/export', 'Tenant\ItemController@export')->name('tenant.items.export');
            Route::get('items/export/wp', 'Tenant\ItemController@exportWp')->name('tenant.items.export.wp');
            Route::get('items/export/digemid', 'Tenant\ItemController@exportDigemid');
            Route::get('items/search-items', 'Tenant\ItemController@searchItems');
            Route::get('items/search/item/{item}', 'Tenant\ItemController@searchItemById');
            Route::get('items/item/tables', 'Tenant\ItemController@item_tables');
            Route::get('items/table/{table}', 'Tenant\DocumentController@table');
            Route::get('items/export/barcode', 'Tenant\ItemController@exportBarCode')->name('tenant.items.export.barcode');
            Route::get('items/export/extra_atrributes/PDF', 'Tenant\ItemController@downloadExtraDataPdf');
            Route::get('items/export/extra_atrributes/XLSX', 'Tenant\ItemController@downloadExtraDataItemsExcel');
            Route::get('items/export/barcode_full', 'Tenant\ItemController@exportBarCodeFull');
            Route::post('items/export/bartender', 'Tenant\ItemController@exportTxtBartender');
            Route::post('items/visibleMassive', 'Tenant\ItemController@visibleMassive');
            Route::get('items/export/barcode/print', 'Tenant\ItemController@printBarCode')->name('tenant.items.export.barcode.print');
            Route::get('items/export/barcode/print_x', 'Tenant\ItemController@printBarCodeX')->name('tenant.items.export.barcode.print.x');
            Route::get('items/export/barcode/last', 'Tenant\ItemController@itemLast')->name('tenant.items.last');
            Route::post('get-items', 'Tenant\ItemController@getAllItems');

            // Item Variants
            Route::prefix('items/{item}/variants')->group(function () {
                Route::get('/',                   [\App\Http\Controllers\Tenant\ItemVariantController::class, 'index']);
                Route::post('/options',            [\App\Http\Controllers\Tenant\ItemVariantController::class, 'saveOptions']);
                Route::post('/generate',           [\App\Http\Controllers\Tenant\ItemVariantController::class, 'generate']);
                Route::patch('/{variant}',         [\App\Http\Controllers\Tenant\ItemVariantController::class, 'update']);
                Route::delete('/{variant}',        [\App\Http\Controllers\Tenant\ItemVariantController::class, 'destroy']);
                Route::post('/{variant}/stock',    [\App\Http\Controllers\Tenant\ItemVariantController::class, 'updateStock']);
            });

            //Persons
            Route::prefix('persons')->group(function () {
                /**
                 *persons/columns
                 *persons/tables
                 *persons/{type}
                 *persons/{type}/records
                 *persons/
                 *persons/{person}
                 *persons/import
                 *persons/enabled/{type}/{person}
                 *persons/{type}/exportation
                 *persons/search-data
                 */
                Route::get('/columns', 'Tenant\PersonController@columns');
                Route::get('/tables', 'Tenant\PersonController@tables');
                Route::get('/{type}', 'Tenant\PersonController@index')->name('tenant.persons.index');
                Route::get('/{type}/records', 'Tenant\PersonController@records');
                Route::get('/record/{person}', 'Tenant\PersonController@record');
                Route::post('', 'Tenant\PersonController@store');
                Route::delete('/{person}', 'Tenant\PersonController@destroy');
                Route::post('/import', 'Tenant\PersonController@import');
                Route::get('/enabled/{type}/{person}', 'Tenant\PersonController@enabled');
                Route::get('/{type}/exportation', 'Tenant\PersonController@export')->name('tenant.persons.export');
                Route::get('/export/barcode/print', 'Tenant\PersonController@printBarCode')->name('tenant.persons.export.barcode.print');
                Route::get('/barcode/{item}', 'Tenant\PersonController@generateBarcode');
                Route::get('/search/{barcode}', 'Tenant\PersonController@getPersonByBarcode');
                Route::get('accumulated-points/{id}', 'Tenant\PersonController@getAccumulatedPoints');
                Route::get('search-data/{type}', 'Tenant\PersonController@searchData');
                Route::get('/{person}/history', 'Tenant\PersonController@history')->name('tenant.persons.history');

            });

            Route::prefix('consigneds')->group(function() {
                Route::get('/tables', 'Tenant\ConsignedController@tables');
                Route::get('/data', 'Tenant\ConsignedController@data');
                Route::get('/records', 'Tenant\ConsignedController@records');
                Route::get('/record/{consigned}', 'Tenant\ConsignedController@record');
                Route::post('', 'Tenant\ConsignedController@store');
                Route::post('/store-document', 'Tenant\ConsignedController@storeDocument');
                Route::get('/search_by_customer/{id}', 'Tenant\ConsignedController@searchByCustomer');
                Route::get('/addresses', 'Tenant\ConsignedController@consignedAddresses');
            });

            //Documents
            Route::post('documents/categories', 'Tenant\DocumentController@storeCategories');
            Route::post('documents/brands', 'Tenant\DocumentController@storeBrands');
            Route::get('documents/search/customers', 'Tenant\DocumentController@searchCustomers');
            Route::get('documents/search/customer/{id}', 'Tenant\DocumentController@searchCustomerById');
            Route::get('documents/search/externalId/{external_id}', 'Tenant\DocumentController@searchExternalId');

            Route::get('documents', 'Tenant\DocumentController@index')->name('tenant.documents.index')->middleware(['redirect.level', 'tenant.internal.mode']);
            Route::get('documents/columns', 'Tenant\DocumentController@columns');
            Route::get('documents/records', 'Tenant\DocumentController@records');
            Route::get('documents/recordsTotal', 'Tenant\DocumentController@recordsTotal');
            Route::get('documents/create', 'Tenant\DocumentController@create')->name('tenant.documents.create')->middleware(['redirect.level', 'tenant.internal.mode']);
            Route::get('documents/create_tensu', 'Tenant\DocumentController@create_tensu')->name('tenant.documents.create_tensu');
            Route::get('documents/{id}/edit', 'Tenant\DocumentController@edit')->middleware(['redirect.level', 'tenant.internal.mode']);
            Route::get('documents/{id}/show', 'Tenant\DocumentController@show');

            Route::get('documents/tables', 'Tenant\DocumentController@tables');
            Route::get('documents/record/{document}', 'Tenant\DocumentController@record');
            Route::post('documents', 'Tenant\DocumentController@store');
            Route::post('documents/{id}/update', 'Tenant\DocumentController@update');
            Route::get('documents/send/{document}', 'Tenant\DocumentController@send');
            // Route::get('documents/remove/{document}', 'Tenant\DocumentController@remove');
            // Route::get('documents/consult_cdr/{document}', 'Tenant\DocumentController@consultCdr');
            Route::post('documents/email', 'Tenant\DocumentController@email');
            Route::get('documents/note/{document}', 'Tenant\NoteController@create');
            Route::get('documents/note/record/{document}', 'Tenant\NoteController@record');
            Route::get('documents/item/tables', 'Tenant\DocumentController@item_tables');
            Route::get('documents/table/{table}', 'Tenant\DocumentController@table');
            Route::get('documents/re_store/{document}', 'Tenant\DocumentController@reStore');
            Route::get('documents/locked_emission', 'Tenant\DocumentController@messageLockedEmission');
            Route::get('documents/note/has-documents/{document}', 'Tenant\NoteController@hasDocuments');
            Route::post('documents/preview', 'Tenant\DocumentController@preview');

            Route::get('document_payments/records/{document_id}', 'Tenant\DocumentPaymentController@records');
            Route::get('document_payments/document/{document_id}', 'Tenant\DocumentPaymentController@document');
            Route::get('document_payments/tables', 'Tenant\DocumentPaymentController@tables');
            Route::post('document_payments', 'Tenant\DocumentPaymentController@store');
            Route::delete('document_payments/{document_payment}', 'Tenant\DocumentPaymentController@destroy');
            Route::get('document_payments/initialize_balance', 'Tenant\DocumentPaymentController@initialize_balance');
            Route::get('document_payments/report/{start}/{end}/{report}', 'Tenant\DocumentPaymentController@report');


            Route::get('documents/send_server/{document}/{query?}', 'Tenant\DocumentController@sendServer');
            Route::get('documents/check_server/{document}', 'Tenant\DocumentController@checkServer');
            Route::get('documents/change_to_registered_status/{document}', 'Tenant\DocumentController@changeToRegisteredStatus');

            Route::post('documents/import', 'Tenant\DocumentController@import');
            Route::post('documents/import_second_format', 'Tenant\DocumentController@importTwoFormat');
            Route::get('documents/data_table', 'Tenant\DocumentController@data_table');
            Route::get('documents/payments/excel/{month}/{anulled}', 'Tenant\DocumentController@report_payments')->name('tenant.document.payments.excel');
            Route::get('documents/payments-complete', 'Tenant\DocumentController@report_payments');


            Route::post('documents/import_excel_format', 'Tenant\DocumentController@importExcelFormat');
            Route::get('documents/import_excel_tables', 'Tenant\DocumentController@importExcelTables');


            Route::delete('documents/delete_document/{document_id}', 'Tenant\DocumentController@destroyDocument');

            Route::get('documents/data-table/items', 'Tenant\DocumentController@getDataTableItem');
            Route::get('documents/retention/{document}', 'Tenant\DocumentController@retention');
            Route::post('documents/retention', 'Tenant\DocumentController@retentionStore');
            Route::post('documents/retention/upload', 'Tenant\DocumentController@retentionUpload');

            //Contingencies
            Route::get('contingencies', 'Tenant\ContingencyController@index')->name('tenant.contingencies.index')->middleware('redirect.level', 'tenant.internal.mode');
            Route::get('contingencies/columns', 'Tenant\ContingencyController@columns');
            Route::get('contingencies/records', 'Tenant\ContingencyController@records');
            Route::get('contingencies/create', 'Tenant\ContingencyController@create')->name('tenant.contingencies.create');

            //Summaries
            Route::get('summaries', 'Tenant\SummaryController@index')->name('tenant.summaries.index')->middleware('redirect.level', 'tenant.internal.mode');
            Route::get('summaries/records', 'Tenant\SummaryController@records');
            Route::post('summaries/documents', 'Tenant\SummaryController@documents');
            Route::post('summaries', 'Tenant\SummaryController@store');
            Route::get('summaries/status/{summary}', 'Tenant\SummaryController@status');
            Route::get('summaries/columns', 'Tenant\SummaryController@columns');
            Route::delete('summaries/{summary}', 'Tenant\SummaryController@destroy');
            Route::get('summaries/record/{summary}', 'Tenant\SummaryController@record');
            Route::get('summaries/regularize/{summary}', 'Tenant\SummaryController@regularize');
            Route::get('summaries/cancel-regularize/{summary}', 'Tenant\SummaryController@cancelRegularize');
            Route::get('summaries/tables', 'Tenant\SummaryController@tables');

            //Voided
            Route::get('voided', 'Tenant\VoidedController@index')->name('tenant.voided.index')->middleware('redirect.level', 'tenant.internal.mode');
            Route::get('voided/columns', 'Tenant\VoidedController@columns');
            Route::get('voided/records', 'Tenant\VoidedController@records');
            Route::post('voided', 'Tenant\VoidedController@store');
            //            Route::get('voided/download/{type}/{voided}', 'Tenant\VoidedController@download')->name('tenant.voided.download');
            Route::get('voided/status/{voided}', 'Tenant\VoidedController@status');
            Route::get('voided/status_masive', 'Tenant\VoidedController@status_masive');

            Route::delete('voided/{voided}', 'Tenant\VoidedController@destroy');
            //            Route::get('voided/ticket/{voided_id}/{group_id}', 'Tenant\VoidedController@ticket');

            //Retentions
            Route::get('retentions', 'Tenant\RetentionController@index')->name('tenant.retentions.index');
            Route::get('retentions/columns', 'Tenant\RetentionController@columns');
            Route::get('retentions/records', 'Tenant\RetentionController@records');
            Route::get('retentions/create', 'Tenant\RetentionController@create')->name('tenant.retentions.create');
            Route::get('retentions/tables', 'Tenant\RetentionController@tables');
            Route::get('retentions/record/{retention}', 'Tenant\RetentionController@record');
            Route::post('retentions', 'Tenant\RetentionController@store');
            Route::delete('retentions/{retention}', 'Tenant\RetentionController@destroy');
            Route::get('retentions/document/tables', 'Tenant\RetentionController@document_tables');
            Route::get('retentions/table/{table}', 'Tenant\RetentionController@table');

            /** Dispatches
             * dispatches
             * dispatches/columns
             * dispatches/records
             * dispatches/create/{document?}/{type?}/{dispatch?}
             * dispatches/tables
             * dispatches
             * dispatches/record/{id}
             * dispatches/sendSunat/{document}
             * dispatches/email
             * dispatches/generate/{sale_note}
             * dispatches/record/{id}/tables
             * dispatches/record/{id}/set-document-id
             * dispatches/search/customers
             * dispatches/search/customer/{id}
             * dispatches/client/{id}
             * dispatches/items
             * dispatches/data_table
             * dispatches/search/customer/{id}
             */
            Route::prefix('dispatches')->group(function () {
                Route::get('', 'Tenant\DispatchController@index')->name('tenant.dispatches.index');
                Route::get('/columns', 'Tenant\DispatchController@columns');
                Route::get('/records', 'Tenant\DispatchController@records');
                Route::get('/create/{document?}/{type?}/{dispatch?}', 'Tenant\DispatchController@create');
                Route::post('/tables', 'Tenant\DispatchController@tables');
                Route::post('', 'Tenant\DispatchController@store');
                Route::get('/record/{id}', 'Tenant\DispatchController@record');
                Route::post('/sendSunat/{document}', 'Tenant\DispatchController@sendDispatchToSunat');
                Route::post('/email', 'Tenant\DispatchController@email');
                Route::get('/generate/{sale_note}', 'Tenant\DispatchController@generate');
                Route::get('/record/{id}/tables', 'Tenant\DispatchController@generateDocumentTables');
                Route::post('/record/{id}/set-document-id', 'Tenant\DispatchController@setDocumentId');
                Route::get('/client/{id}', 'Tenant\DispatchController@dispatchesByClient');
                Route::post('/items', 'Tenant\DispatchController@getItemsFromDispatches');
                Route::post('/getDocumentType', 'Tenant\DispatchController@getDocumentTypeToDispatches');
                Route::get('/data_table', 'Tenant\DispatchController@data_table');
                Route::get('/search/customers', 'Tenant\DispatchController@searchCustomers');
                Route::get('/search/customer/{id}', 'Tenant\DispatchController@searchClientById');
                Route::post('/status_ticket', 'Tenant\Api\DispatchController@statusTicket');
                Route::get('create_new/{table}/{id}', 'Tenant\DispatchController@createNew');
                Route::get('/get_origin_addresses/{establishment_id}', 'Tenant\DispatchController@getOriginAddresses');
                Route::get('/get_addresses_other_establishments/{establishment_id}', 'Tenant\DispatchController@getAddressesOtherEstablishments');
                Route::get('/get_delivery_addresses/{person_id}', 'Tenant\DispatchController@getDeliveryAddresses');
            });

            Route::prefix('dispatch_carrier')->group(function () {
                Route::get('', 'Tenant\DispatchCarrierController@index')->name('tenant.dispatch_carrier.index');
                Route::get('/columns', 'Tenant\DispatchCarrierController@columns');
                Route::get('/records', 'Tenant\DispatchCarrierController@records');
                Route::get('/create/{document?}/{type?}/{dispatch?}', 'Tenant\DispatchCarrierController@create');
                Route::post('/tables', 'Tenant\DispatchCarrierController@tables');
                Route::post('', 'Tenant\DispatchCarrierController@store');
                Route::get('/record/{id}', 'Tenant\DispatchCarrierController@record');
                Route::post('/sendSunat/{document}', 'Tenant\DispatchCarrierController@sendDispatchToSunat');
                Route::post('/email', 'Tenant\DispatchCarrierController@email');
                Route::get('/generate/{sale_note}', 'Tenant\DispatchCarrierController@generate');
                Route::get('/record/{id}/tables', 'Tenant\DispatchCarrierController@generateDocumentTables');
                Route::post('/record/{id}/set-document-id', 'Tenant\DispatchCarrierController@setDocumentId');
                Route::get('/client/{id}', 'Tenant\DispatchCarrierController@dispatchesByClient');
                Route::post('/items', 'Tenant\DispatchCarrierController@getItemsFromDispatches');
                Route::post('/getDocumentType', 'Tenant\DispatchCarrierController@getDocumentTypeToDispatches');
                Route::get('/data_table', 'Tenant\DispatchCarrierController@data_table');
                Route::get('/search/customers', 'Tenant\DispatchCarrierController@searchCustomers');
                Route::get('/search/customer/{id}', 'Tenant\DispatchCarrierController@searchClientById');
                Route::post('/status_ticket', 'Tenant\Api\DispatchCarrierController@statusTicket');
                Route::get('create_new/{table}/{id}', 'Tenant\DispatchCarrierController@createNew');
                Route::get('/get_origin_addresses/{establishment_id}', 'Tenant\DispatchCarrierController@getOriginAddresses');
                Route::get('/get_delivery_addresses/{person_id}', 'Tenant\DispatchCarrierController@getDeliveryAddresses');
            });

            Route::get('customers/list', 'Tenant\PersonController@clientsForGenerateCPE');
            Route::get('reports/consistency-documents', 'Tenant\ReportConsistencyDocumentController@index')->name('tenant.consistency-documents.index')->middleware('tenant.internal.mode');
            Route::post('reports/consistency-documents/lists', 'Tenant\ReportConsistencyDocumentController@lists');

            Route::post('options/delete_documents', 'Tenant\OptionController@deleteDocuments');
            Route::post('options/delete_items', 'Tenant\OptionController@deleteItems');

            // apiperu no usa estas rutas - revisar
            Route::get('services/ruc/{number}', 'Tenant\Api\ServiceController@ruc');
            Route::get('services/dni/{number}', 'Tenant\Api\ServiceController@dni');
            Route::post('services/exchange_rate', 'Tenant\Api\ServiceController@exchange_rate');
            Route::post('services/search_exchange_rate', 'Tenant\Api\ServiceController@searchExchangeRateByDate');
            Route::get('services/exchange_rate/{date}', 'Tenant\Api\ServiceController@exchangeRateTest');
            Route::get('services/exchange/{date}', 'Tenant\Api\ServiceController@exchangeRateTest');

            //BUSQUEDA DE DOCUMENTOS
            // Route::get('busqueda', 'Tenant\SearchController@index')->name('search');
            // Route::post('busqueda', 'Tenant\SearchController@index')->name('search');

            //Codes
            Route::get('codes/records', 'Tenant\Catalogs\CodeController@records');
            Route::get('codes/tables', 'Tenant\Catalogs\CodeController@tables');
            Route::get('codes/record/{code}', 'Tenant\Catalogs\CodeController@record');
            Route::post('codes', 'Tenant\Catalogs\CodeController@store');
            Route::delete('codes/{code}', 'Tenant\Catalogs\CodeController@destroy');

            //Units
            Route::get('unit_types/records', 'Tenant\UnitTypeController@records');
            Route::get('unit_types/record/{code}', 'Tenant\UnitTypeController@record');
            Route::post('unit_types', 'Tenant\UnitTypeController@store');
            Route::delete('unit_types/{code}', 'Tenant\UnitTypeController@destroy');

            //Transfer Reason Types
            Route::get('transfer-reason-types/records', 'Tenant\TransferReasonTypeController@records');
            Route::get('transfer-reason-types/record/{code}', 'Tenant\TransferReasonTypeController@record');
            Route::post('transfer-reason-types', 'Tenant\TransferReasonTypeController@store');
            Route::delete('transfer-reason-types/{code}', 'Tenant\TransferReasonTypeController@destroy');

            // Affectation IGV types 
            Route::get('item-affectations-igv/records', 'Tenant\ItemAffectationsIgvController@records');
            Route::get('item-affectations-igv/active/{id}/{active}', 'Tenant\ItemAffectationsIgvController@changeActive');


            //Detractions
            Route::get('detraction_types/records', 'Tenant\DetractionTypeController@records');
            Route::get('detraction_types/tables', 'Tenant\DetractionTypeController@tables');
            Route::get('detraction_types/record/{code}', 'Tenant\DetractionTypeController@record');
            Route::post('detraction_types', 'Tenant\DetractionTypeController@store');
            Route::delete('detraction_types/{code}', 'Tenant\DetractionTypeController@destroy');

            //Banks
            Route::get('banks/records', 'Tenant\BankController@records');
            Route::get('banks/record/{bank}', 'Tenant\BankController@record');
            Route::post('banks', 'Tenant\BankController@store');
            Route::delete('banks/{bank}', 'Tenant\BankController@destroy');

            //Exchange Rates
            Route::get('exchange_rates/records', 'Tenant\ExchangeRateController@records');
            Route::post('exchange_rates', 'Tenant\ExchangeRateController@store');

            //Currency Types
            Route::get('currency_types/records', 'Tenant\CurrencyTypeController@records');
            Route::get('currency_types/record/{currency_type}', 'Tenant\CurrencyTypeController@record');
            Route::post('currency_types', 'Tenant\CurrencyTypeController@store');
            Route::delete('currency_types/{currency_type}', 'Tenant\CurrencyTypeController@destroy');

            //Perceptions
            Route::get('perceptions', 'Tenant\PerceptionController@index')->name('tenant.perceptions.index');
            Route::get('perceptions/columns', 'Tenant\PerceptionController@columns');
            Route::get('perceptions/records', 'Tenant\PerceptionController@records');
            Route::get('perceptions/create', 'Tenant\PerceptionController@create')->name('tenant.perceptions.create');
            Route::get('perceptions/tables', 'Tenant\PerceptionController@tables');
            Route::get('perceptions/record/{perception}', 'Tenant\PerceptionController@record');
            Route::post('perceptions', 'Tenant\PerceptionController@store');
            Route::delete('perceptions/{perception}', 'Tenant\PerceptionController@destroy');
            Route::get('perceptions/document/tables', 'Tenant\PerceptionController@document_tables');
            Route::get('perceptions/table/{table}', 'Tenant\PerceptionController@table');

            //Tribute Concept Type
            Route::get('tribute_concept_types/records', 'Tenant\TributeConceptTypeController@records');
            Route::get('tribute_concept_types/record/{id}', 'Tenant\TributeConceptTypeController@record');
            Route::post('tribute_concept_types', 'Tenant\TributeConceptTypeController@store');
            Route::delete('tribute_concept_types/{id}', 'Tenant\TributeConceptTypeController@destroy');

            //purchases
            Route::get('purchases', 'Tenant\PurchaseController@index')->name('tenant.purchases.index');
            Route::get('purchases/columns', 'Tenant\PurchaseController@columns');
            Route::get('purchases/records', 'Tenant\PurchaseController@records');
            Route::get('purchases/create/{purchase_order_id?}', 'Tenant\PurchaseController@create')->name('tenant.purchases.create');
            Route::get('purchases/tables', 'Tenant\PurchaseController@tables');
            Route::get('purchases/table/{table}', 'Tenant\PurchaseController@table');
            Route::post('purchases', 'Tenant\PurchaseController@store');
            Route::post('purchases/update', 'Tenant\PurchaseController@update');
            Route::get('purchases/record/{document}', 'Tenant\PurchaseController@record');
            Route::get('purchases/edit/{id}', 'Tenant\PurchaseController@edit');
            Route::get('purchases/anular/{id}', 'Tenant\PurchaseController@anular');
            Route::post('purchases/guide/{purchase}', 'Tenant\PurchaseController@processGuides');
            Route::post('purchases/guide-file/upload', 'Tenant\PurchaseController@uploadAttached');
            Route::post('purchases/guide-file/upload', 'Tenant\PurchaseController@uploadAttached');
            Route::get('purchases/guides-file/download-file/{purchase}/{filename}', 'Tenant\PurchaseController@downloadGuide');
            Route::post('purchases/save_guide/{purchase}', 'Tenant\PurchaseController@processGuides');
            Route::get('purchases/delete/{id}', 'Tenant\PurchaseController@delete');
            Route::post('purchases/import', 'Tenant\PurchaseController@import');
            // Route::get('purchases/print/{external_id}/{format?}', 'Tenant\PurchaseController@toPrint');
            Route::get('purchases/search-items', 'Tenant\PurchaseController@searchItems');
            Route::get('purchases/search/item/{item}', 'Tenant\PurchaseController@searchItemById');
            Route::post('purchases/search/purchase_order','Tenant\PurchaseController@searchPurchaseOrder');
            // Route::get('purchases/item_resource/{id}', 'Tenant\PurchaseController@itemResource');

            // Route::get('documents/send/{document}', 'Tenant\DocumentController@send');
            // Route::get('documents/consult_cdr/{document}', 'Tenant\DocumentController@consultCdr');
            // Route::post('documents/email', 'Tenant\DocumentController@email');
            // Route::get('documents/note/{document}', 'Tenant\NoteController@create');
            Route::get('purchases/item/tables', 'Tenant\PurchaseController@item_tables');
            // Route::get('documents/table/{table}', 'Tenant\DocumentController@table');

            Route::delete('purchases/destroy_purchase_item/{purchase_item}', 'PurchaseController@destroy_purchase_item');

            //quotations
            Route::get('quotations', 'Tenant\QuotationController@index')->name('tenant.quotations.index')->middleware('redirect.level');
            Route::get('quotations/columns', 'Tenant\QuotationController@columns');
            Route::get('quotations/records', 'Tenant\QuotationController@records');
            Route::get('quotations/create/{saleOpportunityId?}', 'Tenant\QuotationController@create')->name('tenant.quotations.create')->middleware('redirect.level');
            Route::get('quotations/edit/{id}', 'Tenant\QuotationController@edit')->middleware('redirect.level');

            Route::get('quotations/state-type/{state_type_id}/{id}', 'Tenant\QuotationController@updateStateType');
            Route::get('quotations/filter', 'Tenant\QuotationController@filter');
            Route::get('quotations/tables', 'Tenant\QuotationController@tables');
            Route::get('quotations/table/{table}', 'Tenant\QuotationController@table');
            Route::post('quotations', 'Tenant\QuotationController@store');
            Route::post('quotations/update', 'Tenant\QuotationController@update');
            Route::get('quotations/record/{quotation}', 'Tenant\QuotationController@record');
            Route::get('quotations/anular/{id}', 'Tenant\QuotationController@anular');
            Route::get('quotations/item/tables', 'Tenant\QuotationController@item_tables');
            Route::get('quotations/option/tables', 'Tenant\QuotationController@option_tables');
            Route::get('quotations/search/customers', 'Tenant\QuotationController@searchCustomers');
            Route::get('quotations/search/customer/{id}', 'Tenant\QuotationController@searchCustomerById');
            Route::get('quotations/download/{external_id}/{format?}', 'Tenant\QuotationController@download');
            // Route::get('quotations/print/{external_id}/{format?}', 'Tenant\QuotationController@toPrint');
            Route::post('quotations/email', 'Tenant\QuotationController@email');
            Route::post('quotations/duplicate', 'Tenant\QuotationController@duplicate');
            Route::get('quotations/record2/{quotation}', 'Tenant\QuotationController@record2');
            Route::get('quotations/changed/{quotation}', 'Tenant\QuotationController@changed');

            Route::get('quotations/search-items', 'Tenant\QuotationController@searchItems');
            Route::get('quotations/search/item/{item}', 'Tenant\QuotationController@searchItemById');
            Route::get('quotations/item-warehouses/{item}', 'Tenant\QuotationController@itemWarehouses');

            //sale-notes
            Route::get('sale-notes', 'Tenant\SaleNoteController@index')->name('tenant.sale_notes.index')->middleware('redirect.level');
            Route::get('sale-notes/columns', 'Tenant\SaleNoteController@columns');
            Route::get('sale-notes/columns2', 'Tenant\SaleNoteController@columns2');

            Route::get('sale-notes/records', 'Tenant\SaleNoteController@records');
            Route::get('sale-notes/totals', 'Tenant\SaleNoteController@totals');
            // Route::get('sale-notes/create', 'Tenant\SaleNoteController@create')->name('tenant.sale_notes.create');
            Route::get('sale-notes/create/{salenote?}', 'Tenant\SaleNoteController@create')->name('tenant.sale_notes.create')->middleware('redirect.level');

            Route::get('sale-notes/tables', 'Tenant\SaleNoteController@tables');
            Route::post('sale-notes/UpToOther', 'Tenant\SaleNoteController@EnviarOtroSitio');
            Route::post('sale-notes/getUpToOther', 'Tenant\SaleNoteController@getSaleNoteToOtherSite');
            Route::post('sale-notes/urlUpToOther', 'Tenant\SaleNoteController@getSaleNoteToOtherSiteUrl');
            Route::post('sale-notes/duplicate', 'Tenant\SaleNoteController@duplicate');
            Route::get('sale-notes/table/{table}', 'Tenant\SaleNoteController@table');
            Route::post('sale-notes', 'Tenant\SaleNoteController@store');
            Route::get('sale-notes/record/{salenote}', 'Tenant\SaleNoteController@record');
            Route::get('sale-notes/item/tables', 'Tenant\SaleNoteController@item_tables');
            Route::get('sale-notes/search/customers', 'Tenant\SaleNoteController@searchCustomers');
            Route::get('sale-notes/search/customer/{id}', 'Tenant\SaleNoteController@searchCustomerById');
            // Route::get('sale-notes/print/{external_id}/{format?}', 'Tenant\SaleNoteController@toPrint');
            Route::get('sale-notes/record2/{salenote}', 'Tenant\SaleNoteController@record2');
            Route::get('sale-notes/option/tables', 'Tenant\SaleNoteController@option_tables');
            Route::get('sale-notes/changed/{salenote}', 'Tenant\SaleNoteController@changed');
            Route::post('sale-notes/email', 'Tenant\SaleNoteController@email');
            Route::get('sale-notes/print-a5/{sale_note_id}/{format}', 'Tenant\SaleNotePaymentController@toPrint');
            Route::get('sale-notes/dispatches', 'Tenant\SaleNoteController@dispatches');
            Route::delete('sale-notes/destroy_sale_note_item/{sale_note_item}', 'Tenant\SaleNoteController@destroy_sale_note_item');
            Route::get('sale-notes/search-items', 'Tenant\SaleNoteController@searchItems');
            Route::get('sale-notes/search/item/{item}', 'Tenant\SaleNoteController@searchItemById');
            Route::get('sale-notes/list-by-client', 'Tenant\SaleNoteController@saleNotesByClient');
            Route::post('sale-notes/items', 'Tenant\SaleNoteController@getItemsFromNotes');
            Route::get('sale-notes/config-group-items', 'Tenant\SaleNoteController@getConfigGroupItems');

            Route::get('sale_note_payments/records/{sale_note}', 'Tenant\SaleNotePaymentController@records');
            Route::get('sale_note_payments/document/{sale_note}', 'Tenant\SaleNotePaymentController@document');
            Route::get('sale_note_payments/tables', 'Tenant\SaleNotePaymentController@tables');
            Route::post('sale_note_payments', 'Tenant\SaleNotePaymentController@store');
            Route::delete('sale_note_payments/{sale_note_payment}', 'Tenant\SaleNotePaymentController@destroy');

            Route::post('sale-notes/enabled-concurrency', 'Tenant\SaleNoteController@enabledConcurrency');

            Route::get('sale-notes/anulate/{id}', 'Tenant\SaleNoteController@anulate');

            Route::get('sale-notes/downloadExternal/{external_id}/{format?}', 'Tenant\SaleNoteController@downloadExternal');

            Route::post('sale-notes/transform-data-order', 'Tenant\SaleNoteController@transformDataOrder');
            Route::post('sale-notes/items-by-ids', 'Tenant\SaleNoteController@getItemsByIds');
            Route::post('sale-notes/delete-relation-invoice', 'Tenant\SaleNoteController@deleteRelationInvoice');

            // Route::get('sale-notes/record-generate-document/{salenote}', 'Tenant\SaleNoteController@recordGenerateDocument');

            Route::get('sale-notes/dispatch/{id}', 'Tenant\SaleNoteController@recordsDispatch');
            Route::post('sale-notes/dispatch', 'Tenant\SaleNoteController@recordDispatch');
            Route::post('sale-notes/dispatch/statusUpdate', 'Tenant\SaleNoteController@statusUpdate');
            Route::delete('sale-notes/dispatch/delete/{id}', 'Tenant\SaleNoteController@destroyStatus');
            Route::get('sale-notes/dispatch_note/{id}', 'Tenant\SaleNoteController@recordsDispatchNote');

            //POS
            Route::get('pos', 'Tenant\PosController@index')->name('tenant.pos.index')->middleware('redirect.level');
            Route::get('pos_full', 'Tenant\PosController@index_full')->name('tenant.pos_full.index');

            Route::get('pos/search_items', 'Tenant\PosController@search_items');
            Route::get('pos/tables', 'Tenant\PosController@tables');
            Route::get('pos/table/{table}', 'Tenant\PosController@table');
            Route::get('pos/payment_tables', 'Tenant\PosController@payment_tables');
            Route::get('pos/payment', 'Tenant\PosController@payment')->name('tenant.pos.payment');
            Route::get('pos/status_configuration', 'Tenant\PosController@status_configuration');
            Route::get('pos/validate_stock/{item}/{quantity}', 'Tenant\PosController@validate_stock');
            Route::get('pos/items', 'Tenant\PosController@item');
            Route::get('pos/search_items_cat', 'Tenant\PosController@search_items_cat');

            Route::get('cash', 'Tenant\CashController@index')->name('tenant.cash.index')->middleware('redirect.level');
            Route::get('cash/columns', 'Tenant\CashController@columns');
            Route::get('cash/records', 'Tenant\CashController@records');
            Route::get('cash/create', 'Tenant\CashController@create')->name('tenant.cash.create');
            Route::get('cash/tables', 'Tenant\CashController@tables');
            Route::get('cash/opening_cash', 'Tenant\CashController@opening_cash');
            Route::get('cash/opening_cash_check/{user_id}', 'Tenant\CashController@opening_cash_check');

            Route::post('cash', 'Tenant\CashController@store');
            Route::post('cash/cash_document', 'Tenant\CashController@cash_document');
            Route::get('cash/close/{cash}', 'Tenant\CashController@close');
            Route::get('cash/report/{cash}', 'Tenant\CashController@report');
            Route::get('cash/report', 'Tenant\CashController@report_general');

            Route::get('cash/record/{cash}', 'Tenant\CashController@record');
            Route::delete('cash/{cash}', 'Tenant\CashController@destroy');
            Route::get('cash/item/tables', 'Tenant\CashController@item_tables');
            Route::get('cash/search/customers', 'Tenant\CashController@searchCustomers');
            Route::get('cash/search/customer/{id}', 'Tenant\CashController@searchCustomerById');

            Route::get('cash/report/products/{cash}/{is_garage?}', 'Tenant\CashController@report_products');
            Route::get('cash/report/products-excel/{cash}', 'Tenant\CashController@report_products_excel');
            Route::get('cash/report/cash-excel/{cash}', 'Tenant\CashController@report_cash_excel');

            //POS VENTA RAPIDA
            Route::get('pos/fast', 'Tenant\PosController@fast')->name('tenant.pos.fast');
            Route::get('pos/garage', 'Tenant\PosController@garage')->name('tenant.pos.garage');


            //Tags
            Route::get('tags', 'Tenant\TagController@index')->name('tenant.tags.index');
            Route::get('tags/columns', 'Tenant\TagController@columns');
            Route::get('tags/records', 'Tenant\TagController@records');
            Route::get('tags/record/{tag}', 'Tenant\TagController@record');
            Route::post('tags', 'Tenant\TagController@store');
            Route::delete('tags/{tag}', 'Tenant\TagController@destroy');

            //Promotion
            Route::get('promotions', 'Tenant\PromotionController@index')->name('tenant.promotion.index');
            Route::get('promotions/columns', 'Tenant\PromotionController@columns');
            Route::get('promotions/tables', 'Tenant\PromotionController@tables');
            Route::get('promotions/records', 'Tenant\PromotionController@records');
            Route::get('promotions/record/{tag}', 'Tenant\PromotionController@record');
            Route::post('promotions', 'Tenant\PromotionController@store');
            Route::delete('promotions/{promotion}', 'Tenant\PromotionController@destroy');
            Route::post('promotions/upload', 'Tenant\PromotionController@upload');

            //Promotions-list
            Route::post('promotions-list', 'Tenant\PromotionController@storePromotionList');
            Route::get('promotions-list/records', 'Tenant\PromotionController@recordsPromotionList');

            //Spot-list
            Route::post('spot-list', 'Tenant\PromotionController@storeSpotList');
            Route::put('spot-list/{id}', 'Tenant\PromotionController@storeSpotList');
            Route::get('spot-list/records', 'Tenant\PromotionController@recordsSpotList');
            Route::get('spot-list/record/{id}', 'Tenant\PromotionController@record');

            Route::get('item-sets', 'Tenant\ItemSetController@index')->name('tenant.item_sets.index')->middleware('redirect.level');
            Route::get('item-sets/columns', 'Tenant\ItemSetController@columns');
            Route::get('item-sets/records', 'Tenant\ItemSetController@records');
            Route::get('item-sets/tables', 'Tenant\ItemSetController@tables');
            Route::get('item-sets/record/{item}', 'Tenant\ItemSetController@record');
            Route::post('item-sets', 'Tenant\ItemSetController@store');
            Route::delete('item-sets/{item}', 'Tenant\ItemSetController@destroy');
            Route::delete('item-sets/item-unit-type/{item}', 'Tenant\ItemSetController@destroyItemUnitType');
            Route::post('item-sets/import', 'Tenant\ItemSetController@import');
            Route::post('item-sets/upload', 'Tenant\ItemSetController@upload');
            Route::post('item-sets/visible_store', 'Tenant\ItemSetController@visibleStore');
            Route::get('item-sets/item/tables', 'Tenant\ItemSetController@item_tables');

            Route::get('person-types/columns', 'Tenant\PersonTypeController@columns');
            Route::get('person-types', 'Tenant\PersonTypeController@index')->name('tenant.person_types.index');
            Route::get('person-types/records', 'Tenant\PersonTypeController@records');
            Route::get('person-types/record/{person}', 'Tenant\PersonTypeController@record');
            Route::post('person-types', 'Tenant\PersonTypeController@store');
            Route::delete('person-types/{person}', 'Tenant\PersonTypeController@destroy');

            //Cuenta
            Route::get('cuenta/payment_index', 'Tenant\AccountController@paymentIndex')->name('tenant.payment.index');
            Route::get('cuenta/configuration', 'Tenant\AccountController@index')->name('tenant.configuration.index');
            Route::get('cuenta/payment_records', 'Tenant\AccountController@paymentRecords');
            Route::get('cuenta/tables', 'Tenant\AccountController@tables');
            Route::post('cuenta/update_plan', 'Tenant\AccountController@updatePlan');
            Route::post('cuenta/payment_culqui', 'Tenant\AccountController@paymentCulqui')->name('tenant.account.payment_culqui');

            //Payment Methods
            Route::get('payment_method/records', 'Tenant\PaymentMethodTypeController@records');
            Route::get('payment_method/record/{code}', 'Tenant\PaymentMethodTypeController@record');
            Route::post('payment_method', 'Tenant\PaymentMethodTypeController@store');
            Route::delete('payment_method/{code}', 'Tenant\PaymentMethodTypeController@destroy');

            //formats PDF
            Route::get('templates', 'Tenant\FormatTemplateController@records');
            // Configuración del Login
            Route::get('login-page', 'Tenant\LoginConfigurationController@index')->name('tenant.login_page')->middleware('redirect.level');
            Route::post('login-page/upload-bg-image', 'Tenant\LoginConfigurationController@uploadBgImage');
            Route::post('login-page/update', 'Tenant\LoginConfigurationController@update');


            Route::post('extra_info/items', 'Tenant\ExtraInfoController@getExtraDataForItems');

            //liquidacion de compra
            Route::get('purchase-settlements', 'Tenant\PurchaseSettlementController@index')->name('tenant.purchase-settlements.index')->middleware('redirect.level');
            Route::get('purchase-settlements/columns', 'Tenant\PurchaseSettlementController@columns');
            Route::get('purchase-settlements/records', 'Tenant\PurchaseSettlementController@records');

            Route::get('purchase-settlements/create/{order_id?}', 'Tenant\PurchaseSettlementController@create')->name('tenant.purchase-settlements.create')->middleware('redirect.level');

            Route::post('purchase-settlements', 'Tenant\PurchaseSettlementController@store');
            Route::get('purchase-settlements/tables', 'Tenant\PurchaseSettlementController@tables');
            Route::get('purchase-settlements/table/{table}', 'Tenant\PurchaseSettlementController@table');
            Route::get('purchase-settlements/record/{document}', 'Tenant\PurchaseSettlementController@record');

            //Almacen de columnas por usuario
            Route::post('validate_columns','Tenant\SettingController@getColumnsToDatatable');

            Route::post('general-upload-temp-image', 'Controller@generalUploadTempImage');

            Route::get('general-get-current-warehouse', 'Controller@generalGetCurrentWarehouse');

            // ── Marketplace Integration ──
            Route::prefix('marketplace')->group(function () {
                Route::get('/channels', 'MarketplaceController@channels');
                Route::post('/channels', 'MarketplaceController@storeChannel');
                Route::put('/channels/{id}', 'MarketplaceController@updateChannel');
                Route::get('/channels/{channelId}/products', 'MarketplaceController@products');
                Route::post('/channels/{channelId}/auto-map', 'MarketplaceController@autoMapProducts');
                Route::post('/products/map', 'MarketplaceController@mapProduct');
                Route::post('/channels/{channelId}/sync/products', 'MarketplaceController@syncProducts');
                Route::post('/channels/{channelId}/sync/stock', 'MarketplaceController@syncStock');
                Route::post('/channels/{channelId}/fetch/orders', 'MarketplaceController@fetchOrders');
                Route::post('/channels/{channelId}/generate-feed', 'MarketplaceController@generateFeed');
                Route::get('/orders', 'MarketplaceController@orders');
                Route::get('/logs', 'MarketplaceController@logs');
            });

            // test theme
            // Route::get('testtheme', function () {
            //     return view('tenant.layouts.partials.testtheme');
            // });


        });
    });
} else {
    $prefix = env('PREFIX_URL',null);
    $prefix = !empty($prefix)?$prefix.".":'';
    $app_url = $prefix. env('APP_URL_BASE');

    Route::domain($app_url)->group(function () {
        Route::get('login', 'System\LoginController@showLoginForm')->name('login');
        Route::post('login', 'System\LoginController@login')->middleware('throttle:10,1');
        Route::post('logout', 'System\LoginController@logout')->name('logout');

        // ── 2FA — verificación durante login (sin auth) ──────────────────────
        Route::get('2fa/verify',  'System\TwoFactorController@showVerify')->name('system.2fa.verify');
        Route::post('2fa/verify', 'System\TwoFactorController@verify')->middleware('throttle:5,1');

        // ── 2FA — setup y gestión (requiere auth admin) ──────────────────────
        Route::middleware(['auth:admin'])->group(function () {
            Route::get('2fa/setup',    'System\TwoFactorController@showSetup')->name('system.2fa.setup');
            Route::post('2fa/enable',  'System\TwoFactorController@enable')->name('system.2fa.enable');
            Route::post('2fa/disable', 'System\TwoFactorController@disable')->name('system.2fa.disable');
        });

        Route::get('phone', 'System\UserController@getPhone')->middleware('auth:admin');
        
        //guest-Register
        Route::prefix('guest-register')->group(function () {     
             
            Route::get('/disabled', 'System\GuestRegisterController@disabled')->name('guest.register.disabled');

            Route::middleware('enable.guest.register')->group(function () {
                Route::get('/', 'System\GuestRegisterController@index')->name('guest.register.index');
                Route::post('register', 'System\GuestRegisterController@register');
                Route::post('resend-email', 'System\GuestRegisterController@resendEmail');
                Route::get('email/verify/{id}/{hash}/{client_id}', 'System\GuestRegisterController@verifyGuestRegisteredEmail')->name('guest-register.verification.verify');
                Route::get('service/ruc/{number}', 'System\ServiceController@ruc');
            });
        });
        //guest-Register
        // Route::prefix('guest-register')->group(function () {       
        //     $config = Configuration::first();
        //     if ($config && $config->enable_guest_register) {     
        //         Route::get('/', 'System\GuestRegisterController@index')->name('guest.register.index');
        //         Route::post('register', 'System\GuestRegisterController@register');
        //         Route::post('resend-email', 'System\GuestRegisterController@resendEmail');
        //         Route::get('email/verify/{id}/{hash}/{client_id}', 'System\GuestRegisterController@verifyGuestRegisteredEmail')->name('guest-register.verification.verify');
        //         Route::get('service/ruc/{number}', 'System\ServiceController@ruc');
        //     }else{
        //         Route::get('/', 'System\GuestRegisterController@disabled')->name('guest.register.disabled');
        //     }
        // });

        // ─── Marketplace público (ebaemy.com/marketplace) ────────────────────
        // Vitrina agregadora: productos de tenants opt-in. Redirige la compra
        // a un lead que se convierte en Order dentro del tenant.
        Route::get('marketplace',                       'MarketplaceController@index')->name('marketplace.index');
        // URL canónica de categoría oficial (Fase D). fullSlug puede contener slashes (p.ej. hogar/muebles/sillas).
        Route::get('marketplace/c/{fullSlug}',          'MarketplaceController@categoryOfficial')
             ->where('fullSlug', '[a-z0-9\-/]+')
             ->name('marketplace.category_official');
        Route::get('marketplace/categoria/{categorySlug}', 'MarketplaceController@category')->name('marketplace.category');
        Route::get('marketplace/tienda/{subdomain}',    'MarketplaceController@tenantPage')
             ->where('subdomain', '[a-z0-9][a-z0-9\-]{1,62}')
             ->name('marketplace.tenant');
        Route::get('marketplace/item/{slug}',           'MarketplaceController@show')->name('marketplace.item');
        Route::get('marketplace/go/{slug}',       'MarketplaceController@go')->name('marketplace.go');
        Route::post('marketplace/item/{slug}/solicitar', 'MarketplaceController@lead')
             ->middleware('throttle:10,1')
             ->name('marketplace.lead');
        Route::post('marketplace/item/{slug}/review', 'MarketplaceController@review')
             ->middleware('throttle:5,1')
             ->name('marketplace.review');
        Route::get('marketplace/gracias/{slug}',  'MarketplaceController@thanks')->name('marketplace.thanks');
        Route::get('sitemap-marketplace.xml',     'MarketplaceController@sitemap')->name('marketplace.sitemap');
        Route::get('robots.txt',                  'MarketplaceController@robots')->name('marketplace.robots');
        Route::get('feeds/meta-catalog.xml',      'MarketplaceController@metaCatalog')->name('marketplace.feed.meta');
        Route::get('feeds/google-merchant.xml',   'MarketplaceController@metaCatalog')->name('marketplace.feed.google');

        // ─── Carrito multi-tienda (marketplace central) ──────────────────────
        Route::get('marketplace/cart',             'MarketplaceCartController@show')->name('marketplace.cart');
        Route::get('marketplace/cart/json',        'MarketplaceCartController@summary')->name('marketplace.cart.json');
        Route::post('marketplace/cart',            'MarketplaceCartController@add')
             ->middleware('throttle:60,1')->name('marketplace.cart.add');
        Route::patch('marketplace/cart/{listing}', 'MarketplaceCartController@update')
             ->whereNumber('listing')->name('marketplace.cart.update');
        Route::delete('marketplace/cart/{listing}','MarketplaceCartController@destroy')
             ->whereNumber('listing')->name('marketplace.cart.destroy');
        Route::delete('marketplace/cart',          'MarketplaceCartController@clear')->name('marketplace.cart.clear');

        // ─── Checkout multi-tienda ───────────────────────────────────────────
        Route::get('marketplace/checkout',         'MarketplaceCheckoutController@show')
             ->middleware('throttle:30,1')->name('marketplace.checkout');
        Route::post('marketplace/checkout',        'MarketplaceCheckoutController@store')
             ->middleware('throttle:6,1')->name('marketplace.checkout.store');
        Route::get('marketplace/order/{number}',   'MarketplaceCheckoutController@confirmation')
             ->middleware('throttle:30,1')
             ->where('number', 'MP-[A-Z0-9\-]+')->name('marketplace.order.confirmation');

        // ─── Onboarding de sellers (captación pública) ───────────────────────
        // Flujo completo:
        //   /seller                          → landing informativa
        //   /seller/register                 → form multi-paso de pre-registro
        //   /seller/register/validate-ruc    → AJAX: autocomplete desde SUNAT
        //   /seller/register/check-subdomain → AJAX: disponibilidad
        //   /seller/application/{token}      → portal de seguimiento
        // La aprobación manual la hace el SuperAdmin desde
        // /admin/seller-applications (definido arriba, bajo auth:admin).
        Route::get('seller', 'SellerLandingController@show')->name('seller.landing');
        Route::get('seller/access',  'SellerLandingController@access')->name('seller.access');
        Route::post('seller/access', 'SellerLandingController@accessGo')
             ->middleware('throttle:20,1')->name('seller.access.go');
        Route::get('seller/register', 'SellerRegistrationController@create')->name('seller.register');
        Route::post('seller/register', 'SellerRegistrationController@store')
             ->middleware('throttle:5,60')
             ->name('seller.register.store');
        Route::get('seller/register/validate-ruc', 'SellerRegistrationController@validateRuc')
             ->middleware('throttle:30,1')
             ->name('seller.register.validate_ruc');
        Route::get('seller/register/check-subdomain', 'SellerRegistrationController@checkSubdomain')
             ->middleware('throttle:30,1')
             ->name('seller.register.check_subdomain');
        Route::post('seller/upload-logo', 'SellerRegistrationController@uploadLogo')
             ->middleware('throttle:6,60')
             ->name('seller.upload_logo');
        Route::get('seller/application/{token}', 'SellerApplicationStatusController@show')
             ->where('token', '[A-Za-z0-9]+')
             ->name('seller.application.status');
        Route::post('seller/application/resend', 'SellerRegistrationController@resendTracking')
             ->middleware('throttle:3,60')
             ->name('seller.application.resend');

        // Solicitud de ACTIVACIÓN de tienda virtual para tenants existentes
        // (clientes de facturación/POS que aún no tienen marketplace_enabled).
        Route::get('seller/request-activation', 'SellerRegistrationController@createActivation')
             ->name('seller.request_activation');
        Route::post('seller/request-activation', 'SellerRegistrationController@storeActivation')
             ->middleware('throttle:5,60')
             ->name('seller.request_activation.store');

        // Aliases SEO/marketing — todas redirigen 301 a /seller para no
        // dispersar autoridad de página. Los CTAs en redes sociales y ads
        // pueden apuntar a la URL más natural según la campaña.
        foreach (['vender', 'vende-en-ebaemy', 'vende', 'crear-tienda', 'crear-tienda-gratis'] as $alias) {
            Route::get($alias, function () {
                return redirect()->route('seller.landing', [], 301);
            });
        }

        // Página pública de planes y precios
        Route::get('precios', 'PricingController@show')->name('pricing');
        Route::get('planes', function () { return redirect()->route('pricing', [], 301); });

        // ─── Opt-out público (cancelar suscripción marketing) ────────────────
        // El token va incrustado en cada mensaje, no requiere login.
        Route::get('unsubscribe/{token}',  'MarketingOptOutController@show')
             ->where('token', '[A-Za-z0-9]+')->name('marketing.optout.show');
        Route::post('unsubscribe/{token}', 'MarketingOptOutController@confirm')
             ->where('token', '[A-Za-z0-9]+')->name('marketing.optout.confirm');

        // ─── Webhook inbound marketing (Meta Cloud / QR API) ─────────────────
        // El handshake de verificación es GET (Meta lo invoca al configurar el
        // webhook). Los mensajes inbound entran por POST. STOP automático.
        Route::get('webhooks/marketing/inbound',  'MarketingInboundController@verify')
             ->name('marketing.webhook.verify');
        Route::post('webhooks/marketing/inbound', 'MarketingInboundController@inbound')
             ->name('marketing.webhook.inbound');

        // Root del central: SIEMPRE redirige al marketplace público.
        // El SuperAdmin puede acceder a su dashboard manualmente vía
        // /dashboard o /login — no tiene sentido que al visitar
        // ebaemy.com vea el panel administrativo.
        Route::get('/', function () {
            return redirect()->route('marketplace.index');
        });

        Route::middleware('auth:admin')->group(function () {
            Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
            Route::get('dashboard', 'System\HomeController@index')->name('system.dashboard');

            // ── Categorías oficiales del marketplace (árbol global) ──────────
            Route::prefix('admin/marketplace/categories')->name('system.marketplace_categories.')->group(function () {
                Route::get('/',               'System\MarketplaceCategoryController@index')->name('index');
                Route::get('tree',            'System\MarketplaceCategoryController@tree')->name('tree');
                Route::get('records',         'System\MarketplaceCategoryController@records')->name('records');
                Route::get('unclassified',    'System\MarketplaceCategoryController@unclassifiedListings')->name('unclassified');
                Route::get('migration-stats', 'System\MarketplaceCategoryController@categoryMigrationStats')->name('migration_stats');
                Route::get('bulk-assign',     function() { return view('system.marketplace_categories.bulk-assign'); })->name('bulk_assign');
                Route::post('/',              'System\MarketplaceCategoryController@store')->name('store');
                Route::put('{id}',            'System\MarketplaceCategoryController@update')->name('update')->whereNumber('id');
                Route::post('{id}/toggle',    'System\MarketplaceCategoryController@toggle')->name('toggle')->whereNumber('id');
                Route::delete('{id}',         'System\MarketplaceCategoryController@destroy')->name('destroy')->whereNumber('id');
                Route::post('assign-bulk',    'System\MarketplaceCategoryController@assignBulk')->name('assign_bulk');
            });

            // ── Solicitudes de nuevas categorías (sellers piden) ─────────────
            Route::prefix('admin/marketplace/category-requests')->name('system.marketplace_category_requests.')->group(function () {
                Route::get('/',              'System\MarketplaceCategoryRequestController@index')->name('index');
                Route::get('records',        'System\MarketplaceCategoryRequestController@records')->name('records');
                Route::get('{id}',           'System\MarketplaceCategoryRequestController@show')->name('show')->whereNumber('id');
                Route::post('{id}/approve',  'System\MarketplaceCategoryRequestController@approve')->name('approve')->whereNumber('id');
                Route::post('{id}/reject',   'System\MarketplaceCategoryRequestController@reject')->name('reject')->whereNumber('id');
            });

            // ── Pedidos multi-tienda creados desde el marketplace central ────
            Route::prefix('admin/marketplace/orders')->name('system.marketplace_orders.')->group(function () {
                Route::get('/',              'System\MarketplaceOrderController@index')->name('index');
                Route::get('records',        'System\MarketplaceOrderController@records')->name('records');
                Route::get('{id}',           'System\MarketplaceOrderController@show')->name('show')->whereNumber('id');
                Route::post('{id}/retry',    'System\MarketplaceOrderController@retry')->name('retry')->whereNumber('id');
                Route::post('{id}/cancel',   'System\MarketplaceOrderController@cancel')->name('cancel')->whereNumber('id');
                Route::post('{id}/sub/{subId}/retry', 'System\MarketplaceOrderController@retrySubOrder')
                     ->name('sub_retry')->whereNumber('id')->whereNumber('subId');
            });

            // ── Marketing centralizado (campañas multicanal con opt-out) ────
            Route::prefix('admin/marketing/campaigns')->name('system.marketing.campaigns.')->group(function () {
                Route::get('/',               'System\MarketingCampaignController@index')->name('index');
                Route::get('create',          'System\MarketingCampaignController@create')->name('create');
                Route::post('/',              'System\MarketingCampaignController@store')->name('store');
                Route::get('{id}',            'System\MarketingCampaignController@show')->name('show')->whereNumber('id');
                Route::post('{id}/build',     'System\MarketingCampaignController@buildTargets')->name('build')->whereNumber('id');
                Route::post('{id}/dispatch',  'System\MarketingCampaignController@sendBatch')->name('dispatch')->whereNumber('id');
                Route::post('{id}/dispatch-async', 'System\MarketingCampaignController@sendAsync')->name('dispatch_async')->whereNumber('id');
            });

            // ── Solicitudes de sellers (onboarding con aprobación manual) ────
            Route::prefix('admin/seller-applications')->name('system.seller_applications.')->group(function () {
                Route::get('/',                       'System\SellerApplicationController@index')->name('index');
                Route::get('records',                 'System\SellerApplicationController@records')->name('records');
                Route::get('{id}',                    'System\SellerApplicationController@show')->name('show')->whereNumber('id');
                Route::post('{id}/under-review',      'System\SellerApplicationController@markUnderReview')->name('under_review')->whereNumber('id');
                Route::post('{id}/approve',           'System\SellerApplicationController@approve')->name('approve')->whereNumber('id');
                Route::post('{id}/reject',            'System\SellerApplicationController@reject')->name('reject')->whereNumber('id');
                Route::post('{id}/request-documents', 'System\SellerApplicationController@requestDocuments')->name('request_documents')->whereNumber('id');
                Route::post('{id}/notes',             'System\SellerApplicationController@addNote')->name('add_note')->whereNumber('id');
            });

            // ── Moderación marketplace central ──────────────────────────────
            Route::prefix('admin/marketplace')->name('system.marketplace.')->group(function () {
                Route::get('/',                       'System\MarketplaceAdminController@dashboard')->name('dashboard');
                Route::get('listings',                'System\MarketplaceAdminController@listings')->name('listings');
                Route::post('listings/{id}/status',   'System\MarketplaceAdminController@updateListingStatus')->name('listings.status');
                Route::post('listings/{id}/featured', 'System\MarketplaceAdminController@toggleListingFeatured')->name('listings.featured');
                Route::post('tenant/{clientId}/verify', 'System\MarketplaceAdminController@toggleTenantVerified')->name('tenant.verify');
                Route::get('reviews',                   'System\MarketplaceAdminController@reviews')->name('reviews');
                Route::post('reviews/{id}/approve',     'System\MarketplaceAdminController@approveReview')->name('reviews.approve');
                Route::post('reviews/{id}/reject',      'System\MarketplaceAdminController@rejectReview')->name('reviews.reject');
                Route::get('leads',                   'System\MarketplaceAdminController@leads')->name('leads');
                Route::get('leads/export',            'System\MarketplaceAdminController@exportLeads')->name('leads.export');
                Route::post('leads/{id}/retry',       'System\MarketplaceAdminController@retryLead')->name('leads.retry');
                Route::post('leads/{id}/archive',     'System\MarketplaceAdminController@archiveLead')->name('leads.archive');
            });

            //Clients
            Route::get('clients', 'System\ClientController@index')->name('system.clients.index');
            Route::get('clients/records', 'System\ClientController@records');
            Route::get('clients/record/{client}', 'System\ClientController@record');

            Route::get('clients/create', 'System\ClientController@create');
            Route::get('clients/tables', 'System\ClientController@tables');
            Route::get('clients/charts', 'System\ClientController@charts');
            Route::post('clients', 'System\ClientController@store');
            Route::post('clients/update', 'System\ClientController@update');
            Route::get('clients/search', 'System\ClientController@search');

            Route::get('clients/{client}/domains-panel', 'System\ClientController@domains')->name('system.client.domains');

            Route::delete('clients/{client}/{input_validate}', 'System\ClientController@destroy');
            // Route::delete('clients/{client}', 'System\ClientController@destroy');

            Route::post('clients/password/{client}', 'System\ClientController@password');
            Route::post('clients/locked_emission', 'System\ClientController@lockedEmission');
            Route::post('clients/locked_tenant', 'System\ClientController@lockedTenant');
            // Route::post('clients/locked_tenant', 'System\ClientController@lockedTenant'); //Linea repetida

            Route::post('clients/locked_user', 'System\ClientController@lockedUser');
            Route::post('clients/renew_plan', 'System\ClientController@renewPlan');

            Route::post('clients/set_billing_cycle', 'System\ClientController@startBillingCycle');

            Route::post('clients/locked-by-column', 'System\ClientController@lockedByColumn');
            Route::post('secret-login', 'System\SecretLoginController@secretLogin')->middleware('throttle:10,1');

            Route::post('clients/upload', 'System\ClientController@upload');
            Route::get('clients/confirm-limit-reseller', 'System\ClientController@confirmLimitReseller');

            Route::get('client_payments/records/{client_id}', 'System\ClientPaymentController@records');
            Route::get('client_payments/client/{client_id}', 'System\ClientPaymentController@client');
            Route::get('client_payments/tables', 'System\ClientPaymentController@tables');
            Route::post('client_payments', 'System\ClientPaymentController@store');
            Route::delete('client_payments/{client_payment}', 'System\ClientPaymentController@destroy');
            Route::post('client_payments/cancel_payment/{client_payment_id}', 'System\ClientPaymentController@cancel_payment');

            Route::get('client_account_status/records/{client_id}', 'System\AccountStatusController@records');
            Route::get('client_account_status/client/{client_id}', 'System\AccountStatusController@client');
            Route::get('client_account_status/tables', 'System\AccountStatusController@tables');

            //Planes
            Route::get('plans', 'System\PlanController@index')->name('system.plans.index');
            Route::get('plans/records', 'System\PlanController@records');
            Route::get('plans/tables', 'System\PlanController@tables');
            Route::get('plans/record/{plan}', 'System\PlanController@record');
            Route::post('plans', 'System\PlanController@store');
            Route::delete('plans/{plan}', 'System\PlanController@destroy');
            Route::get('plans/{plan}/features',  'System\PlanController@features');
            Route::post('plans/{plan}/features', 'System\PlanController@syncFeatures');

            // Themes
            Route::get('themes', 'System\ThemeController@index')->name('system.themes.index');
            Route::get('themes/records', 'System\ThemeController@records');
            Route::get('themes/available', 'System\ThemeController@available');
            Route::post('themes', 'System\ThemeController@store');
            Route::post('themes/toggle/{id}', 'System\ThemeController@toggleStatus');
            Route::delete('themes/{id}', 'System\ThemeController@destroy');
            Route::post('themes/{id}/install', 'System\ThemeController@installForClient');

            // Dominios de clientes
            Route::get('clients/{client}/domains', 'System\ClientDomainController@index');
            Route::post('clients/{client}/domains', 'System\ClientDomainController@store');
            Route::post('domains/{verification}/verify', 'System\ClientDomainController@verify');
            Route::get('domains/{verification}/instructions', 'System\ClientDomainController@getVerificationInstructions');
            Route::post('hostnames/{hostname}/set-primary', 'System\ClientDomainController@setPrimary');
            Route::post('hostnames/{hostname}/toggle-redirect', 'System\ClientDomainController@toggleRedirect');
            Route::post('hostnames/{hostname}/change-subdomain', 'System\ClientDomainController@changeSubdomain');
            Route::delete('hostnames/{hostname}', 'System\ClientDomainController@destroy');

            //Pagos
            Route::get('payment-orders', 'System\PaymentOrderController@index')->name('system.payments.index');
            Route::get('payment-orders/records', 'System\PaymentOrderController@records');
            Route::get('payment-orders/tables', 'System\PaymentOrderController@tables');
            Route::post('payment-orders/cancel/{id}','System\PaymentOrderController@cancel' );
            Route::post('payment-orders/pays/{id}','System\PaymentOrderController@pays' );
            Route::post('payment-orders/notify/{id}','System\PaymentOrderController@notify' );
            Route::post('payment-orders/updateTable','System\PaymentOrderController@updateTable');
            Route::post('payment-orders/create','System\PaymentOrderController@create' );
            Route::post('payment-orders/updateClient/{id}','System\PaymentOrderController@updateClient' );
            Route::get('payment-orders/client/tables','System\PaymentOrderController@clientTables' );


            //Massive Invoice
            Route::get('massive-invoice', 'System\MassiveInvoiceController@index')->name('system.massive-invoice.index');
            Route::get('massive-invoice/download-format', 'System\MassiveInvoiceController@downloadFormat')->name('system.massive-invoice.download');
            Route::post('massive-invoice/upload', 'System\MassiveInvoiceController@upload')->name('system.massive-invoice.upload');
            Route::post('massive-invoice/process', 'System\MassiveInvoiceController@process')->name('system.massive-invoice.process');
            Route::get('massive-invoice/config', 'System\MassiveInvoiceController@config');
            Route::get('massive-invoice/records', 'System\MassiveInvoiceController@records');
            Route::get('massive-invoice/download/{id}/{type}', 'System\MassiveInvoiceController@downloadFile');

            //Users
            Route::get('users/create', 'System\UserController@create')->name('system.users.create');
            Route::get('users/record', 'System\UserController@record');
            Route::post('users', 'System\UserController@store');

            Route::get('services/ruc/{number}', 'System\ServiceController@ruc');

            Route::get('certificates/record', 'System\CertificateController@record');
            Route::post('certificates/uploads', 'System\CertificateController@uploadFile');
            Route::post('certificates/saveSoapUser', 'System\CertificateController@saveSoapUser');
            Route::delete('certificates', 'System\CertificateController@destroy');
            Route::get('configurations', 'System\ConfigurationController@index')->name('system.configuration.index');
            Route::post('configurations/login', 'System\ConfigurationController@storeLoginSettings');
            Route::post('configurations/bg', 'System\ConfigurationController@storeBgLogin');
            Route::post('configurations/other-configuration', 'System\ConfigurationController@storeOtherConfiguration');
            Route::get('configurations/get-other-configuration', 'System\ConfigurationController@getOtherConfiguration');
            Route::get('configurations/seller-onboarding', 'System\ConfigurationController@getSellerOnboarding');
            Route::post('configurations/seller-onboarding', 'System\ConfigurationController@storeSellerOnboarding');
            Route::post('configurations/upload-tenant-ads', 'System\ConfigurationController@uploadTenantAds');

            Route::get('companies/record', 'System\CompanyController@record');
            Route::post('companies', 'System\CompanyController@store');

            // auto-update
            Route::get('auto-update', 'System\UpdateController@index')->name('system.update');
            Route::get('auto-update/branch', 'System\UpdateController@branch')->name('system.update.branch');
            Route::post('auto-update/pull/{branch}', 'System\UpdateController@pull')->name('system.update.pull');
            Route::post('auto-update/artisan/migrate', 'System\UpdateController@artisanMigrate')->name('system.update.artisan.migrate');
            Route::post('auto-update/artisan/migrate/tenant', 'System\UpdateController@artisanTenancyMigrate')->name('system.update.artisan.tenancy.migrate');
            Route::post('auto-update/artisan/clear', 'System\UpdateController@artisanClear')->name('system.update.artisan.clear');
            Route::post('auto-update/composer/install', 'System\UpdateController@composerInstall')->name('system.update.composer.install');
            Route::post('auto-update/keygen', 'System\UpdateController@keygen')->name('system.update.keygen');
            Route::get('auto-update/version', 'System\UpdateController@version')->name('system.update.version');
            Route::get('auto-update/changelog', 'System\UpdateController@changelog')->name('system.changelog');

            //Configuration

            Route::post('configurations', 'System\ConfigurationController@store');
            Route::get('configurations/record', 'System\ConfigurationController@record');
            
            // Visual theme configuration routes
            Route::post('configurations/visual-theme', 'System\ConfigurationController@storeVisualTheme');
            Route::get('configurations/visual-theme', 'System\ConfigurationController@getVisualTheme');
            
            Route::get('information', 'System\ConfigurationController@InfoIndex')->name('system.information');
            Route::get('status/history', 'System\StatusController@history')->name('system.status');
            Route::get('status/memory', 'System\StatusController@memory')->name('system.status.memory');
            Route::get('status/cpu', 'System\StatusController@cpu')->name('system.status.cpu');
            Route::get('configurations/apiruc', 'System\ConfigurationController@apiruc');
            Route::get('configurations/apkurl', 'System\ConfigurationController@apkurl');
            Route::post('configurations/emails', 'System\ConfigurationController@emails');
            Route::post('configurations/qrapi', 'System\ConfigurationController@qrapi');

            Route::post('configurations/update-tenant-discount-type-base', 'System\ConfigurationController@updateTenantDiscountTypeBase');

            // ── Analytics (Data Warehouse) ─────────────────────────────────────
            Route::get('analytics', 'System\WarehouseAnalyticsController@index')->name('system.analytics');
            Route::get('analytics/global-kpis',        'System\WarehouseAnalyticsController@globalKpis');
            Route::get('analytics/daily-sales',        'System\WarehouseAnalyticsController@dailySales');
            Route::get('analytics/top-tenants',        'System\WarehouseAnalyticsController@topTenants');
            Route::get('analytics/by-doc-type',        'System\WarehouseAnalyticsController@byDocType');
            Route::get('analytics/plan-distribution',  'System\WarehouseAnalyticsController@planDistribution');
            Route::get('analytics/etl-log',            'System\WarehouseAnalyticsController@etlLog');

            // backup
            Route::get('backup', 'System\BackupController@index')->name('system.backup');
            Route::post('backup/db', 'System\BackupController@db')->name('system.backup.db');
            Route::post('backup/files', 'System\BackupController@files')->name('system.backup.files');
            Route::post('backup/upload', 'System\BackupController@upload')->name('system.backup.upload');

            Route::get('backup/last-backup', 'System\BackupController@mostRecent');
            Route::get('backup/download/{filename}', 'System\BackupController@download');

            // demo_environments
            Route::get('demo_environments/files', 'System\DemoEnvironmentController@getFiles')->name('system.demo.getfiles');
            Route::post('demo_environments/backup-create', 'System\DemoEnvironmentController@create')->name('system.demo.create');
            Route::post('demo_environments/backup-restore', 'System\DemoEnvironmentController@restore')->name('system.demo.restore');
            Route::post('demo_environments/enable-cron', 'System\DemoEnvironmentController@enableCron')->name('system.demo.cron');
            Route::get('demo_environments/client/{client_id}', 'System\DemoEnvironmentController@client');

            /*
            Route::get('ajuste_claves_mysql', function(){

                $sites = \Hyn\Tenancy\Models\Website::all();
                $passwords = [];
                foreach($sites as $site){
                    $contra =md5(sprintf(
                                     '%s.%d',
                                     \Config::get('app.key'),
                                     $site->id
                                 ));
                    $temp = [
                        'username'=>$site->uuid,
                        'password'=>$contra,
                        'query'=>"SET PASSWORD FOR '{$site->uuid}'@'%' = PASSWORD('$contra');"
                    ];
                    $passwords[] = $temp;
                    \DB::update( $temp['query'] );
                }
            });
            */


        });
    });
}
