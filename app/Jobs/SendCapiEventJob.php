<?php

namespace App\Jobs;

use App\Services\Tenant\FacebookConversionsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCapiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $queue = 'default';

    private string $eventName;
    private array  $data;

    public function __construct(string $eventName, array $data)
    {
        $this->eventName = $eventName;
        $this->data      = $data;
    }

    public function handle(): void
    {
        $capi = FacebookConversionsApiService::fromConfig();

        if (!$capi) {
            return;
        }

        $capi->sendEvent($this->eventName, $this->data);
    }
}
