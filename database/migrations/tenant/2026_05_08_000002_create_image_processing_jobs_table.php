<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de procesamiento de imágenes (F2 image-pipeline).
 *
 * Cuando el seller sube una foto pesada o HEIC, el endpoint
 * /items/upload-async crea una fila aquí, dispara un job y devuelve
 * inmediatamente al frontend con el UUID. El frontend hace polling a
 * /items/upload-jobs/{uuid} hasta que status=completed o failed.
 *
 * Permite:
 *  - Liberar al PHP-FPM worker rápido (no esperar 5-15s por upload).
 *  - Mostrar progreso visual ("Subiendo / Procesando / Listo").
 *  - Reintento automático ante fallas transitorias (Imagick ooM, etc.).
 *  - Auditar fallas: la cola misma es la fuente de verdad.
 *
 * El registro NO está atado a un item_id — la foto puede subirse antes
 * de crear el producto. El frontend recibe el filename procesado y lo
 * asigna al payload de items.store.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('image_processing_jobs')) return;

        Schema::create('image_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();        // expuesto al frontend
            $table->unsignedInteger('user_id')->nullable();
            $table->string('original_path', 500)->nullable();   // tmp absoluto
            $table->string('original_name', 500)->nullable();   // nombre original
            $table->string('base_name', 255)->nullable();       // sanitizado

            $table->string('filename', 500)->nullable();        // main final
            $table->string('filename_medium', 500)->nullable();
            $table->string('filename_small', 500)->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_processing_jobs');
    }
};
