<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\ShippingRequest;
use Illuminate\Support\Facades\Log;
use Modules\Dispatch\Models\DispatchAddress;

/**
 * Precarga de la Guía de Remisión a partir de un Registro de Envío.
 *
 * El registro de envíos guarda lo que el CLIENTE escribió (texto libre), y la
 * guía necesita entidades del sistema: un `persons.id`, un `dispatchers.id`,
 * ítems con `item_id` y unidad de medida. Este servicio hace ese puente y —
 * esto es lo importante — informa qué pudo resolver y qué no, en vez de
 * inventar datos para que el formulario "salga lleno".
 */
class ShipmentDispatchPrefill
{
    /** Avisos para el operador: lo que hay que revisar antes de emitir. */
    private array $avisos = [];

    public function avisos(): array
    {
        return $this->avisos;
    }

    // ── Cliente ────────────────────────────────────────────────────────────

    /**
     * Persona del ERP para el envío. Se busca por documento; si no existe se
     * crea, porque el envío puede venir del formulario público de alguien que
     * nunca compró antes.
     */
    public function persona(ShippingRequest $s): ?Person
    {
        $doc = preg_replace('/\D+/', '', (string) $s->dni);

        // Sin documento no hay a quién emitirle: SUNAT lo exige.
        if ($doc === '') {
            $this->avisos[] = 'El envío no tiene DNI/RUC del cliente. La guía necesita '
                            . 'un documento válido: complétalo en el envío o elige el cliente a mano.';
            return null;
        }

        // La resolucion vive en Person::resolveCustomer(): es la misma que usan
        // el espejo del encargo logistico y el alta manual de pedidos. Aqui
        // solo se aportan los datos del envio y se traduce el fallo a un aviso
        // para el operador, que es lo propio de esta pantalla.
        $persona = Person::resolveCustomer($doc, $s->full_name, [
            'district_id'   => $s->district_id,
            'province_id'   => $s->province_id,
            'department_id' => $s->department_id,
            'address'       => $s->shipping_destination ?: $s->formatted_address,
            'telephone'     => $s->phone,
        ]);

        if (!$persona) {
            $this->avisos[] = 'No se pudo crear el cliente automáticamente. Selecciónalo a mano.';
        }

        return $persona;
    }

    // ── Dirección de entrega ───────────────────────────────────────────────

    /**
     * Punto de llegada. Se reutiliza la dirección si ya existe para esa persona
     * para no llenar la tabla de duplicados en cada envío.
     */
    public function direccionEntrega(ShippingRequest $s, ?Person $persona): ?int
    {
        if (!$persona) {
            return null;
        }

        // En agencia el paquete llega a la OFICINA, no al domicilio: esa es la
        // dirección que corresponde declarar como punto de llegada.
        $texto = trim((string) ($s->shipping_destination ?: $s->reference ?: $s->formatted_address));
        if ($texto === '' && $s->destination_city) {
            $texto = 'Agencia en ' . $s->destination_city;
        }
        if ($texto === '') {
            $this->avisos[] = 'El envío no tiene dirección de destino. Complétala antes de emitir.';
            return null;
        }

        $texto = mb_substr($texto, 0, 100);   // límite de SUNAT

        try {
            $existente = DispatchAddress::where('person_id', $persona->id)
                                        ->where('address', $texto)->first();
            if ($existente) {
                return $existente->id;
            }

            return DispatchAddress::create([
                'person_id'   => $persona->id,
                'address'     => $texto,
                'is_active'   => true,
                'location_id' => $this->ubigeo($s),
            ])->id;
        } catch (\Throwable $e) {
            Log::warning('[Shipments] Dirección de guía: ' . $e->getMessage());
            return null;
        }
    }

    /** Ubigeo en el formato [departamento, provincia, distrito] que espera la guía. */
    private function ubigeo(ShippingRequest $s): array
    {
        $dist = (string) $s->district_id;

        if (strlen($dist) >= 6) {
            return [substr($dist, 0, 2), substr($dist, 0, 4), $dist];
        }

        // Sin ubigeo no se puede adivinar el destino: se avisa y se deja Lima
        // centro solo para que la fila no viole el NOT NULL. El operador lo
        // corrige en el formulario, que es donde se ve.
        $this->avisos[] = 'El envío no tiene distrito de destino: revisa el ubigeo antes de emitir.';

        return ['15', '1501', '150101'];
    }

    // ── Transportista ──────────────────────────────────────────────────────

