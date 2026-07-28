<?php

namespace App\Events\ModuleEvents;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public $client,
        public $tenant
    ) {}
}
