<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Canales Logísticos — Sistema de Stock Inteligente
|--------------------------------------------------------------------------
*/

// Canal del almacén — acceso para roles: admin, warehouse
// Filtra por tenant UUID para aislamiento multitenant total
Broadcast::channel('warehouse.{tenantUuid}', \App\Broadcasting\WarehouseChannel::class);

// Canal del cliente — cada cliente escucha solo sus pedidos
Broadcast::channel('customer.{customerId}', \App\Broadcasting\CustomerChannel::class);
