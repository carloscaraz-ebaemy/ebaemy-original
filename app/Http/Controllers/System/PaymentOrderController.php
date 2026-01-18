<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Resources\System\PaymentOrderCollection;
use App\Models\System\Client;
use App\Models\System\Configuration;
use App\Models\System\PaymentOrder;
use App\Models\System\PaymentOrderState;
use App\Models\System\Plan;
use App\Models\System\PlanPeriod;
use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentOrderController extends Controller
{
    public function index()
    {
        return view('system.payments.index');
    }

    public function tables()
    {
        $clients = Client::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
            ];
        });
        $status = PaymentOrderState::all();
        $active_cron = Configuration::first()->active_cron;
        $clientPlans = Client::all()->transform(function($row) {
            $payments = PaymentOrder::paymentsTotals($row->id)->get();
            $payments = PaymentOrderState::all()->transform(function($row) use ($payments) {
                $total = $payments->where('id', $row->id)->first();
                return (object) [
                    'id' => $row->id,
                    'name' => strtoupper($row->name),
                    'total' => $total ? $total->total : 0,
                ];
            });
            $ending = Carbon::now()->diffAsCarbonInterval(Carbon::parse($row->ending_billing_cycle));           
            $result = [];
            if ($ending->y) $result[] = $ending->y . ' años';
            if ($ending->m) $result[] = $ending->m . ' meses';
            if ($ending->d) $result[] = $ending->d . ' días';
            if ($ending->h) $result[] = $ending->h . ' horas';
            if ($ending->i) $result[] = $ending->i . ' minutos';

            $ending = implode(' ', array_slice($result, 0, 2));
            return [
                'id' => $row->id,
                'name' => $row->name,
                'number' => $row->number,
                'price' => $row->getPricePlan(),
                'contact_email' => $row->contact_email,
                'phone_ws' => $row->phone_ws,
                'client_name' => $row->client_name,
                'plan_id' => $row->plan_id,
                'plan_period_id' => $row->plan_period_id,
                'ending_billing_cycle' => $row->ending_billing_cycle,
                'start_billing_cycle' => $row->start_billing_cycle,
                'plan' => [
                    'name' => $row->plan ? $row->plan->name : 'Sin plan',
                    'price' => $row->getPricePlan(),
                    'period' => $row->period ? $row->period->name : 'Mensual',
                    'active' => $row->activeService(),
                    'date_of_ending' => $row->ending_billing_cycle ? Carbon::parse($row->ending_billing_cycle)->format('Y-m-d') : '',
                    'diff_date_of_ending' => $ending,
                ],
                'phone_ws' => $row->phone_ws,
                'created_at' => $row->created_at->format('Y-m-d'),  
                'diff_time_creation' => Carbon::parse($row->created_at)->diffForHumans(),
                'pays' => $payments
            ];
        });

        $clients->prepend(['id' => null, 'name' => 'Todos']);

        return compact('clients', 'status', 'clientPlans', 'active_cron');
    }

    public function records(Request $request)
    {
        $paymen_orders = PaymentOrder::query();
        $payments = PaymentOrder::paymentsTotals()
        ->get();

        $payments = PaymentOrderState::all()->transform(function($row) use ($payments) {
            $total = $payments->where('id', $row->id)->first();
            return (object) [
                'id' => $row->id,
                'name' => $row->name,
                'total' => $total ? $total->total : 0,
            ];
        });

        if ($request->client_id) {
            $paymen_orders->where('client_id', $request->client_id);
        }
        if ($request->order_state_id) {
            $paymen_orders->where('order_state_id', $request->order_state_id);
        }

        if ($request->date_start || $request->date_end) {
            $date_start = $request->date_start ? Carbon::parse($request->date_start)->format('Y-m-d') : now()->format('Y-m-d');
            $date_end = $request->date_end ? Carbon::parse($request->date_end)->format('Y-m-d') : now()->format('Y-m-d');
            $paymen_orders->whereBetween('date_of_due', [$date_start, $date_end]);
            
        }

        return (new PaymentOrderCollection($paymen_orders->paginate(config('tenant.items_per_page'))))->additional([
            'pays' =>  $payments
        ]);
    }


    public function record($id, Request $request)
    {
        
        
    }

    public function create(Request $request) 
    {
        $validated = $request->validate([
            'client_id' => 'required',
            'amount' => 'required|numeric|min:0',
            'date_of_due' => 'required|date',
            'description' => 'nullable|string',
            'notify' => 'boolean'
        ]);

        $order = PaymentOrder::where('client_id', $validated['client_id'])
                    ->where('order_state_id', 1) // Pendiente   
                    ->first();

        if ($order) {
            return [
                'success' => false,
                'message' => 'El cliente ya tiene una orden de pago pendiente',
            ];
        }
        $or = null;

        DB::beginTransaction();

        $or = PaymentOrder::create([
            'order' => str_pad((PaymentOrder::count() + 1), 6, '0', STR_PAD_LEFT),
            'client_id' => $validated['client_id'],
            'amount' => $validated['amount'],
            'date_of_due' => $validated['date_of_due'],
            'description' => $validated['description'] ?? null,
            'order_state_id' => 1,
            'notifications' => 0,
            'created_by' => 'Manual' 
        ]);
        
        DB::commit();

        if ($validated['notify'] && $or) {
            $this->notify($or->id);
        }

        return [
            'success' => true,  
            'message' => 'Orden de pago creada con éxito',
        ];

    }

    public function pays(int $id)
    {
        $model = PaymentOrder::find($id);
        $model->date_of_payment = now()->toDateTimeString();
        $model->order_state_id = 2;
        $model->save();

        $client = $model->client;
        $months = optional($client->period)->months ? $client->period->months : 1;
        $ending = Carbon::parse($client->ending_billing_cycle)->addMonths($months);
        
        $client->ending_billing_cycle = $ending;
        $client->save();


        return [
            'success' => true,
            'message' => 'Orden de pago pagada con éxito',
        ];
    }

    public function cancel($id, Request $request)
    {
        $model = PaymentOrder::find($id);

        $model->order_state_id = 4;
        $model->save();
        return [
            'success' => true,
            'message' => 'Orden de pago anulada con éxito',
        ];
    }


    public function updateTable(Request $request)
    {
        $model = PaymentOrder::find($request->id);

        if ($request->date_of_due) {
            if (Carbon::parse($request->date_of_due)->greaterThan($model->date_of_due)) {
                $model->date_of_due = $request->date_of_due;
                $client = $model->client;
                $client->locked_tenant = false;
                $model->order_state_id = 1;
                $client->save();
                $tenancy = app(Environment::class);
                $tenancy->tenant($client->hostname->website);
                DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_tenant' => $client->locked_tenant]);

                $model->order_state_id = 1;
            } else if (Carbon::parse($request->date_of_due)->lessThan($model->date_of_due))
            {
                $model->date_of_due = $request->date_of_due;
                $client = $model->client;
                $client->locked_tenant = true;
                $client->save();
                $model->order_state_id = 3;
                $tenancy = app(Environment::class);
                $tenancy->tenant($client->hostname->website);
                DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_tenant' => $client->locked_tenant]);
            }
        }
        if ($request->price) {
            $model->amount = $request->price;
        }

        $model->save();

        return [
            'success' => true,
            'message' => 'Orden de pago actualizada con éxito',
        ];
    }

    public function notify($id)
    {
        $model = PaymentOrder::find($id);

        $response = $model->client->notifyOrder($id);
        if ($response['success']) {
            $model->notifications = $model->notifications + 1;
            $model->date_of_notification = now()->toDateTimeString();
            $model->save();
        }
        return $response;

    }

    public function updateClient($id, Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'plan_id' => 'required|integer|exists:plans,id',
            'plan_period_id' => 'required|integer|exists:plan_periods,id',
            'phone_ws' => 'nullable|string|max:20',
            'ending_billing_cycle' => 'nullable|date',
            'start_billing_cycle' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($id, $request) {
                    if (is_null($value)) {
                        return;
                    }

                    $client = Client::find($id);
                    $start_billing_cycle = Carbon::parse($value)->startOfDay();
                    if (!isset($client->ending_billing_cycle)) {
                        return;
                    }
                    $ending_billing_cycle = $client->ending_billing_cycle->startOfDay();
                    if (isset($request->ending_billing_cycle)) {
                        $ending_billing_cycle = Carbon::parse($request->ending_billing_cycle)->startOfDay();
                    }

                    if ($start_billing_cycle->day > $ending_billing_cycle->day) {
                        $fail('La fecha de activación debe ser anterior a la fecha de finalización del servicio');
                    }
                },
            ],
            'price' => 'numeric|min:0',
        ]);

        Client::find($id)->update([
            'client_name' => $validated['client_name'],
            'price' => $validated['price'],
            'contact_email' => $validated['contact_email'],
            'plan_id' => $validated['plan_id'],
            'plan_period_id' => $validated['plan_period_id'],
            'phone_ws' => $validated['phone_ws'] ?? null,
            'ending_billing_cycle' => isset($validated['ending_billing_cycle']) ? $validated['ending_billing_cycle'] : null,
            'start_billing_cycle' => isset($validated['start_billing_cycle']) ? $validated['start_billing_cycle'] : null,
        ]);
        
        return [
            'success' => true,
            'message' => 'Cliente actualizado con éxito',
        ];
    }

    public function clientTables(Request $request)
    {
        $plans = Plan::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'price' => $row->price,
                'currency_type_id' => $row->currency_type_id,
            ];
        });
        $periods = PlanPeriod::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'months' => $row->months,
            ];
        });

        return compact('plans', 'periods');
    }

}
