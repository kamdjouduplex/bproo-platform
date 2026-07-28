<?php

namespace App\Events;

use InovCom\Facturation\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired by the nightly CheckOverdueInvoices command for each newly overdue invoice. */
class InvoiceOverdue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
