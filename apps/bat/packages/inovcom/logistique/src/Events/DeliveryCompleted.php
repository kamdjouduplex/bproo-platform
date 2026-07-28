<?php

namespace InovCom\Logistique\Events;

use InovCom\Logistique\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Delivery $delivery) {}
}
