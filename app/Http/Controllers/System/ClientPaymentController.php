<?php
namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ClientPaymentRequest;
use App\Http\Resources\System\ClientPaymentCollection;
use App\Models\System\CardBrand;
use App\Models\System\Client;
use App\Models\System\ClientPayment;
use App\Models\System\PaymentMethodType;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;

class ClientPaymentController extends Controller
{
    public function records($client_id)
    {
        $records = ClientPayment::where('client_id', $client_id)->get();

        return new ClientPaymentCollection($records);
    }

    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::all(),
            'card_brands' => CardBrand::all(),
        ];
    }

    public function client($client_id)
    {
        $client = Client::findOrFail($client_id);

        $total_paid = collect($client->payments)->where('state', true)->sum('payment');
        $total = collect($client->payments)->sum('payment');
        $total_difference = round($total - $total_paid, 2);

        return [
            'name' => $client->name,
            'pricing' => $client->plan->pricing,
            'total_paid' => $total_paid,
            'total' => $total,
            'total_difference' => $total_difference,
        ];
    }

    public function store(ClientPaymentRequest $request)
    {
        $id = $request->input('id');
        $record = ClientPayment::firstOrNew(['id' => $id]);
        $isNewRecord = !$record->exists;
        $record->fill($request->all());
        $record->save();

        $client = Client::findOrFail($request->client_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);

        $tenantPayments = DB::connection('tenant')->table('account_payments');
        $existingTenantPayment = $tenantPayments->where('reference_id', $record->id)->first();

        $payload = [
            'date_of_payment' => $record->date_of_payment,
            'payment_method_type_id' => $record->payment_method_type_id,
            'card_brand_id' => $record->card_brand_id,
            'reference' => $record->reference,
            'payment' => $record->payment,
        ];

        if ($existingTenantPayment) {
            $tenantPayments->where('reference_id', $record->id)->update($payload);
        } else {
            $tenantPayments->insert(array_merge($payload, [
                'reference_id' => $record->id,
                'state' => (int) ((bool) $record->state),
                'created_at' => now(),
            ]));
        }

        return [
            'success' => true,
            'message' => ($isNewRecord) ? 'Pago programado con exito' : 'Pago editado con exito',
        ];
    }

    public function destroy($id)
    {
        $item = ClientPayment::findOrFail($id);
        $client = Client::findOrFail($item->client_id);

        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);
        DB::connection('tenant')->table('account_payments')->where('reference_id', $item->id)->delete();

        $item->delete();

        return [
            'success' => true,
            'message' => 'Pago eliminado con exito',
        ];
    }

    public function cancel_payment($client_payment_id)
    {
        $client_payment = ClientPayment::findOrFail($client_payment_id);

        if ((bool) $client_payment->state === true) {
            return [
                'success' => false,
                'message' => 'El pago ya se encontraba marcado como pagado',
            ];
        }

        $client_payment->state = true;
        $client_payment->save();

        $client = Client::findOrFail($client_payment->client_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);
        DB::connection('tenant')->table('account_payments')->where('reference_id', $client_payment->id)->update([
            'state' => 1,
            'date_of_payment_real' => date('Y-m-d'),
        ]);

        return [
            'success' => true,
            'message' => 'Monto pagado',
        ];
    }
}
