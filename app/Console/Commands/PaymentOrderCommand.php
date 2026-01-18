<?php

namespace App\Console\Commands;

use App\Http\Controllers\System\PaymentOrderController;
use App\Models\System\Client;
use App\Models\System\Configuration;
use App\Models\System\PaymentOrder;
use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaymentOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para la creación de ordenes de pago rapidas';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $clients = Client::where('locked_tenant', false)
                    ->whereNotNull('ending_billing_cycle')
                    ->get();
        $config = Configuration::first();

        if ($config->active_cron) {
            $now = now();
            foreach ($clients as $client) {
                $this->createOrderPayment($client, $config);
            }

            $midnight = $now->format('H:i');
            if ($midnight === '00:00') {
                $this->verifiedOrder($client);
            }

        }

    }

    private function createOrderPayment(Client $client, Configuration $config)
    {
        $day_notification = $client->dayNotification();
        $range_start= now()->startOfMonth();
        $range_end= now()->endOfMonth();
        $now = now();

        $order_payment = PaymentOrder::whereBetween('date_of_due', [$range_start, $range_end])
                        ->where('client_id', $client->id)
                        ->first();


        $ending_service = Carbon::parse($client->ending_billing_cycle);
        $today_notification = Carbon::createFromDate($ending_service->year, $ending_service->month, $day_notification);
        $hour_notification = Carbon::today()->setTimeFromTimeString($config->hour_generate_payment_order);
        if ( ($today_notification->isSameDay($now) && Carbon::now()->isSameHour($hour_notification) )&& !$order_payment) {
            $id = $client->createPayemtnOrder(); // Crear orden de pago
                if ($config->send_notification_cron) {
                    app(PaymentOrderController::class)->notify($id);
                }
        }
    }

    private function verifiedOrder()
    {
        $range_start= now()->startOfMonth();
        $range_end= now()->endOfMonth();
        $order_payments = PaymentOrder::whereBetween('date_of_due', [$range_start, $range_end])
                        ->where('order_state_id', 1) 
                        ->get();


        foreach ($order_payments as $order_payment) {
            if (($order_payment->created_by  === 'Sistema') && (Carbon::now()->greaterThan(Carbon::parse($order_payment->date_of_due)) && $order_payment->order_state_id == 1)) {
                $client = $order_payment->client;
                $client->locked_tenant = true;
                $client->save();

                $tenancy = app(Environment::class);
                $tenancy->tenant($client->hostname->website);
                DB::connection('tenant')->table('configurations')->where('id', 1)->update(['locked_tenant' => $client->locked_tenant]);
                $order_payment->order_state_id = 3; // Vencida
                $order_payment->save();
            }
        }

    }
}
