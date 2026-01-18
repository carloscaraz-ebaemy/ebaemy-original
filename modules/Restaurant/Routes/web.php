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

// Rutas públicas (no deben forzar login principal)
Route::prefix('restaurant')->middleware(['locked.tenant'])->group(function() {
    Route::get('item_partial/{id}', 'RestaurantController@partialItem')->name('restaurant.item_partial');
    Route::get('item/{id}/{promotion_id?}', 'RestaurantController@item')->name('restaurant.item');
    Route::get('cart', 'RestaurantController@detailCart')->name('restaurant.detail.cart');
    Route::post('payment_cash', 'RestaurantController@paymentCash')->name('restaurant.payment.cash')->middleware('auth:ecommerce');
});

Route::prefix('restaurant')->middleware(['auth','check.email.verified'])->group(function() {
    // para configuracion de productos a mostrar
    Route::get('/list/items', 'RestaurantController@list_items')->name('tenant.restaurant.list_items')->middleware('redirect.module');
    Route::post('items/visible', 'RestaurantController@is_visible');

    // vista de configuracion general
    Route::get('configuration', 'RestaurantConfigurationController@configuration')->name('tenant.restaurant.configuration')->middleware('redirect.module');
    Route::get('configuration/record', 'RestaurantConfigurationController@record')->name('tenant.restaurant.configuration.record');
    Route::post('configuration', 'RestaurantConfigurationController@setConfiguration')->name('tenant.restaurant.configuration.set');
    Route::get('get-users', 'RestaurantConfigurationController@getUsers')->name('tenant.restaurant.users.get');
    Route::get('get-roles', 'RestaurantConfigurationController@getRoles')->name('tenant.restaurant.roles.get');
    Route::post('user/set-role', 'RestaurantConfigurationController@setRole')->name('tenant.restaurant.role.set');
    Route::post('user/delete-role', 'RestaurantConfigurationController@deleteRole')->name('tenant.restaurant.role.delete');
    Route::post('configuration/update-envs', 'RestaurantConfigurationController@updateTableEnv')->name('tenant.restaurant.configuration.envs');
    Route::get('configuration/get-envs', 'RestaurantConfigurationController@getEnvs')->name('tenant.restaurant.configuration.getEnvs');
    Route::post('configuration/create-envs', 'RestaurantConfigurationController@createTableEnv')->name('tenant.restaurant.configuration.createEnv');

    Route::prefix('notes')->group(function () {
        Route::get('records', 'NotesController@records');
        Route::post('/', 'NotesController@store');
        Route::delete('{id}', 'NotesController@destroy');
    });

    // Supplies (Insumos)
    Route::prefix('supplies')->group(function () {
        Route::get('', 'SupplyController@index')->name('tenant.restaurant.supplies.index')->middleware('redirect.module');
        Route::get('records', 'SupplyController@records');
        Route::get('unit-types', 'SupplyController@getUnitTypes');
        Route::post('/', 'SupplyController@store');
        Route::put('{id}/stock', 'SupplyController@updateStock');
        Route::delete('{id}', 'SupplyController@destroy');
    });

    // Item Supplies (Insumos por Item)
    Route::prefix('item-supplies')->group(function () {
        Route::get('{itemId}', 'RestaurantItemSupplyController@getItemSupplies');
        Route::post('', 'RestaurantItemSupplyController@storeItemSupplies');
        Route::post('discount-stock', 'RestaurantItemSupplyController@discountSuppliesStock');
        Route::get('available/list', 'RestaurantItemSupplyController@getAvailableSupplies');
    });

    // Modifier groups
    Route::get('modifier-groups', 'ModifierGroupController@indexPage')->middleware('redirect.module');
    Route::get('/modifier-groups/records', 'ModifierGroupController@records');
    Route::post('/modifier-groups', 'ModifierGroupController@store');
    Route::get('/modifier-groups/{id}', 'ModifierGroupController@show');
    Route::put('/modifier-groups/{id}', 'ModifierGroupController@update');
    Route::delete('/modifier-groups/{id}', 'ModifierGroupController@destroy');

    // Assign groups to item
    Route::post('/items/{itemId}/modifier-groups', 'ModifierGroupController@assignToItem');
    Route::get('/items/{itemId}/modifier-groups', 'ModifierGroupController@groupsForItem');

    // Preparation Areas (Áreas de preparación)
    Route::prefix('preparation-areas')->group(function () {
        Route::get('', 'PreparationAreaController@index');
        Route::post('', 'PreparationAreaController@store');
        Route::put('{id}', 'PreparationAreaController@update');
        Route::delete('{id}', 'PreparationAreaController@destroy');
    });

    //Promotion
    Route::prefix('promotions')->group(function() {

        Route::get('', 'PromotionController@index')->name('tenant.restaurant.promotion.index')->middleware('redirect.module');
        Route::get('columns', 'PromotionController@columns');
        Route::get('tables', 'PromotionController@tables');
        Route::get('records', 'PromotionController@records');
        Route::get('record/{tag}', 'PromotionController@record');
        Route::post('', 'PromotionController@store');
        Route::delete('{promotion}', 'PromotionController@destroy');
        Route::post('upload', 'PromotionController@upload');

    });

    //Spot-list (Anuncios publicitarios)
    Route::prefix('spot-list')->group(function() {
        Route::post('', 'PromotionController@storeSpotList');
        Route::put('{id}', 'PromotionController@storeSpotList');
        Route::get('records', 'PromotionController@recordsSpotList');
        Route::get('record/{id}', 'PromotionController@record');
        Route::delete('{id}', 'PromotionController@destroySpotList');
    });

    //Orders
    Route::prefix('orders')->group(function() {

        Route::get('', 'OrderController@index')->name('tenant.restaurant.order.index')->middleware('redirect.module');
        Route::get('columns', 'OrderController@columns');
        Route::get('records', 'OrderController@records');
        Route::get('record/{order}', 'OrderController@record');
        Route::get('pdf/{id}', 'OrderController@pdf');

        //warehouse
        Route::post('warehouse', 'OrderController@searchWarehouse');
        Route::get('tables', 'OrderController@tables');
        Route::get('tables/item/{internal_id}', 'OrderController@item');

    });

    //Cash
    Route::prefix('cash')->group(function() {

        Route::get('', 'CashController@index')->name('tenant.restaurant.cash.index')->middleware('redirect.module');
        Route::get('/pos', 'CashController@posFilter')->name('tenant.restaurant.cash.filter-pos');
        Route::get('records', 'CashController@records');
        Route::get('report', 'CashController@report_general');
        Route::get('columns', 'CashController@columns');

        Route::get('tables', 'CashController@tables');
        Route::get('opening_cash', 'CashController@opening_cash');
        Route::get('opening_cash_check/{user_id}', 'CashController@opening_cash_check');
        Route::post('cash', 'Tenant\CashController@store');
        Route::post('cash_document', 'CashController@cash_document');
        Route::get('close/{cash}', 'CashController@close');
        Route::get('report/{cash}', 'CashController@report');
        Route::get('report', 'CashController@report_general');
        Route::get('record/{cash}', 'CashController@record');
        Route::delete('{cash}', 'CashController@destroy');
        Route::get('item/tables', 'CashController@item_tables');
        Route::get('search/customers', 'CashController@searchCustomers');
        Route::get('search/customer/{id}', 'CashController@searchCustomerById');
        Route::get('report/products/{cash}', 'CashController@report_products');
        Route::get('report/products-excel/{cash}', 'CashController@report_products_excel');


    });

      //Waiters
    Route::prefix('waiter')->group(function() {
        Route::post('', 'WaiterController@store');
        Route::get('', 'WaiterController@records');
        Route::delete('{id}', 'WaiterController@destroy');
    });

    Route::prefix('users')->group(function() {
        Route::post('', 'RestaurantConfigurationController@userStore');
        Route::get('/record/{user}', 'RestaurantConfigurationController@userRecord');
    });


});

// ruta publica
Route::middleware(['locked.tenant'])->group(function() {
    // restaurant
    Route::get('/pedidos/{name?}', 'RestaurantController@menu')->name('tenant.restaurant.menu');


});

Route::middleware(['locked.tenant'])->group(function() {
    // ruta publica de lista
Route::get('/lista', 'ListaController@index')->name('restaurant.lista');
});