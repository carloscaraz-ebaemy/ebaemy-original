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
 * La tienda se registra sola desde PRO 9 (botón "Publicar en ebaemy.com") y
 * queda `pending` hasta que el administrador la aprueba en
 * /admin/marketplace/tiendas-externas. Mientras tanto puede enviar productos,
 * que se guardan ocultos y salen todos al aprobarla.
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
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('api_key', 64)->unique();
            $table->text('api_secret');                       // cifrado (cast encrypted)
            $table->string('webhook_url', 255)->nullable();   // base del sistema del vendedor para avisarle pedidos
            $table->string('status', 20)->default('pending')->index(); // pending|approved|rejected|suspended
            $table->string('admin_notes', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_external_sellers');
    }
};
