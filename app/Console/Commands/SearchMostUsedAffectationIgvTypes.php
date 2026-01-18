<?php

namespace App\Console\Commands;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\SaleNoteItem;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

class SearchMostUsedAffectationIgvTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affectation:fill {--tenant=} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activa el tipo de afectación IGV más usado en los productos referente a boleta/factura y nota de venta';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $tenants = explode(",",$this->option('tenant'));

        if (count($tenants) == 1 && $tenants[0] === "") {
            $websites = Website::all();
            foreach ($websites as $website) {
                $tenancy = app(\Hyn\Tenancy\Environment::class);
                $tenancy->tenant($website);
                $this->getAffectationIgvTypeMostUsed();

            }
        } else {
            foreach ($tenants as $tenant_id) {
                $website = Website::where('id', $tenant_id)->first();
                if ($website) {
                    $tenancy = app(\Hyn\Tenancy\Environment::class);
                    $tenancy->tenant($website);

                    $this->getAffectationIgvTypeMostUsed();
                }
            }

        }

    }

    private function getAffectationIgvTypeMostUsed()
    {

        $document_affectation_types_id = DocumentItem::query()
            ->whereNotNull('affectation_igv_type_id')
            ->distinct()
            ->pluck('affectation_igv_type_id')
            ->toArray();
    
        $sale_note_affectation_types_id = SaleNoteItem::query()
            ->whereNotNull('affectation_igv_type_id')
            ->distinct()
            ->pluck('affectation_igv_type_id')
            ->toArray();
        

        $array_ids = collect($document_affectation_types_id)
            ->merge($sale_note_affectation_types_id)
            ->unique()
            ->values()->all();
        
        
        AffectationIgvType::whereNotIn('id', $array_ids)
            ->update(['active' => false]);

        
    }

}
