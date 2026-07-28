<?php

namespace App\Events;

use InovCom\Devis\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a Quote transitions to 'accepted'. */
class QuoteAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly ?int  $userId,
    ) {}
}
