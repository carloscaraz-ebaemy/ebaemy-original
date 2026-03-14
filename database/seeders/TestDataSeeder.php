<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Document;
use App\Models\Tenant\QuotationItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNotePayment;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Company;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;

class TestDataSeeder extends Seeder
{
    private $establishment_id = 1;
    private $est_json;
    private $soap_type;
    private $items_data;
    private $customers;

    public function run()
    {
        $company = Company::active();
        $this->soap_type = $company->soap_type_id;
        $this->est_json  = EstablishmentInput::set($this->establishment_id);

        $this->customers = [
            ['id' => 2,  'name' => 'Juan Carlos Pérez García'],
            ['id' => 5,  'name' => 'Ana Lucía Flores Mendoza'],
            ['id' => 7,  'name' => 'Empresa Tecnológica SAC'],
            ['id' => 8,  'name' => 'Comercial Norte EIRL'],
            ['id' => 3,  'name' => 'María Elena Rodríguez López'],
        ];

        $this->items_data = [
            ['id'=>28,'name'=>'Laptop HP 15 pulgadas',       'price'=>2800.00],
            ['id'=>29,'name'=>'Mouse Inalámbrico Logitech',   'price'=>45.00],
            ['id'=>30,'name'=>'Teclado Mecánico RGB',          'price'=>180.00],
            ['id'=>31,'name'=>'Monitor Samsung 24"',           'price'=>750.00],
            ['id'=>32,'name'=>'Auriculares Sony MDR',          'price'=>320.00],
            ['id'=>33,'name'=>'Silla Ergonómica Ejecutiva',    'price'=>650.00],
            ['id'=>34,'name'=>'Impresora Epson L3150',         'price'=>890.00],
            ['id'=>35,'name'=>'Webcam Logitech C920',          'price'=>260.00],
            ['id'=>36,'name'=>'Disco Duro Externo 1TB',        'price'=>195.00],
            ['id'=>37,'name'=>'Tablet Samsung A8',             'price'=>980.00],
        ];

        $quotation_ids  = $this->createQuotations();
        $sale_note_ids  = $this->createSaleNotes($quotation_ids);
        $this->createBoletas($sale_note_ids);
        $this->createFacturas($sale_note_ids);

        $this->command->info('');
        $this->command->info('=== RESUMEN FINAL ===');
        $this->command->info('Cotizaciones:   ' . Quotation::count());
        $this->command->info('Notas de venta: ' . SaleNote::count());
        $this->command->info('Boletas:        ' . Document::where('document_type_id','03')->count());
        $this->command->info('Facturas:       ' . Document::where('document_type_id','01')->count());
    }

    private function calcItem($item_id, $name, $unit_price, $qty = 1): array
    {
        $item_model  = Item::find($item_id);
        $unit_value  = round($unit_price / 1.18, 6);
        $total_base  = round($unit_value * $qty, 2);
        $total_igv   = round($total_base * 0.18, 2);
        $total       = round($unit_price * $qty, 2);

        $item_json = [
            'id'                          => $item_id,
            'name'                        => $name,
            'description'                 => $item_model->description ?? '',
            'unit_type_id'                => $item_model->unit_type_id,
            'currency_type_id'            => 'PEN',
            'sale_unit_price'             => $unit_price,
            'purchase_unit_price'         => $item_model->purchase_unit_price ?? 0,
            'has_igv'                     => true,
            'internal_id'                 => $item_model->internal_id,
            'sale_affectation_igv_type_id'=> '10',
            'is_set'                      => false,
            'calculate_quantity'          => false,
            'presentation'                => null,
        ];

        return [
            'item_id'                 => $item_id,
            'item'                    => json_encode($item_json),
            'quantity'                => $qty,
            'unit_value'              => $unit_value,
            'affectation_igv_type_id' => '10',
            'total_base_igv'          => $total_base,
            'percentage_igv'          => 18,
            'total_igv'               => $total_igv,
            'system_isc_type_id'      => null,
            'total_base_isc'          => 0,
            'percentage_isc'          => 0,
            'total_isc'               => 0,
            'total_base_other_taxes'  => 0,
            'percentage_other_taxes'  => 0,
            'total_other_taxes'       => 0,
            'total_taxes'             => $total_igv,
            'price_type_id'           => '01',
            'unit_price'              => $unit_price,
            'total_value'             => $total_base,
            'total_charge'            => 0,
            'total_discount'          => 0,
            'total'                   => $total,
            'warehouse_id'            => 1,
        ];
    }

