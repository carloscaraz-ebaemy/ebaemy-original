<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de onboarding de sellers del marketplace.
 *
 * A diferencia de `/guest-register` (que crea tenants automáticamente tras
 * verificar email), este flujo captura una SOLICITUD que debe ser revisada
 * y aprobada manualmente por el SuperAdmin antes de materializar el tenant.
 *
 * Al aprobar, SellerApplicationService invoca TenantCreationService con los
 * datos de esta solicitud y almacena el tenant_id resultante aquí.
 */
class CreateSellerApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('seller_applications', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Empresa ──────────────────────────────────────────
            $table->string('ruc', 20)->index();
            $table->string('business_name', 255);
            $table->string('trade_name', 255)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('fiscal_address', 500)->nullable();
            $table->string('department_id', 10)->nullable();
            $table->string('province_id', 10)->nullable();
            $table->string('district_id', 10)->nullable();

            // ── Responsable legal ─────────────────────────────────
            $table->string('legal_representative_name', 180);
            $table->string('legal_representative_dni', 20);
            $table->string('legal_representative_position', 100)->nullable();
            $table->string('email', 180)->index();
            $table->string('phone', 30);

            // ── Tienda (datos que se transferirán al tenant) ─────
            $table->string('requested_subdomain', 60)->unique();
            $table->string('store_name', 180)->nullable();
            $table->text('store_description')->nullable();
            $table->string('password_hash', 255)
                  ->comment('Bcrypt hash — NUNCA en texto plano');
            $table->string('logo_path', 500)->nullable();

            // ── Redes / web opcionales ───────────────────────────
            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('tiktok_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();

            // ── Snapshot de validación RUC (al momento de la solicitud) ──
            $table->string('ruc_status', 30)->nullable()
                  ->comment('ACTIVO / SUSPENDIDO / BAJA / UNKNOWN');
            $table->string('ruc_condition', 30)->nullable()
                  ->comment('HABIDO / NO_HALLADO / UNKNOWN');
            $table->json('ruc_validation_response')->nullable();

            // ── Workflow ──────────────────────────────────────────
            $table->enum('status', [
                'pending',
                'under_review',
                'requires_documents',
                'requires_review',
                'approved',
                'rejected',
                'cancelled',
            ])->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()
                  ->comment('FK a system.users (SuperAdmin que revisó)');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // ── Vínculo con tenant creado (nullable hasta aprobación) ─
            $table->unsignedBigInteger('tenant_id')->nullable()->index()
                  ->comment('FK a system.clients cuando se materializa');

            // ── Token para portal público de seguimiento ─────────
            $table->string('tracking_token', 64)->nullable()->unique()
                  ->comment('Hash opaco usado en URL firmada /seller/application/{token}');

            // ── Telemetría básica ─────────────────────────────────
            $table->ipAddress('source_ip')->nullable();
            $table->string('source_ua', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_applications');
    }
}
