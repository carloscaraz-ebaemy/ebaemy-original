<?php

namespace Modules\Report\Http\Controllers\Api;

use App\CoreFacturalo\Helpers\Template\TemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Report\Traits\ReportTrait;

class ReportController extends Controller
{

    use ReportTrait;

    private function getDateRange() {
        return 
        [
            now()->firstOfMonth()->format('Y-m-d'),
            now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    private function findModel(string $arg, $model, $param)
    {
        $arg = str_replace('_', ' ', $arg);
        return $model::where($param, 'like', "%{$arg}%")
            ->first();
    }

    public function quotations(Request  $request)
    {

        $response = $this->parserRequest($request);
        if (!$response['success']) {
            return $response;
        }
        Log::info($request->all());
        $records = $this->getRecords($request->all(), \App\Models\Tenant\Quotation::class); 

        return $this->getCollectionQuotations($records->get());
    }

    public function generalItems(Request $request)
    {

        if (!$request->input('type')) {
            $request->merge(['type' =>  'sale']);
        }

        $response = $this->parserRequest($request);

        if (!$response['success']) {
            return $response;
        }

        $controller = new \Modules\Report\Http\Controllers\ReportGeneralItemController();
        $records = $controller->getRecordsItems($request->all())->latest('id')->get();

        return $this->getCollectionGeneralItems($records, $request);
    }


    public function documents(Request $request)
    {

        $response = $this->parserRequest($request);
        if (!$response['success']) {
            return $response;
        }

        $records = $this->getRecords($request->all(), Document::class);
        return $this->getCollectionDocuments($records->get());
    }


    private function getCollectionGeneralItems($records, Request $request)
    {
        return $records->transform(function($row) use($request) {
            if ($request->input('type') === 'sale') {
                $data = \Modules\Report\Http\Resources\GeneralItemCollection::getDocument($row);
                if ($request->input('document_type_id') == '80') {
                    $observation = $data['observation']?$data['observation']:'';
                } else {
                    $observation = $data['additional_information']?$data['additional_information'][0]:'';
                }
                $document = $row->document;
                $item = $row->relation_item;
                $item_web_platform = $item->getWebPlatformModel();
                if ($item_web_platform) {
                    $web_platform = $item_web_platform->name;
                } 
                $total_item_purchase = \Modules\Report\Http\Resources\GeneralItemCollection::getPurchaseUnitPrice($row);
                $warehouse_description = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($row, $document);
                $isSaleNote = ($request->input('document_type_id') != '80' && $request->input('type') == 'sale') ? true : false;
                return [
                    'date_of_issue' => $document->date_of_issue->format('Y-m-d'),
                    'user' => $document->seller_id ? $document->user->name : $document->seller->name,
                    'district' => $document->establishment->district->description,
                    'province' => $document->establishment->province->description,
                    'department' => $document->establishment->department->description,
                    'type_document' => $isSaleNote ?  $document->document_type->description : 'SALON DE VENTA',
                    'serie' => $document->series,
                    'number' => $document->number,
                    'purchase_order' => $document->purchase_order,
                    'web_platform' => $item_web_platform ? $web_platform : '',
                    'annulled' => $document->state_type_id == '11' ? true : false,
                    'identity_document_customer' => $document->customer->identity_document_type->description,
                    'number_document_customer' => $document->customer->number,
                    'customer_name' => $document->customer->name,
                    'seller' => $isSaleNote ? ($document->seller_id ? $document->user->name : $document->seller->name) : $document->user->name,
                    'observation' => $observation,  
                    'currency' => $document->currency_type_id,
                    'unit_type'=> $item->unit_type_id,
                    'internal_id'=> $item->internal_id,
                    'description'=> $item->description,
                    'quantity'=> $row->quantity,
                    'payments_method' => '',
                    'unit_value' => $item->unit_value,
                    'unit_price' => $row->unit_price,
                    'discount' => $item->total_discount,
                    'total_value' => $item->total_value,
                    'affectation_igv_type' => $row->affectation_igv_type_id,
                    'igv' => $item->total_igv,
                    'system_isc_type_id' => $row->system_isc_type_id,
                    'total_isc' => $row->total_isc,
                    'total_plastic_bag_taxes' => $row->total_plastic_bag_taxes,
                    'total' => $row->total,
                    'purchase_total' => $total_item_purchase,
                    'exchange_rate_sale' => $document->exchange_rate_sale,
                    'warehouse_description' => $warehouse_description
                ];
            } else if ($request->input('type') === 'purchase') {
                $purchase = $row->purchase;
                $warehouse_description = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($row, $purchase);
                Log::info('purchase', ['purchase' => $row]);
                return [
                    'date_of_issue' => $purchase->date_of_issue->format('Y-m-d'),
                    'document_type' => $purchase->document_type->description,
                    'serie' => $purchase->series,
                    'number' => $purchase->number,
                    'cancelled' => $purchase->state_type_id == '11' ? true : false,
                    'identity_document_supplier' => $purchase->supplier->identity_document_type->description,
                    'number_document_supplier' => $purchase->supplier->number,
                    'supplier_name' => $purchase->supplier->name,
                    'currency' => $purchase->currency_type_id,
                    'unit_type'=> $row->item->unit_type_id,
                    'internal_id'=> $row->relation_item ? $row->relation_item->internal_id:'',
                    'description' => $row->item->description,
                    'quantity'=> $row->quantity,
                    'unit_value' => $row->getConvertUnitValueToPen(),
                    'unit_price' => $row->getConvertUnitPriceToPen(),
                    'discount' => collect($row->discounts)->sum('amount'),
                    'total_value' => $row->getConvertTotalValueToPen(),
                    'affectation_igv_type' => $row->affectation_igv_type_id,
                    'total_igv' => $row->getConvertTotalIgvToPen(),
                    'system_isc_type_id' => optional($row->system_isc_type_id)->description,
                    'total_isc' => $row->getConvertTotalIscToPen(),
                    'total_plastic_bag_taxes' => $row->total_plastic_bag_taxes,
                    'total' => $row->getConvertTotalToPen(),
                    'exchange_rate_sale' => $purchase->exchange_rate_sale,
                    'warehouse_description' => $warehouse_description
                ];
            }
        });
    }

    private function getCollectionQuotations($records)
    {
        return $records->transform(function($row) {
            return [
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'user_name' => $row->user->name,
                'customer_name' => $row->customer->name,
                'state' => $row->state_type->description,
                'number_full' => $row->number_full,
                'documents' => $row->documents->transform(function($doc) {
                    return [
                        'document_type_description' => $doc->document_type->description,
                        'number_full' => $doc->number_full,
                    ];
                }),
                'sale_notes' => $row->sale_notes->transform(function($row) {
                    return [
                    'number_full' => $row->number_full,
                ];
                }),
                'case' =>'',
                'currency' => $row->currency_type_id,
                'total_exportation' => floatval($row->total_exportation),
                'total_unaffected' => floatval($row->total_unaffected),
                'total_exonerated' => floatval($row->total_exonerated),
                'total_taxed' => floatval($row->total_taxed),
                'total_igv' => floatval($row->total_igv),
                'total' => floatval($row->total)
            ];
        });
    }

    public function getCollectionDocuments($records)
    {
        return $records->transform(function($row) {
            $document_type = $row->getDocumentType();
            $seller = \App\CoreFacturalo\Helpers\Template\ReportHelper::getSellerData($row);
            $customer = \App\Models\Tenant\Person::find($row->customer_id);
            $isNote = in_array($document_type->id,["07","08"]) && $row->note;
            $establishment = \App\CoreFacturalo\Helpers\Template\ReportHelper::getLocationData($row);
            $serie_affec = '';
            $payments = collect([]);
            if ($isNote) {
                $serie = ($row->note->affected_document) ? $row->note->affected_document->series : $row->note->data_affected_document->series;
                $number =  ($row->note->affected_document) ? $row->note->affected_document->number : $row->note->data_affected_document->number;
                $serie_affec = $serie.' - '.$number;
            }
            if(
                get_class($row) == Document::class ||
                get_class($row) == SaleNote::class
            ){
                $payments = TemplateHelper::getDetailedPayment($row);
            }
            $totals = $this->totalesDocuments($row, $document_type->id);

            return [
                'user' => $seller ? $seller->name : '',
                'document_type_id' =>  $document_type->id ,
                'number' => "{$row->series}-{$row->number}",
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'date_of_due' => isset($row->invoice)?$row->invoice->date_of_due->format('Y-m-d'):'',
                'document_affected' => $serie_affec,
                'guides' => $row->guides ? $row->guides->transform(function($guide) {
                    return $guide->number;
                }) : [],
                'quotations' => ($row->quotation) ? $row->quotation->number_full : '',
                'district' => $establishment ? $establishment['district'] : '',
                'province' => $establishment ? $establishment['province'] : '',
                'department' => $establishment ? $establishment['department'] : '',
                'address_customer' => $row->customer->address,
                'document_type_customer' => optional($customer->person_type)->description,
                'name_customer' => $customer->name,
                'number_customer' => $customer->number,
                'status' => $row->state_type->description,
                'currency_type_id' => $row->currency_type_id,
                'sale_note' => $row->sale_note ? $row->sale_note->number_full : '',
                'date_of_issue_sale_note' => $row->sale_note ? $row->sale_note->date_of_issue->format('Y-m-d') : '',
                'payments' =>  $payments,
                'exchange_rate_sale' => $row->exchange_rate_sale,
                'total_charge' => $totals['total_charge'],
                'total_exonerated' => $totals['total_exonerated'],
                'total_unaffected' => $totals['total_unaffected'],
                'total_taxed' => $totals['total_taxed'],
                'total_discount' => $totals['total_discount'],
                'total_free' => $totals['total_free'],
                'total_igv' => $totals['total_igv'],
                'total_isc' => $totals['total_isc'],
                'total' => $totals['total'],
            ];
        });
    }

    private function totalesDocuments($record, $document_type) 
    {
        $totals = [
            'total_charge' => 0,
            'total_exonerated' => 0,
            'total_unaffected' => 0,
            'total_taxed' => 0,
            'total_discount' => 0,
            'total_free' => 0,
            'total_igv' => 0,
            'total_isc' => 0,
            'total' => 0
        ];

        if ($document_type == '07') {
            if (!in_array($record->state_type_id,['09','11'])) {
                foreach ($totals as $key => $value) {
                    $totals[$key] =  floatval("-{$record->$key}") == -0 ? 0 : floatval("-{$record->$key}");
                }
            }
        } else {
            foreach ($totals as $key => $value) {
                $totals[$key] =  (in_array($document_type,['01','03']) && in_array($record->state_type_id,['09','11'])) ? 0  : floatval($record->$key);
            }
        }
        return $totals;
    }

    function isNegativeZero(float $num): bool {
        return $num === 0.0 && is_infinite(1 / $num) && (1 / $num) < 0;
    }

    private function parserRequest(Request &$request)
    {
        $user = auth()->user();
        [$range_start, $range_end] = $this->getDateRange();

        $response = [
            'success' => true,
            'message' => '',    
        ];

        if ($request->input('item_name')) {
            $item = $this->findModel($request->input('item_name'), Item::class, 'description');
            if (!$item)
            {
                $response['success'] = false;
                $response['message'] = "No se encontro el item ".$request->input('item_name');
                return $response;

            }


            $request->merge(['item_id' => $item->id ?? null]);
        }

        if ($request->input('user_name')) {
            $user = $this->findModel($request->input('user_name'), User::class, 'name');

            if (!$user) {
                $response['success'] = false;
                $response['message'] = "No se encontro el usuario ".$request->input('user_name');
                return $response;
            }

            $request->merge(['user_id' => $user->id ?? null]);
        } else {
            $request->merge(['user_id' => $user->id]);
        }

        if ($request->input('person_name')) {
            $person = $this->findModel($request->input('person_name'), Person::class, 'name');
            Log::info('person found', ['person' => $person]);

            if (!$person) {
                $response['success'] = false;
                $response['message'] = "No se encontro la persona ".$request->input('person_name');
                return $response;
            }

            $request->merge(['person_id' => $person->id]);
            $request->merge(['type_person' => 'customers']);
        }

        if ($request->input('establishment_name')) {
            $establishment = $this->findModel($request->input('establishment_name'), Establishment::class, 'description');

            if (!$establishment) {
                $response['success'] = false;
                $response['message'] = "No se encontro el establecimiento ".$request->input('establishment_name');
                return $response;
            }
            $request->merge(['establishment_id' => $establishment->id ]);
        } else {
            $request->merge(['establishment_id' => null ]);
        }

        if ($request->input('date_start') == null) {
            $request->merge(['date_start' => $range_start]);
        }

        if ($request->input('date_end') == null) {
            $request->merge(['date_end' => $range_end]);
        }

        $request->merge(['period' => 'between_dates']);

        return $response;

    }

}   