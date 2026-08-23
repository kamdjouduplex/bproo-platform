<?php

namespace InovCom\Crm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InovCom\Crm\Models\Opportunity;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Prospects\Services\ProspectsService;

class OpportunityService
{
    public function __construct(
        private ProspectsService $prospects,
        private ProspectScoringService $scoring,
    ) {}

    /**
     * @param  array{
     *     title:string,
     *     amount?:float|string|null,
     *     probability?:int,
     *     stage?:string,
     *     owner_id?:int|null,
     *     expected_close_date?:string|null,
     *     product_interest?:string|null,
     *     next_action?:array{type?:string,summary?:string,due_at:string,assignee_id?:int|null}
     * }  $data
     */
    public function createFromProspect(Prospect $prospect, array $data, ?int $userId = null): Opportunity
    {
        if ($prospect->status === Prospect::STATUS_NON_QUALIFIE) {
            throw new \RuntimeException('Un prospect non qualifié ne peut pas devenir une opportunité.');
        }

        $userId = $userId ?? auth('tenant')->id();
        $ownerId = isset($data['owner_id']) && $data['owner_id']
            ? (int) $data['owner_id']
            : ($prospect->owner_id ?: $userId);

        if (! $ownerId) {
            throw new \RuntimeException('Une opportunité ouverte doit avoir un commercial responsable.');
        }

        $next = $data['next_action'] ?? null;
        if (! is_array($next) || empty($next['due_at'])) {
            throw new \RuntimeException('Une opportunité ouverte doit avoir une prochaine action.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $prospect->need ?: ($prospect->product_interest ?: $prospect->name);
        }

        return DB::connection('tenant')->transaction(function () use ($prospect, $data, $userId, $ownerId, $next, $title) {
            if (! $prospect->owner_id) {
                $this->prospects->assignOwner($prospect, $ownerId, $userId);
            }

            if (in_array($prospect->status, [Prospect::STATUS_NOUVEAU, Prospect::STATUS_A_QUALIFIER], true)) {
                $this->prospects->changeStatus($prospect, Prospect::STATUS_QUALIFIE, null, 'Qualifié — opportunité créée.', $userId);
            }

            $stage = $data['stage'] ?? Opportunity::STAGE_QUALIFICATION;
            if (! array_key_exists($stage, Opportunity::stageOptions())) {
                $stage = Opportunity::STAGE_QUALIFICATION;
            }

            $opportunity = Opportunity::create([
                'prospect_id' => $prospect->id,
                'client_id' => $prospect->converted_client_id,
                'title' => $title,
                'product_interest' => $data['product_interest'] ?? $prospect->product_interest ?? $prospect->need,
                'amount' => isset($data['amount']) && $data['amount'] !== '' && $data['amount'] !== null
                    ? (float) $data['amount']
                    : $prospect->expected_value,
                'probability' => isset($data['probability'])
                    ? max(0, min(100, (int) $data['probability']))
                    : Opportunity::defaultProbability($stage),
                'stage' => $stage,
                'owner_id' => $ownerId,
                'expected_close_date' => $data['expected_close_date'] ?? $prospect->decision_deadline,
                'created_by' => $userId,
                'updated_by' => $userId,
                'last_activity_at' => now(),
            ]);

            $this->scheduleNextAction($opportunity, $next, $userId);

            $this->prospects->addActivity(
                $prospect->fresh(),
                ProspectActivity::TYPE_STATUS,
                'Opportunité créée : '.$opportunity->title,
                $userId,
                $prospect->status,
                $prospect->status,
                $opportunity->id
            );

            $this->scoring->recalculate($prospect->fresh());

            return $opportunity->fresh(['prospect', 'owner', 'nextPlannedActivity']);
        });
    }

    /**
     * @param  array{type?:string,summary?:string,body?:string,due_at:string,assignee_id?:int|null}  $data
     */
    public function scheduleNextAction(Opportunity $opportunity, array $data, ?int $userId = null): ProspectActivity
    {
        $prospect = $opportunity->prospect ?: Prospect::findOrFail($opportunity->prospect_id);
        $activity = $this->prospects->scheduleActivity($prospect, [
            'type' => $data['type'] ?? ProspectActivity::TYPE_CALL,
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'due_at' => $data['due_at'],
            'assignee_id' => $data['assignee_id'] ?? $opportunity->owner_id,
        ], $userId);

        $activity->opportunity_id = $opportunity->id;
        $activity->save();

        $opportunity->last_activity_at = now();
        $opportunity->save();

        return $activity;
    }

    public function moveToStage(
        Opportunity $opportunity,
        string $stage,
        ?string $lostReason = null,
        ?string $lostComment = null,
        ?int $userId = null
    ): Opportunity {
        $userId = $userId ?? auth('tenant')->id();
        if (! array_key_exists($stage, Opportunity::stageOptions())) {
            throw new \InvalidArgumentException('Étape invalide.');
        }

        if ($opportunity->stage === $stage) {
            return $opportunity;
        }

        $opportunity->lost_reason = $lostReason ?: $opportunity->lost_reason;
        $opportunity->lost_comment = $lostComment ?: $opportunity->lost_comment;
        $opportunity->loadMissing('nextPlannedActivity');

        $gaps = $opportunity->stageGaps($stage);
        if ($gaps !== []) {
            throw new \RuntimeException('Impossible d’avancer : '.implode(', ', $gaps).'.');
        }

        $from = $opportunity->stage;
        $opportunity->stage = $stage;
        $opportunity->probability = Opportunity::defaultProbability($stage);
        $opportunity->updated_by = $userId;
        $opportunity->last_activity_at = now();

        if ($stage === Opportunity::STAGE_PERDU) {
            $opportunity->lost_at = now();
            $opportunity->won_at = null;
        } elseif ($stage === Opportunity::STAGE_GAGNE) {
            $opportunity->won_at = now();
            $opportunity->lost_at = null;
            $opportunity->lost_reason = null;
        } else {
            $opportunity->lost_at = null;
            $opportunity->won_at = null;
            $opportunity->lost_reason = null;
        }

        $opportunity->save();

        $this->prospects->addActivity(
            $opportunity->prospect,
            ProspectActivity::TYPE_STATUS,
            'Opportunité « '.$opportunity->title.' » : '.Opportunity::stageOptions()[$from].' → '.Opportunity::stageOptions()[$stage]
                .($stage === Opportunity::STAGE_PERDU && $opportunity->lost_reason
                    ? ' — Motif : '.(Opportunity::lostReasonOptions()[$opportunity->lost_reason] ?? $opportunity->lost_reason)
                    : ''),
            $userId,
            $from,
            $stage,
            $opportunity->id
        );

        return $opportunity->fresh(['prospect', 'owner', 'nextPlannedActivity']);
    }

    public function markWon(Opportunity $opportunity, ?int $userId = null): Opportunity
    {
        $userId = $userId ?? auth('tenant')->id();
        $opportunity = $this->moveToStage($opportunity, Opportunity::STAGE_GAGNE, null, null, $userId);

        $prospect = $opportunity->prospect;
        if ($prospect && ! $prospect->isConverted()) {
            $prospect->status = Prospect::STATUS_GAGNE; // transitoire, convertToClient attendait gagne
            // New world: canConvert is based on won opportunity
            $prospect->save();
            try {
                $result = $this->prospects->convertToClient($prospect->fresh(), $userId);
                $opportunity->client_id = $result['client']->id;
                $opportunity->save();
            } catch (\Throwable $e) {
                // La conversion peut échouer (RCCM/NIU) — l'opportunité reste gagnée.
            }
        } elseif ($prospect?->converted_client_id) {
            $opportunity->client_id = $prospect->converted_client_id;
            $opportunity->save();
        }

        return $opportunity->fresh(['prospect', 'client', 'owner']);
    }

    public function markLost(Opportunity $opportunity, string $reason, ?string $comment = null, ?int $userId = null): Opportunity
    {
        if ($reason === '') {
            throw new \RuntimeException('Indiquez le motif de perte.');
        }
        if (! array_key_exists($reason, Opportunity::lostReasonOptions())) {
            $reason = 'autre';
        }

        $opportunity->lost_reason = $reason;
        $opportunity->lost_comment = $comment;
        $opportunity->save();

        return $this->moveToStage($opportunity, Opportunity::STAGE_PERDU, $reason, $comment, $userId);
    }

    /**
     * Transmet le contexte à l’ERP Devis — ne crée PAS le devis ici.
     *
     * @return array{url:string,opportunity:Opportunity}
     */
    public function transferToQuotations(Opportunity $opportunity, ?int $userId = null): array
    {
        $userId = $userId ?? auth('tenant')->id();
        $prospect = $opportunity->prospect ?: Prospect::findOrFail($opportunity->prospect_id);

        if ($opportunity->stage !== Opportunity::STAGE_INTENTION && $opportunity->isOpen()) {
            $opportunity = $this->moveToStage($opportunity, Opportunity::STAGE_INTENTION, null, null, $userId);
        }

        $clientId = $opportunity->client_id ?: $prospect->converted_client_id;
        if (! $clientId) {
            if (! $prospect->isConverted()) {
                // Préparer la conversion : statut gagne technique pour réutiliser convertToClient.
                $prospect->status = Prospect::STATUS_GAGNE;
                $prospect->save();
            }
            try {
                $result = $this->prospects->convertToClient($prospect->fresh(), $userId);
                $clientId = $result['client']->id;
                $prospect = $result['prospect'];
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Pour transmettre au module Devis, le prospect doit d’abord devenir client. '.$e->getMessage()
                );
            }
        }

        $opportunity->client_id = $clientId;
        $opportunity->transferred_at = now();
        $opportunity->updated_by = $userId;
        $opportunity->save();

        $this->prospects->addActivity(
            $prospect->fresh(),
            ProspectActivity::TYPE_STATUS,
            'Transmis au module Devis ERP — opportunité « '.$opportunity->title.' ».',
            $userId,
            $opportunity->stage,
            $opportunity->stage,
            $opportunity->id
        );

        if (! Route::has('tenant.quotations.create')) {
            throw new \RuntimeException('Le module Devis n’est pas disponible sur ce tenant.');
        }

        $tenant = request()->attributes->get('tenant');
        $url = route('tenant.quotations.create', array_filter([
            'tenant' => $tenant?->code,
            'client_id' => $clientId,
            'from_crm' => 1,
            'opportunity_id' => $opportunity->id,
        ]));

        return ['url' => $url, 'opportunity' => $opportunity->fresh(['prospect', 'client'])];
    }

