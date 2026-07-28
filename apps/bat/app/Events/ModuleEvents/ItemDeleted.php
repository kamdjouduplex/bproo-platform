<?php

namespace App\Events\ModuleEvents;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $itemId,
        public $tenant
    ) {}
}
