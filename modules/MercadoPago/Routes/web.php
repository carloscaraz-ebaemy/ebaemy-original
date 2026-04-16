<?php

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if($hostname) 
{
    Route::domain($hostname->fqdn)->group(function () {

        Route::prefix('transactions')->middleware('throttle:10,1')->group(function () {
            Route::post('', 'TransactionController@store');
        });

        Route::middleware(['auth', 'locked.tenant'])->group(function() {
            Route::get('client-errors/records', 'ClientErrorController@records');
        });

    });
}
