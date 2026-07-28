<?php

namespace App\Listeners;

use App\Events\QuoteAccepted;
use Illuminate\Support\Facades\Log;
use InovCom\Devis\Services\QuoteAcceptanceService;

/**
 * When a Quote is accepted, route execution (project or maintenance contract).
 *
 * Runs synchronously so the linked record is available on the quote page.
 */
class CreateProjectFromQuote
{
    public function handle(QuoteAccepted $event): void
    {
        try {
            app(QuoteAcceptanceService::class)->execute($event->quote);
        } catch (\Throwable $e) {
            Log::error('CreateProjectFromQuote: failed.', [
                'quote_id' => $event->quote->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
