<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `marketplace_listings.search_text` de 500 a 1000 caracteres.
 *
 * El índice del buscador lleva título, marca, categorías, variantes y nombre,
 * y desde 2026-11-18 también las palabras útiles de la descripción (mp_notes):
 * «arbol pino verde» no encontraba un «Pino Americano» porque «verde» solo
 * estaba en la descripción. Con 500 caracteres no cabía; lo decisivo sigue
 * yendo primero (MarketplaceListingSyncService::buildSearchText) y la
 * descripción rellena lo que sobra.
 *
 * El índice B-tree sobre la columna entera no cabe con 1000 caracteres
 * utf8mb4 (4000 bytes > 3072 de InnoDB), y el buscador no lo usa: compara con
 * REGEXP y LIKE '%x%'. Se rehace con prefijo de 191, que conserva lo que
 * pudiera dar (igualdades y prefijos) sin el límite.
 *
 * Idempotente: si la columna ya mide 1000 o más, no hace nada. El código
 * aguanta que esta migración aún no se haya corrido (lee el largo real), pero
 * hasta que se corra el índice sigue cortado en 500.
 *
 * Después: `php artisan ebaemy-marketplace:sync` para reconstruir el índice.
 */
return new class extends Migration {
    private const TABLA = 'marketplace_listings';

    public function up(): void
    {
        if (!Schema::hasColumn(self::TABLA, 'search_text') || $this->length() >= 1000) {
            return;
        }

        if ($this->hasIndex('mpl_search_text_idx')) {
            DB::statement('ALTER TABLE ' . self::TABLA . ' DROP INDEX mpl_search_text_idx');
        }

        DB::statement('ALTER TABLE ' . self::TABLA . " MODIFY search_text VARCHAR(1000)
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
            COMMENT 'Texto normalizado (sin acentos) para búsqueda insensible a tildes'");

        DB::statement('ALTER TABLE ' . self::TABLA . ' ADD INDEX mpl_search_text_idx (search_text(191))');
    }

    public function down(): void
    {
        if (!Schema::hasColumn(self::TABLA, 'search_text') || $this->length() <= 500) {
            return;
        }

        if ($this->hasIndex('mpl_search_text_idx')) {
            DB::statement('ALTER TABLE ' . self::TABLA . ' DROP INDEX mpl_search_text_idx');
        }

        // Lo que pase de 500 se pierde; el próximo sync con el código viejo
        // lo vuelve a cortar igual.
        DB::statement('UPDATE ' . self::TABLA . ' SET search_text = LEFT(search_text, 500) WHERE CHAR_LENGTH(search_text) > 500');
        DB::statement('ALTER TABLE ' . self::TABLA . " MODIFY search_text VARCHAR(500)
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
            COMMENT 'Texto normalizado (sin acentos) para búsqueda insensible a tildes'");
        DB::statement('ALTER TABLE ' . self::TABLA . ' ADD INDEX mpl_search_text_idx (search_text)');
    }

    private function length(): int
    {
        $fila = DB::selectOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS l FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'search_text'",
            [self::TABLA]
        );

        return (int) ($fila->l ?? 0);
    }

    private function hasIndex(string $nombre): bool
    {
        return !empty(DB::select('SHOW INDEX FROM ' . self::TABLA . ' WHERE Key_name = ?', [$nombre]));
    }
};