    public function assignOwner(Opportunity $opportunity, ?int $ownerId, ?int $userId = null): Opportunity
    {
        if ($opportunity->isOpen() && ! $ownerId) {
            throw new \RuntimeException('Une opportunité ouverte doit avoir un commercial responsable.');
        }
        $userId = $userId ?? auth('tenant')->id();
        $opportunity->owner_id = $ownerId;
        $opportunity->updated_by = $userId;
        $opportunity->save();

        if ($opportunity->prospect && $ownerId) {
            $this->prospects->assignOwner($opportunity->prospect, $ownerId, $userId);
        }

        return $opportunity->fresh(['owner', 'prospect']);
    }

    public function update(Opportunity $opportunity, array $data, ?int $userId = null): Opportunity
    {
        if ($opportunity->isClosed()) {
            throw new \RuntimeException('Une opportunité close ne peut plus être modifiée.');
        }

        $userId = $userId ?? auth('tenant')->id();
        $opportunity->fill([
            'title' => isset($data['title']) ? trim((string) $data['title']) : $opportunity->title,
            'product_interest' => $data['product_interest'] ?? $opportunity->product_interest,
            'amount' => array_key_exists('amount', $data) && $data['amount'] !== '' && $data['amount'] !== null
                ? (float) $data['amount']
                : $opportunity->amount,
            'probability' => isset($data['probability'])
                ? max(0, min(100, (int) $data['probability']))
                : $opportunity->probability,
            'expected_close_date' => array_key_exists('expected_close_date', $data)
                ? $data['expected_close_date']
                : $opportunity->expected_close_date,
            'updated_by' => $userId,
        ]);

        if (array_key_exists('owner_id', $data) && $data['owner_id']) {
            $opportunity->owner_id = (int) $data['owner_id'];
        }

        if ($opportunity->isOpen() && ! $opportunity->owner_id) {
            throw new \RuntimeException('Une opportunité ouverte doit avoir un commercial responsable.');
        }

        $opportunity->save();

        return $opportunity->fresh(['prospect', 'owner', 'nextPlannedActivity']);
    }
}
