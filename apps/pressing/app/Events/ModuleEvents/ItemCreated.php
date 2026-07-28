<?php

namespace App\Events\ModuleEvents;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public $item,
        public $tenant
    ) {}
}
