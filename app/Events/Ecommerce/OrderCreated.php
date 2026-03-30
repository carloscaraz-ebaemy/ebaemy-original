<?php

namespace App\Events\Ecommerce;

use App\Models\Tenant\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public Order  $order;
    public string $customerName;
    public string $customerEmail;
    public string $customerPhone;

    public function __construct(Order $order, string $customerName, string $customerEmail, string $customerPhone = '')
    {
        $this->order         = $order;
        $this->customerName  = $customerName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
    }
}
