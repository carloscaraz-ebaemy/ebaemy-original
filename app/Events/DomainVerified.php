<?php

namespace App\Events;

use App\Models\System\DomainVerification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DomainVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DomainVerification $verification,
        public int $hostnameId,
    ) {}
}
