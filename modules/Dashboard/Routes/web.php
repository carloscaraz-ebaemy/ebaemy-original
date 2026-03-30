<?php
use Illuminate\Support\Facades\Route;
$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant', 'check.email.verified'])->group(function () {

            Route::redirect('/', '/dashboard');

            Route::prefix('dashboard')->group(function () {
                Route::get('/', 'DashboardController@index')->name('tenant.dashboard.index');
                Route::get('filter', 'DashboardController@filter');
                Route::post('data', 'DashboardController@data');
                Route::post('data_aditional', 'DashboardController@data_aditional');
                // Route::post('unpaid', 'DashboardController@unpaid');
                // Route::get('unpaidall', 'DashboardController@unpaidall')->name('unpaidall');
                Route::get('stock-by-product/records', 'DashboardController@stockByProduct');
                Route::get('product-of-due/records', 'DashboardController@productOfDue');
                Route::post('utilities', 'DashboardController@utilities');
                Route::get('global-data', 'DashboardController@globalData');
                Route::get('sales-by-product', 'DashboardController@salesByProduct');

                // V2 — Dashboard operativo
                Route::prefix('v2')->group(function () {
                    Route::get('summary',              'DashboardController@v2Summary');
                    Route::get('daily-chart',          'DashboardController@v2DailyChart');
                    Route::get('monthly-chart',        'DashboardController@v2MonthlyChart');
                    Route::get('sellers',              'DashboardController@v2TopSellers');
                    Route::get('top-products',         'DashboardController@v2TopProducts');
                    Route::get('stock-alerts',         'DashboardController@v2StockAlerts');
                    Route::get('purchases',            'DashboardController@v2Purchases');
                    Route::get('alerts',               'DashboardController@v2Alerts');
                    // Nuevos endpoints estadísticos
                    Route::get('receivables',          'DashboardController@v2Receivables');
                    Route::get('customers',            'DashboardController@v2Customers');
                    Route::get('payment-methods',      'DashboardController@v2PaymentMethods');
                    Route::get('profitability',        'DashboardController@v2Profitability');
                    Route::get('period-comparison',    'DashboardController@v2PeriodComparison');
                    Route::get('inventory-advanced',   'DashboardController@v2InventoryAdvanced');
                    Route::get('sales-by-hour',        'DashboardController@v2SalesByHour');
                    Route::get('quotation-conversion', 'DashboardController@v2QuotationConversion');
                    Route::get('sales-by-city',        'DashboardController@v2SalesByCity');
                });
            });

            //Commands
            Route::get('command/df', 'DashboardController@df')->name('command.df');

        });
    });
}
