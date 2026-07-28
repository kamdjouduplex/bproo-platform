<?php

namespace InovCom\Devis\Http\Controllers;

use App\Services\TenantManager;
use InovCom\Clients\Models\ClientActivity;
use InovCom\Devis\Models\Quote;
use InovCom\Devis\Services\QuoteEmailDraftService;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class QuoteEmailDraftController extends Controller
{
    public function __invoke(
        Request $request,
        Quote $quote,
        QuoteEmailDraftService $drafts,
    ): Response {
        $user = auth('tenant')->user();
        if (!$user) {
            abort(403);
        }

        $type = $request->query('type', 'send');
        if (!in_array($type, ['send', 'reminder'], true)) {
            abort(400);
        }

        if ($type === 'send' && !$user->hasPermission('devis.send')) {
            abort(403);
        }

        if ($type === 'reminder' && !$user->hasPermission('devis.view')) {
            abort(403);
        }

        $quote->loadMissing(['client', 'lines']);

        try {
            if ($type === 'send' && $quote->canTransitionTo('sent')) {
                $quote->transitionTo('sent', $user->id);
            }

            if ($type === 'send' && $quote->client_id) {
                try {
                    ClientActivity::log(
                        $quote->client_id,
                        'quote_sent',
                        __('Brouillon e-mail généré pour le devis :code', ['code' => $quote->code]),
                        null,
                        $user->id
                    );
                } catch (\Throwable) {
                }
            }

            if ($type === 'reminder') {
                $quote->update(['last_reminder_at' => now()]);

                if ($quote->client_id) {
                    try {
                        ClientActivity::log(
                            $quote->client_id,
                            'quote_reminder',
                            __('Relance envoyée pour le devis :code', ['code' => $quote->code]),
                            null,
                            $user->id
                        );
                    } catch (\Throwable) {
                    }
                }
            }

            $eml = $drafts->build($quote->fresh(['client', 'lines']), $type);
            $filename = $drafts->suggestedFilename($quote, $type);

            return response($eml, 200, [
                'Content-Type'        => 'message/rfc822',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (InvalidWorkflowTransitionException $e) {
            abort(422, $e->getMessage());
        } catch (\Throwable $e) {
            abort(422, $e->getMessage());
        }
    }
}
