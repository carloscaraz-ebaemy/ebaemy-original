<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia las banderas que se colaron en el estado del contribuyente.
 *
 * `persons.state` y `persons.condition` guardan lo que devuelve SUNAT en
 * TEXTO: 'ACTIVO', 'BAJA DEFINITIVA', 'HABIDO', 'NO HABIDO'. Dos sitios las
 * trataban como un booleano de registro activo y escribieron '1' y '01':
 *
 *   - Person::resolveCustomer() ponia 'state' => true  (alta de cliente desde
 *     la guia de remision, el encargo logistico y el alta manual de pedidos).
 *   - El registro con Google del escaparate ponia '01' en las dos columnas.
 *
 * El efecto no era solo cosmetico. La ficha pinta en rojo «1» como
 * contribuyente no activo, y PersonController::store rechaza guardar
 * cualquier persona cuyo `state` no sea exactamente 'ACTIVO', asi que esos
 * clientes quedaban imposibles de editar: «El estado del contribuyente no es
 * activo, no puede registrarlo».
 *
 * Solo se tocan los valores basura exactos. Un 'BAJA DEFINITIVA' o un
 * 'NO HABIDO' de verdad se respetan: esos SI vienen de SUNAT.
 */
return new class extends Migration
{
    /**
     * Exactamente los dos valores que escribieron los bugs. No se amplia la
     * lista a '0' o '00' por si acaso: son codigos que algun proveedor si usa
     * para ACTIVO, y no aparecen en ningun tenant.
     */
    private const BASURA = ['1', '01'];

    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('persons')) {
            return;
        }

        foreach (['state', 'condition'] as $columna) {
            if (!Schema::connection('tenant')->hasColumn('persons', $columna)) {
                continue;
            }

            DB::connection('tenant')->table('persons')
                ->whereIn($columna, self::BASURA)
                ->update([$columna => '']);
        }
    }

    public function down(): void
    {
        // No hay vuelta atras: el valor anterior era basura y no se puede
        // distinguir de un vacio legitimo. Dejarlo vacio es el estado correcto.
    }
};
