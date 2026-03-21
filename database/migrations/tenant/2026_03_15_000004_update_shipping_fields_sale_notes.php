<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modelo completo de costos de envío:
 *
 *  NV (vendedor):
 *    shipping_cost_customer  → lo que cobra la empresa al cliente (renombrado desde shipping_cost)
 *
 *  Almacén (al despachar):
 *    shipping_packages       → bultos reales
 *    shipping_cost_agency    → costo que paga a la agencia (Olva, Shalom…)
 *    shipping_carrier_type   → quién lleva a la agencia: propio | tercero
 *    shipping_carrier_cost   → costo del motorizado/tercero (si aplica)
 *    shipping_paid_by        → quién pagó a la agencia: empresa | tercero | cliente
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renombrar con SQL directo (evita problemas Doctrine DBAL)
        DB::statement("ALTER TABLE sale_notes CHANGE shipping_cost shipping_cost_customer DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Lo que cobra la empresa al cliente por envío (no facturado)'");

        // Eliminar campo que ya no se usa
        DB::statement("ALTER TABLE sale_notes DROP COLUMN shipping_price_package");

        // Agregar campos del almacén
        DB::statement("ALTER TABLE sale_notes
            ADD COLUMN shipping_cost_agency   DECIMAL(10,2)  NOT NULL DEFAULT 0       COMMENT 'Costo que pagó a la agencia courier (Olva, Shalom…)' AFTER shipping_cost_customer,
            ADD COLUMN shipping_carrier_type  VARCHAR(20)    NULL                     COMMENT 'propio | tercero'                                    AFTER shipping_cost_agency,
            ADD COLUMN shipping_carrier_cost  DECIMAL(10,2)  NOT NULL DEFAULT 0       COMMENT 'Costo del motorizado/tercero que llevó a la agencia' AFTER shipping_carrier_type,
            ADD COLUMN shipping_paid_by       VARCHAR(20)    NULL                     COMMENT 'empresa | tercero | cliente — quién pagó a la agencia' AFTER shipping_carrier_cost
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sale_notes CHANGE shipping_cost_customer shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE sale_notes ADD COLUMN shipping_price_package DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER shipping_packages");
        DB::statement("ALTER TABLE sale_notes DROP COLUMN shipping_cost_agency, DROP COLUMN shipping_carrier_type, DROP COLUMN shipping_carrier_cost, DROP COLUMN shipping_paid_by");
    }
};
