<?php

namespace App\Services\Tenant\Carrier;

class CarrierApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $driver    = 'unknown',
        public readonly int    $httpCode  = 0,
        public readonly array  $response  = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, $httpCode ?: 0, $previous);
    }
}
