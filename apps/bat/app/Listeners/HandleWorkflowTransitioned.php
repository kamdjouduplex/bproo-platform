<?php

namespace App\Listeners;

use App\Events\QuoteAccepted;
use App\Events\QuoteSent;
use App\Events\WorkflowTransitioned;
use App\Notifications\OfferInReview;
use InovCom\Clients\Models\ClientActivity;
use InovCom\Devis\Models\Quote;
use InovCom\Offres\Models\Offer;
use Illuminate\Support\Facades\Log;

/**
 * Central dispatcher for workflow transitions.
 *
 * Listens to the generic WorkflowTransitioned event and fires
 * specific domain events based on the model type and transition.
 *
 * Add new cases here as Phase 1 / 2 features are built.
 */
class HandleWorkflowTransitioned
{
    public function handle(WorkflowTransitioned $event): void
    {
        $model = $event->model;
        $to    = $event->to;

        // ── Quotes ────────────────────────────────────────────────────
        if ($model instanceof Quote) {
            if ($to === 'accepted') {
                QuoteAccepted::dispatch($model, $event->userId);

                // Log client activity
                if ($model->client_id) {
                    try {
                        ClientActivity::log(
                            $model->client_id,
                            'quote_accepted',
                            "Devis {$model->code} accepté",
                            null,
                            $event->userId
                        );
                    } catch (\Throwable) {}
                }
            }

            if ($to === 'sent') {
                QuoteSent::dispatch($model, $event->userId);

                if ($model->client_id) {
                    try {
                        ClientActivity::log(
                            $model->client_id,
                            'quote_sent',
                            "Devis {$model->code} envoyé au client",
                            null,
                            $event->userId
                        );
                    } catch (\Throwable) {}
                }
            }

            if ($to === 'expired' && $model->client_id) {
                try {
                    ClientActivity::log(
                        $model->client_id,
                        'quote_expired',
                        "Devis {$model->code} expiré (validité dépassée)",
                        null,
                        $event->userId
                    );
                } catch (\Throwable) {}
            }
        }

        // ── Offers ────────────────────────────────────────────────────
        if ($model instanceof Offer) {
            if ($to === Offer::STATUS_IN_REVIEW && $model->assigned_to) {
                try {
                    $assignee = $model->assignedUser;
                    if ($assignee) {
                        $assignee->notify(new OfferInReview($model));
                    }
                } catch (\Throwable $e) {
                    Log::warning('offer.notify.in_review.failed', [
                        'offer_id' => $model->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        // ── Invoices ──────────────────────────────────────────────────
        // Phase 1: log transitions; Phase 2 will fire InvoiceSent, InvoicePaid events here
        Log::info('workflow.transitioned', [
            'table'   => $model->getTable(),
            'id'      => $model->getKey(),
            'from'    => $event->from,
            'to'      => $to,
            'user_id' => $event->userId,
        ]);
    }
}
