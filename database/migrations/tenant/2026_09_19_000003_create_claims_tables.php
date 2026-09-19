<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de Reclamaciones (D.S. 011-2011-PCM, modificado por D.S. 101-2022-PCM).
 *
 * Hasta ahora el formulario de la tienda no guardaba nada: armaba un correo y
 * lo mandaba al contacto del ecommerce. Sin registro no hay libro — el
 * reglamento exige conservar la hoja de reclamación con numeración correlativa
 * y poder entregarle copia al consumidor.
 *
 * El aislamiento por tenant lo da la arquitectura: estas tablas viven en la BD
 * del tenant (conexión `tenant`), así que no llevan `tenant_id` ni scopes. No
 * existe consulta capaz de cruzar de un tenant a otro.
 *
 * Idempotente (`hasTable` / `hasColumn`): hay 17 tenants y la migración se
 * corre varias veces durante el despliegue escalonado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('claims')) {
            $schema->create('claims', function (Blueprint $table) {
                $table->increments('id');

                // --- Identificación de la hoja ---
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('correlative');          // correlativo anual, exigido por el formato
                $table->string('code', 20)->unique();            // LR-2026-000001
                $table->string('public_token', 64)->unique();    // consulta y PDF sin sesión

                // --- Tipo (art. 5 del reglamento) ---
                $table->enum('type', ['reclamo', 'queja'])->default('reclamo');
                $table->enum('item_type', ['bien', 'servicio'])->default('bien');

                // --- Consumidor ---
                $table->string('document_type', 10)->default('DNI');
                $table->string('document_number', 20);
                $table->string('names', 100);
                $table->string('surnames', 100);
                $table->string('email', 120);
                $table->string('phone', 30)->nullable();
                $table->string('address', 255)->nullable();
                // Ubigeo: los catálogos usan códigos ('15', '1501', '150101'),
                // no enteros. Mismos anchos que `shipping_requests`.
                $table->string('department_id', 2)->nullable();
                $table->string('province_id', 4)->nullable();
                $table->string('district_id', 6)->nullable();
                $table->boolean('is_minor')->default(false);
                $table->string('guardian_name', 200)->nullable();

                // --- Bien o servicio contratado ---
                // FK a tablas legacy: unsignedInteger, sin constraint (los ids
                // históricos no siempre casan con el tipo de `foreignId`).
                $table->unsignedInteger('order_id')->nullable();
                $table->string('order_reference', 60)->nullable();   // lo que el cliente ve
                $table->date('purchase_date')->nullable();
                $table->string('currency', 3)->default('PEN');
                $table->decimal('amount', 12, 2)->nullable();
                $table->text('product_description');

                // --- Detalle y pedido concreto ---
                $table->text('detail');
                $table->text('consumer_request');
                $table->json('files')->nullable();

                // --- Plazo y estado ---
                $table->enum('status', ['registered', 'in_review', 'answered', 'closed'])
                      ->default('registered');
                $table->date('due_date');                        // 15 días hábiles, improrrogable
                $table->timestamp('answered_at')->nullable();
                $table->text('answer')->nullable();
                $table->text('actions_taken')->nullable();
                $table->boolean('request_accepted')->nullable();
                $table->text('rejection_grounds')->nullable();
                $table->unsignedInteger('answered_by_user_id')->nullable();

                // --- Correos: el fallo NO revierte el registro ---
                $table->string('customer_mail_status', 20)->default('pending');
                $table->text('customer_mail_error')->nullable();
                $table->timestamp('customer_mail_sent_at')->nullable();
                $table->string('tenant_mail_status', 20)->default('pending');
                $table->text('tenant_mail_error')->nullable();
                $table->timestamp('tenant_mail_sent_at')->nullable();
                $table->string('tenant_mail_to', 120)->nullable();
                $table->string('answer_mail_status', 20)->nullable();
                $table->text('answer_mail_error')->nullable();
                $table->timestamp('answer_mail_sent_at')->nullable();

                // --- Trazabilidad ---
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('submission_token', 64)->nullable(); // anti doble envío

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['year', 'correlative']);
                $table->index(['status', 'due_date']);
                $table->index('document_number');
                $table->index('email');
                $table->index('order_id');
                $table->index('submission_token');
            });
        }

        if (!$schema->hasTable('claim_events')) {
            $schema->create('claim_events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('claim_id');
                $table->string('action', 40);          // created | status_changed | answered | mail_sent | mail_failed
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('user_name', 100)->nullable();
                $table->text('description')->nullable();
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['claim_id', 'id']);
            });
        }

        // Correo propio del Libro. Sin esto habría que decidir a ciegas a qué
        // buzón del tenant va el reclamo, y «a ciegas» en multi-tenant termina
        // en el buzón equivocado.
        if ($schema->hasTable('configuration_ecommerce')
            && !$schema->hasColumn('configuration_ecommerce', 'claims_email')) {
            $schema->table('configuration_ecommerce', function (Blueprint $table) {
                $table->string('claims_email', 120)->nullable()->after('information_contact_email');
            });
        }

        // Nivel de acceso del panel, bajo el módulo Ecommerce (id 10).
        if ($schema->hasTable('module_levels')) {
            $exists = DB::connection('tenant')->table('module_levels')
                ->where('value', 'ecommerce_claims')->exists();

            if (!$exists) {
                DB::connection('tenant')->table('module_levels')->insert([
                    'value'       => 'ecommerce_claims',
                    'description' => 'Libro de Reclamaciones',
                    'module_id'   => 10,
                ]);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        $schema->dropIfExists('claim_events');
        $schema->dropIfExists('claims');

        if ($schema->hasTable('configuration_ecommerce')
            && $schema->hasColumn('configuration_ecommerce', 'claims_email')) {
            $schema->table('configuration_ecommerce', function (Blueprint $table) {
                $table->dropColumn('claims_email');
            });
        }

        if ($schema->hasTable('module_levels')) {
            DB::connection('tenant')->table('module_levels')
                ->where('value', 'ecommerce_claims')->delete();
        }
    }
};