    /**
     * Empareja la agencia escrita a mano ("Shalom", "Flores") con un
     * transportista registrado ("SHALOM EMPRESARIAL S.A.C.", "EMP. DE TRANS.
     * FLORES HNOS. SRL.").
     *
     * Se compara por palabras significativas y no por igualdad: nadie escribe
     * la razón social completa en el formulario público.
     */
    public function transportista(ShippingRequest $s): ?int
    {
        $agencia = trim((string) $s->shipping_agency);

        if ($agencia === '') {
            $this->avisos[] = 'El registro no tiene una agencia de transporte configurada. '
                            . 'Selecciona una agencia antes de generar la Guía de Remisión.';
            return null;
        }

        try {
            $registrados = \DB::connection('tenant')->table('dispatchers')
                              ->where('is_active', 1)->get(['id', 'name']);
        } catch (\Throwable $e) {
            return null;
        }

        $clave = $this->palabrasClave($agencia);

        if (!$clave) {
            $this->avisos[] = 'No se pudo interpretar la agencia "' . $agencia . '". Selecciónala a mano.';
            return null;
        }

        foreach ($registrados as $r) {
            $nombre = $this->normalizar($r->name);
            foreach ($clave as $palabra) {
                if (str_contains($nombre, $palabra)) {
                    return (int) $r->id;
                }
            }
        }

        $this->avisos[] = 'La agencia "' . $agencia . '" no está registrada como transportista. '
                        . 'Selecciónala en el formulario o créala en Transportistas.';

        return null;
    }

    private function normalizar(string $t): string
    {
        $t = mb_strtoupper(trim($t));
        $t = strtr($t, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
        return preg_replace('/[^A-Z0-9 ]+/', ' ', $t);
    }

    /**
     * Palabras con las que vale la pena buscar: se descartan las genéricas
     * ("TRANSPORTES", "EXPRESS", "SAC") porque casarían con media tabla.
     */
    private function palabrasClave(string $agencia): array
    {
        $vacias = ['TRANSPORTES', 'TRANSPORTE', 'TRANS', 'EMPRESA', 'EMP', 'DE', 'DEL', 'LA',
                   'EL', 'LOS', 'SAC', 'SA', 'SRL', 'EIRL', 'HNOS', 'COURIER', 'EXPRESS',
                   'EXPRESO', 'CARGO', 'SERVICIOS', 'GENERALES'];

        $palabras = array_filter(
            explode(' ', $this->normalizar($agencia)),
            fn ($p) => mb_strlen($p) >= 4 && !in_array($p, $vacias, true)
        );

        return array_values($palabras);
    }

    // ── Productos ──────────────────────────────────────────────────────────

    /**
     * Ítems para la guía a partir del detalle del paquete.
     *
     * El detalle es texto libre ("01 palmera de 180cm"), escrito por el cliente
     * o por el almacén, así que se parsea la cantidad del inicio y se intenta
     * casar la descripción con el catálogo para recuperar el `item_id` y la
     * unidad de medida. Lo que no casa igual viaja a la guía como descripción
     * libre: es preferible a perderlo, y el operador lo corrige a la vista.
     */
    public function items(ShippingRequest $s): array
    {
        $lineas = $s->contentLines();

        if (!count($lineas)) {
            $this->avisos[] = 'El envío no tiene detalle del producto: la guía necesita al menos un ítem.';
            return [];
        }

        $items = [];
        $sinCasar = 0;

        foreach ($lineas as $linea) {
            [$cantidad, $descripcion] = $this->partirCantidad($linea);

            $item = $this->buscarEnCatalogo($descripcion);
            if (!$item) {
                $sinCasar++;
            }

            $items[] = [
                'item_id'      => $item->id ?? null,
                'description'  => $item ? $item->description : $descripcion,
                'unit_type_id' => $item->unit_type_id ?? 'NIU',
                'quantity'     => $cantidad,
                'unit_price'   => 0,
                'total'        => 0,
                'item'         => $item ? [
                    'id'           => $item->id,
                    'description'  => $item->description,
                    'internal_id'  => $item->internal_id,
                    'unit_type_id' => $item->unit_type_id ?? 'NIU',
                ] : ['description' => $descripcion, 'unit_type_id' => 'NIU'],
                'IdLoteSelected' => '',
                'lots'           => [],
                'warehouse_id'   => null,
                // Marca para pintar en la previsualización cuál hay que revisar.
                'sin_catalogo'   => $item === null,
            ];
        }

        if ($sinCasar > 0) {
            $this->avisos[] = $sinCasar . ' de ' . count($lineas) . ' producto(s) del detalle no se '
                            . 'encontraron en el catálogo y van como descripción libre. Revísalos antes de emitir.';
        }

        return $items;
    }

    /**
     * Separa "02 maceta de cerámica" en [2, "maceta de cerámica"].
     * Es el formato que el almacén ya usa (ver el buscador del detalle).
     */
    private function partirCantidad(string $linea): array
    {
        if (preg_match('/^(\d{1,3})\s*[xX]?\s+(.+)$/u', trim($linea), $m)) {
            $n = (int) $m[1];
            return [$n >= 1 ? $n : 1, trim($m[2])];
        }

        return [1, trim($linea)];
    }

    /** Busca el producto por descripción exacta y, si no, por coincidencia. */
    private function buscarEnCatalogo(string $descripcion): ?Item
    {
        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            return null;
        }

        try {
            return Item::where('description', $descripcion)->first()
                ?? Item::where('description', 'like', '%' . $descripcion . '%')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