    private function calcTotals(array $rows): array
    {
        $total_taxed = 0; $total_igv = 0;
        foreach ($rows as $r) { $total_taxed += $r['total_value']; $total_igv += $r['total_igv']; }
        return ['total_taxed' => $total_taxed, 'total_igv' => $total_igv, 'total' => round($total_taxed + $total_igv, 2)];
    }

    private function nextNumber(string $class, string $series): int
    {
        $last = $class::where('series', $series)->orderBy('number','desc')->first();
        return $last ? $last->number + 1 : 1;
    }

    private function baseCommon(): array
    {
        return [
            'user_id'                => 1,
            'establishment_id'       => $this->establishment_id,
            'soap_type_id'           => $this->soap_type,
            'state_type_id'          => '01',
            'currency_type_id'       => 'PEN',
            'exchange_rate_sale'     => 1.000,
            'payment_method_type_id' => '01',
            'total_prepayment'       => 0, 'total_charge'      => 0,
            'total_discount'         => 0, 'total_exportation' => 0,
            'total_free'             => 0, 'total_unaffected'  => 0,
            'total_exonerated'       => 0, 'total_igv_free'    => 0,
            'total_base_isc'         => 0, 'total_isc'         => 0,
            'total_base_other_taxes' => 0, 'total_other_taxes' => 0,
            'charges'     => json_encode([]), 'discounts'  => json_encode([]),
            'prepayments' => json_encode([]), 'guides'     => json_encode([]),
            'related'     => json_encode([]), 'perception' => json_encode([]),
            'detraction'  => json_encode([]), 'legends'    => json_encode([]),
        ];
    }

    private function baseSaleNote(): array
    {
        return array_merge($this->baseCommon(), [
            'total_plastic_bag_taxes' => 0,
            'total_canceled'          => 0,
            'apply_concurrency'       => 0,
            'enabled_concurrency'     => 0,
            'point_system'            => 0,
            'created_from_pos'        => 0,
        ]);
    }

    private function baseDocument(): array
    {
        return array_merge($this->baseCommon(), [
            'total_plastic_bag_taxes' => 0,
            'total_canceled'          => 0,
            'total_pending_payment'   => 0,
            'apply_concurrency'       => 0,
            'enabled_concurrency'     => 0,
            'point_system'            => 0,
            'has_prepayment'          => 0,
            'has_xml'                 => 0,
            'has_pdf'                 => 0,
            'has_cdr'                 => 0,
            'send_server'             => 0,
            'itinerant'               => 0,
            'is_editable'             => 1,
            'retention'               => json_encode([]),
            'sale_notes_relateds'     => json_encode([]),
        ]);
    }

    // ─── COTIZACIONES ────────────────────────────────────────────────────────
    private function createQuotations(): array
    {
        $this->command->info('=== CREANDO COTIZACIONES ===');
        $ids = [];
        $scenarios = [
            [$this->customers[0], [[$this->items_data[0],2],[$this->items_data[1],1]]],
            [$this->customers[1], [[$this->items_data[2],1],[$this->items_data[3],1]]],
            [$this->customers[2], [[$this->items_data[4],3],[$this->items_data[6],1]]],
            [$this->customers[3], [[$this->items_data[7],2],[$this->items_data[8],1]]],
            [$this->customers[4], [[$this->items_data[5],1],[$this->items_data[9],2]]],
        ];

        foreach ($scenarios as $idx => [$cust, $pairs]) {
            $rows   = array_map(fn($p) => $this->calcItem($p[0]['id'], $p[0]['name'], $p[0]['price'], $p[1]), $pairs);
            $totals = $this->calcTotals($rows);

            $q = Quotation::create(array_merge($this->baseCommon(), [
                'external_id'     => Str::uuid()->toString(),
                'establishment'   => json_encode($this->est_json),
                'prefix'          => 'COT',
                'date_of_issue'   => date('Y-m-d', strtotime("-{$idx} days")),
                'time_of_issue'   => '10:00:00',
                'date_of_due'     => date('Y-m-d', strtotime('+30 days')),
                'customer_id'     => $cust['id'],
                'customer'        => json_encode(PersonInput::set($cust['id'])),
                'total_taxed'     => $totals['total_taxed'],
                'total_igv'       => $totals['total_igv'],
                'total_taxes'     => $totals['total_igv'],
                'total_value'     => $totals['total_taxed'],
                'subtotal'        => $totals['total'],
                'total'           => $totals['total'],
                'changed'         => 0,
                'description'     => 'Cotización de prueba #'.($idx+1),
            ]));

            foreach ($rows as $r) { QuotationItem::create(array_merge($r, ['quotation_id' => $q->id])); }
            $ids[] = $q->id;
            $this->command->info("  COT #{$q->id} | {$cust['name']} | S/ {$totals['total']}");
        }
        return $ids;
    }

