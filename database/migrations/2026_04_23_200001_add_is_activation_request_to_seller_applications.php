<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag que distingue dos tipos de SellerApplication:
 *
 *   - false (default): solicitud de ONBOARDING NUEVO
 *     El seller no es cliente todavía. Al aprobar, TenantCreationService
 *     crea tenant completo (Website + Client + Company + User admin + etc).
 *
 *   - true: solicitud de ACTIVACIÓN de tienda virtual sobre un tenant
 *     EXISTENTE. El seller ya es cliente de ebaemy (usa facturación/POS
 *     pero sin marketplace). Al aprobar, NO se crea tenant — solo se
 *     setea marketplace_enabled=true, seller_status=active en el client
 *     existente y se agrega el módulo ecommerce al usuario admin.
 *
 * La UI del panel SuperAdmin usa este flag para mostrar badges distintos
 * y no pedir plan/contraseña al aprobar una activación.
 */
class AddIsActivationRequestToSellerApplications extends Migration
{
    public function up()
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->boolean('is_activation_request')
                  ->default(false)
                  ->after('tenant_id')
                  ->index();
        });
    }

    public function down()
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn('is_activation_request');
        });
    }
}
