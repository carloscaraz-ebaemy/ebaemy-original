<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendedores externos del marketplace: tiendas que NO son tenants de ebaemy
 * (hoy, los tenants de PRO 9 en devaemy.com) y publican por API firmada.
 *
 * Cada vendedor tiene un `hostnames` propio SIN website (fqdn = el dominio de
 * su tienda en el otro sistema). Así el resto del marketplace —listings,
 * carrito, subpedidos, página de tienda— sigue agrupando por hostname_id sin
 * saber que el vendedor es externo, y todo lo que entra a la BD del tenant ya
 * se salta los hostnames sin website.
 *
 * `api_secret` firma las peticiones en los dos sentidos (HMAC-SHA256): las que
 * el vendedor manda a ebaemy y los webhooks de pedidos que ebaemy le manda.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_external_sellers')) {
            return;
        }
        Schema::create('marketplace_external_sellers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hostname_id')->unique();
            $table->string('source', 20)->default('pro9');
            $table->string('fqdn', 180)->unique();
            $table->string('name', 160);
            $table->string('api_key', 64)->unique();
            $table->text('api_secret');                       // cifrado (cast encrypted)
            $table->string('webhook_url', 255)->nullable();   // base del sistema del vendedor para avisarle pedidos
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_external_sellers');
    }
};