    // ─── NOTAS DE VENTA ───────────────────────────────────────────────────────
    private function createSaleNotes(array $quotation_ids): array
    {
        $this->command->info('=== CREANDO NOTAS DE VENTA ===');
        $ids     = [];
        $series  = 'NV01';
        $scenarios = [
            [$this->customers[0], [[$this->items_data[0],1],[$this->items_data[1],2]], $quotation_ids[0]],
            [$this->customers[1], [[$this->items_data[2],2],[$this->items_data[3],1]], $quotation_ids[1]],
            [$this->customers[2], [[$this->items_data[4],1],[$this->items_data[6],2]], $quotation_ids[2]],
            [$this->customers[3], [[$this->items_data[7],1],[$this->items_data[8],3]], null],
            [$this->customers[4], [[$this->items_data[5],2],[$this->items_data[9],1]], null],
            [$this->customers[0], [[$this->items_data[1],5],[$this->items_data[2],1]], null],
            [$this->customers[2], [[$this->items_data[3],1],[$this->items_data[4],2]], null],
        ];

        foreach ($scenarios as $idx => [$cust, $pairs, $quot_id]) {
            $rows   = array_map(fn($p) => $this->calcItem($p[0]['id'], $p[0]['name'], $p[0]['price'], $p[1]), $pairs);
            $totals = $this->calcTotals($rows);
            $number = $this->nextNumber(SaleNote::class, $series);

            $snData = array_merge($this->baseSaleNote(), [
                'external_id'  => Str::uuid()->toString(),
                'establishment'=> json_encode($this->est_json),
                'prefix'       => 'NV',
                'series'       => $series,
                'number'       => $number,
                'date_of_issue'=> date('Y-m-d', strtotime("-{$idx} days")),
                'time_of_issue'=> '11:00:00',
                'customer_id'  => $cust['id'],
                'customer'     => json_encode(PersonInput::set($cust['id'])),
                'quotation_id' => $quot_id,
                'total_taxed'  => $totals['total_taxed'],
                'total_igv'    => $totals['total_igv'],
                'total_taxes'  => $totals['total_igv'],
                'total_value'  => $totals['total_taxed'],
                'subtotal'     => $totals['total'],
                'total'        => $totals['total'],
            ]);
            $sn = SaleNote::withoutEvents(fn() => SaleNote::create($snData));

            foreach ($rows as $r) { SaleNoteItem::withoutEvents(fn() => SaleNoteItem::create(array_merge($r, ['sale_note_id' => $sn->id]))); }
            SaleNotePayment::create(['sale_note_id' => $sn->id, 'date_of_payment' => date('Y-m-d'), 'payment_method_type_id' => '01', 'reference' => null, 'payment' => $totals['total']]);
            $ids[] = $sn->id;
            $this->command->info("  {$series}-{$number} | {$cust['name']} | S/ {$totals['total']}");
        }
        return $ids;
    }

