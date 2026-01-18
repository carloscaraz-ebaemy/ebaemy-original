<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Models\Tenant\Document;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsertStartBillingCycleForClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
         $clients = Client::all();
        $tenancy = app(Environment::class);

        foreach ($clients as $client) {

            //  No tocar clientes que YA TIENEN fecha
            if (!is_null($client->start_billing_cycle)) {
                continue;
            }

            $website = $client->hostname->website;
            $tenancy->tenant($website);

            $firstDocument = Document::orderBy('created_at', 'asc')->first();

            // Si el cliente NO ha emitido documentos →  NULL
            if (!$firstDocument) {
                continue;
            }
            $billingDate = Carbon::parse($firstDocument->created_at)->format('Y-m-d');

            $client->start_billing_cycle = $billingDate;
            $client->save();

            echo "Cliente {$client->name} actualizado con ciclo {$billingDate}\n";
        }
        return Command::SUCCESS;
    }
}
