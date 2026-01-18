<?php
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function() {

        Route::get('/restaurant/list-waiter', 'WaiterController@listRecords');

        Route::middleware(['auth:api', 'locked.tenant'])->group(function() {

            Route::prefix('restaurant')->group(function () {
                Route::get('/items', 'RestaurantController@items');
                Route::post('/items/price', 'RestaurantController@savePrice');
                Route::post('/items/restaurant-favorite', 'RestaurantController@setRestaurantFavoriteItem');
                Route::post('/order/change-table', 'RestaurantController@changeTablePedido');

                Route::get('/items/stock', 'RestaurantController@getStockStatus');

                Route::get('/categories', 'RestaurantController@categories');
                Route::get('/configurations', 'RestaurantConfigurationController@record');
                Route::get('/waiters', 'WaiterController@records');
                Route::get('/tablesAndEnv', 'RestaurantConfigurationController@tablesAndEnv');
                Route::post('/table/toggle-active', 'RestaurantConfigurationController@toggleActive'); // Nueva ruta para activar/desactivar mesa

                Route::post('/table/cambiar-ambiente', 'RestaurantConfigurationController@cambiarAmbiente');
                Route::post('/table/restaurar-ambiente', 'RestaurantConfigurationController@restaurarAmbiente');

                Route::post('/table', 'RestaurantConfigurationController@createTable');
                Route::post('/table/{id}', 'RestaurantConfigurationController@saveTable');
                Route::get('/table/{id}', 'RestaurantConfigurationController@getTable');
                Route::get('/table/{id}', 'RestaurantConfigurationController@getTable');
                Route::get('/notes', 'NotesController@records');

                Route::get('/available-sellers', 'RestaurantConfigurationController@getSellers');
                Route::get('/correct_pin_check/{id}/{pin}', 'RestaurantConfigurationController@correctPinCheck');
                Route::post('/label-table/save', 'RestaurantConfigurationController@saveLabelTable');

                Route::post('/command-item/save', 'RestaurantItemOrderStatusController@saveItemOrder');
                Route::get('/command-status/items/{id}', 'RestaurantItemOrderStatusController@getStatusItems');
                Route::get('/command-status/served/{tableId}', 'RestaurantItemOrderStatusController@isProductsCommandStatusServer');
                Route::get('/command-status/set/{id}', 'RestaurantItemOrderStatusController@setStatusItem');

                Route::prefix('tables/group')->group(function () {
                    Route::post('create', 'TableGroupController@createGroup');
                    Route::post('add', 'TableGroupController@addTable');
                    Route::post('remove', 'TableGroupController@removeTable');
                    Route::post('disband', 'TableGroupController@disbandGroup');
                    Route::post('recalculate', 'TableGroupController@calculateTotal');
                });
            });

        });

    });
}