    // ─── BOLETAS ─────────────────────────────────────────────────────────────
    private function createBoletas(array $sale_note_ids): void
    {
        $this->command->info('=== CREANDO BOLETAS ===');
        $series = 'B001';
        $scenarios = [
            [$this->customers[0], [[$this->items_data[0],1],[$this->items_data[1],1]], $sale_note_ids[0] ?? null],
            [$this->customers[1], [[$this->items_data[2],1],[$this->items_data[3],1]], $sale_note_ids[1] ?? null],
            [$this->customers[4], [[$this->items_data[5],1],[$this->items_data[9],1]], $sale_note_ids[4] ?? null],
            [$this->customers[0], [[$this->items_data[1],3],[$this->items_data[8],2]], $sale_note_ids[5] ?? null],
            [$this->customers[2], [[$this->items_data[3],1],[$this->items_data[4],1]], null],
        ];

        foreach ($scenarios as $idx => [$cust, $pairs, $sn_id]) {
            $rows   = array_map(fn($p) => $this->calcItem($p[0]['id'], $p[0]['name'], $p[0]['price'], $p[1]), $pairs);
            $totals = $this->calcTotals($rows);
            $number = $this->nextNumber(Document::class, $series);

            $docData = array_merge($this->baseDocument(), [
                'external_id'     => Str::uuid()->toString(),
                'establishment'   => json_encode($this->est_json),
                'document_type_id'=> '03',
                'series'          => $series,
                'number'          => $number,
                'ubl_version'     => '2.1',
                'group_id'        => '02',
                'date_of_issue'   => date('Y-m-d', strtotime("-{$idx} days")),
                'time_of_issue'   => '12:00:00',
                'customer_id'     => $cust['id'],
                'customer'        => json_encode(PersonInput::set($cust['id'])),
                'sale_note_id'    => $sn_id,
                'total_taxed'     => $totals['total_taxed'],
                'total_igv'       => $totals['total_igv'],
                'total_taxes'     => $totals['total_igv'],
                'total_value'     => $totals['total_taxed'],
                'subtotal'        => $totals['total'],
                'total'           => $totals['total'],
            ]);
            $doc = Document::withoutEvents(fn() => Document::create($docData));

            foreach ($rows as $r) { DocumentItem::withoutEvents(fn() => DocumentItem::create(array_merge($r, ['document_id' => $doc->id]))); }
            DocumentPayment::create(['document_id' => $doc->id, 'date_of_payment' => date('Y-m-d'), 'payment_method_type_id' => '01', 'reference' => null, 'payment' => $totals['total']]);
            \App\Models\Tenant\Invoice::create(['document_id' => $doc->id, 'operation_type_id' => '0101', 'date_of_due' => date('Y-m-d', strtotime('+30 days'))]);
            $this->command->info("  {$series}-{$number} | {$cust['name']} | S/ {$totals['total']}");
        }
    }

    // ─── FACTURAS ─────────────────────────────────────────────────────────────
    private function createFacturas(array $sale_note_ids): void
    {
        $this->command->info('=== CREANDO FACTURAS ===');
        $series = 'F001';
        $scenarios = [
            [$this->customers[2], [[$this->items_data[0],1],[$this->items_data[6],1]], $sale_note_ids[2] ?? null],
            [$this->customers[3], [[$this->items_data[7],2],[$this->items_data[8],1]], $sale_note_ids[3] ?? null],
            [$this->customers[2], [[$this->items_data[3],2],[$this->items_data[4],1]], null],
            [$this->customers[3], [[$this->items_data[6],1],[$this->items_data[5],1]], null],
            [$this->customers[3], [[$this->items_data[0],1],[$this->items_data[3],1]], null],
        ];

        foreach ($scenarios as $idx => [$cust, $pairs, $sn_id]) {
            $rows   = array_map(fn($p) => $this->calcItem($p[0]['id'], $p[0]['name'], $p[0]['price'], $p[1]), $pairs);
            $totals = $this->calcTotals($rows);
            $number = $this->nextNumber(Document::class, $series);

            $docData = array_merge($this->baseDocument(), [
                'external_id'     => Str::uuid()->toString(),
                'establishment'   => json_encode($this->est_json),
                'document_type_id'=> '01',
                'series'          => $series,
                'number'          => $number,
                'ubl_version'     => '2.1',
                'group_id'        => '01',
                'date_of_issue'   => date('Y-m-d', strtotime("-{$idx} days")),
                'time_of_issue'   => '14:00:00',
                'customer_id'     => $cust['id'],
                'customer'        => json_encode(PersonInput::set($cust['id'])),
                'sale_note_id'    => $sn_id,
                'total_taxed'     => $totals['total_taxed'],
                'total_igv'       => $totals['total_igv'],
                'total_taxes'     => $totals['total_igv'],
                'total_value'     => $totals['total_taxed'],
                'subtotal'        => $totals['total'],
                'total'           => $totals['total'],
            ]);
            $doc = Document::withoutEvents(fn() => Document::create($docData));

            foreach ($rows as $r) { DocumentItem::withoutEvents(fn() => DocumentItem::create(array_merge($r, ['document_id' => $doc->id]))); }
            DocumentPayment::create(['document_id' => $doc->id, 'date_of_payment' => date('Y-m-d'), 'payment_method_type_id' => '01', 'reference' => null, 'payment' => $totals['total']]);
            \App\Models\Tenant\Invoice::create(['document_id' => $doc->id, 'operation_type_id' => '0101', 'date_of_due' => date('Y-m-d', strtotime('+30 days'))]);
            $this->command->info("  {$series}-{$number} | {$cust['name']} | S/ {$totals['total']}");
        }
    }
}
