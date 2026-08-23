<?php

namespace InovCom\Prospects\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Services\ClientCodeGenerator;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Users\Models\User;

class ProspectsService
{
    public function create(array $data, ?int $userId = null): Prospect
    {
        $userId = $userId ?? auth('tenant')->id();

        return DB::connection('tenant')->transaction(function () use ($data, $userId) {
            $prospect = Prospect::create([
                'reference' => $this->generateReference(),
                'name' => trim((string) ($data['name'] ?? '')),
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'type' => $data['type'] ?? 'company',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? ($data['phone'] ?? null),
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'sector' => $data['sector'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'rccm' => $data['rccm'] ?? null,
                'niu' => $data['niu'] ?? null,
                'source' => $data['source'] ?? Prospect::SOURCE_OTHER,
                'status' => $data['status'] ?? Prospect::STATUS_NOUVEAU,
                'cost' => (float) ($data['cost'] ?? 0),
                'expected_value' => isset($data['expected_value']) && $data['expected_value'] !== ''
                    ? (float) $data['expected_value']
                    : null,
                'owner_id' => $data['owner_id'] ?? $userId,
                'notes' => $data['notes'] ?? null,
                'need' => $data['need'] ?? null,
                'product_interest' => $data['product_interest'] ?? null,
                'problem' => $data['problem'] ?? null,
                'store_id' => $this->resolveStoreId(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->addActivity(
                $prospect,
                ProspectActivity::TYPE_NOTE,
                'Prospect créé.',
                $userId
            );

            return $prospect->fresh(['owner', 'creator']);
        });
    }

    public function update(Prospect $prospect, array $data, ?int $userId = null): Prospect
    {
        if (! $prospect->isEditable()) {
            throw new \RuntimeException('Un prospect converti ne peut plus être modifié.');
        }

        $userId = $userId ?? auth('tenant')->id();

        $prospect->fill([
            'name' => trim((string) ($data['name'] ?? $prospect->name)),
            'first_name' => array_key_exists('first_name', $data) ? $data['first_name'] : $prospect->first_name,
            'last_name' => array_key_exists('last_name', $data) ? $data['last_name'] : $prospect->last_name,
            'company_name' => array_key_exists('company_name', $data) ? $data['company_name'] : $prospect->company_name,
            'job_title' => array_key_exists('job_title', $data) ? $data['job_title'] : $prospect->job_title,
            'type' => $data['type'] ?? $prospect->type,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $prospect->whatsapp,
            'address' => $data['address'] ?? null,
            'city' => array_key_exists('city', $data) ? $data['city'] : $prospect->city,
            'sector' => array_key_exists('sector', $data) ? $data['sector'] : $prospect->sector,
            'tax_id' => $data['tax_id'] ?? null,
            'rccm' => $data['rccm'] ?? null,
            'niu' => $data['niu'] ?? null,
            'source' => $data['source'] ?? $prospect->source,
            'cost' => (float) ($data['cost'] ?? $prospect->cost),
            'expected_value' => isset($data['expected_value']) && $data['expected_value'] !== ''
                ? (float) $data['expected_value']
                : $prospect->expected_value,
            'owner_id' => array_key_exists('owner_id', $data) ? $data['owner_id'] : $prospect->owner_id,
            'notes' => $data['notes'] ?? null,
            'need' => array_key_exists('need', $data) ? $data['need'] : $prospect->need,
            'product_interest' => array_key_exists('product_interest', $data) ? $data['product_interest'] : $prospect->product_interest,
            'problem' => array_key_exists('problem', $data) ? $data['problem'] : $prospect->problem,
            'expectations' => array_key_exists('expectations', $data) ? $data['expectations'] : $prospect->expectations,
            'decision_maker_name' => array_key_exists('decision_maker_name', $data) ? $data['decision_maker_name'] : $prospect->decision_maker_name,
            'need_score' => array_key_exists('need_score', $data) ? (int) $data['need_score'] : $prospect->need_score,
            'decision_score' => array_key_exists('decision_score', $data) ? (int) $data['decision_score'] : $prospect->decision_score,
            'budget_score' => array_key_exists('budget_score', $data) ? (int) $data['budget_score'] : $prospect->budget_score,
            'timeline_score' => array_key_exists('timeline_score', $data) ? (int) $data['timeline_score'] : $prospect->timeline_score,
            'estimated_budget' => array_key_exists('estimated_budget', $data) && $data['estimated_budget'] !== '' && $data['estimated_budget'] !== null
                ? (float) $data['estimated_budget']
                : $prospect->estimated_budget,
            'decision_deadline' => array_key_exists('decision_deadline', $data) ? $data['decision_deadline'] : $prospect->decision_deadline,
            'updated_by' => $userId,
        ]);
        $prospect->save();

        return $prospect->fresh(['owner', 'creator']);
    }

    public function changeStatus(
        Prospect $prospect,
        string $newStatus,
        ?string $lostReason = null,
        ?string $note = null,
        ?int $userId = null
    ): Prospect {
        $userId = $userId ?? auth('tenant')->id();
        $newStatus = $this->validateStatus($newStatus);
        if ($newStatus === Prospect::STATUS_PERDU) {
            $newStatus = Prospect::STATUS_NON_QUALIFIE;
        }
        if ($newStatus === Prospect::STATUS_GAGNE) {
            // Conservé uniquement comme pont technique vers convertToClient.
        }
        $oldStatus = $prospect->status;

        if ($oldStatus === Prospect::STATUS_CONVERTI) {
            throw new \RuntimeException('Un prospect converti ne peut plus changer de statut.');
        }

        if ($oldStatus === $newStatus) {
            return $prospect;
        }

        if ($newStatus === Prospect::STATUS_CONVERTI) {
            throw new \RuntimeException('Utilisez la conversion en client pour marquer un prospect comme converti.');
        }

        if ($newStatus === Prospect::STATUS_NON_QUALIFIE && blank($lostReason) && blank($prospect->lost_reason)) {
            throw new \RuntimeException('Indiquez le motif (non qualifié / perdu).');
        }

        return DB::connection('tenant')->transaction(function () use ($prospect, $newStatus, $oldStatus, $lostReason, $note, $userId) {
            $prospect->status = $newStatus;
            $prospect->updated_by = $userId;
            if ($newStatus === Prospect::STATUS_NON_QUALIFIE) {
                $prospect->lost_reason = $lostReason ?: $prospect->lost_reason;
            } else {
                $prospect->lost_reason = null;
            }
            $prospect->save();

            $body = sprintf(
                'Statut : %s → %s',
                Prospect::statusLabel($oldStatus),
                Prospect::statusLabel($newStatus)
            );
            if ($newStatus === Prospect::STATUS_NON_QUALIFIE && $prospect->lost_reason) {
                $body .= ' — Motif : ' . $prospect->lost_reason;
            }
            if ($note) {
                $body .= ' — ' . trim($note);
            }

            $this->addActivity(
                $prospect,
                ProspectActivity::TYPE_STATUS,
                $body,
                $userId,
                $oldStatus,
                $newStatus
            );

            return $prospect->fresh(['owner', 'activities.user']);
        });
    }

    public function addActivity(
        Prospect $prospect,
        string $type,
        string $body,
        ?int $userId = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $opportunityId = null
    ): ProspectActivity {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Le contenu de l\'activité ne peut pas être vide.');
        }

        if (! array_key_exists($type, ProspectActivity::typeOptions())) {
            $type = ProspectActivity::TYPE_NOTE;
        }

        $activity = ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'opportunity_id' => $opportunityId,
            'user_id' => $userId ?? auth('tenant')->id(),
            'assignee_id' => $prospect->owner_id,
            'type' => $type,
            'summary' => null,
            'state' => ProspectActivity::STATE_DONE,
            'body' => $body,
            'due_at' => null,
            'completed_at' => now(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);

        if ($type !== ProspectActivity::TYPE_STATUS) {
            $prospect->last_contacted_at = now();
            $prospect->save();
        }

        return $activity;
    }

    /**
     * Place un prospect au démarrage du pipeline (Qualifié).
     */
    public function initiateAsProspect(Prospect $prospect, ?int $userId = null): Prospect
    {
        if (in_array($prospect->status, Prospect::pipelineStatuses(), true)) {
            return $prospect;
        }

        if ($prospect->isConverted() || $prospect->isLost()) {
            throw new \RuntimeException('Ce prospect n’est plus éligible au pipeline.');
        }

        $gaps = $prospect->initiationGaps();
        if ($gaps !== []) {
            throw new \RuntimeException('Complétez la fiche : '.implode(', ', $gaps).'.');
        }

        return $this->changeStatus(
            $prospect,
            Prospect::STATUS_A_QUALIFIER,
            null,
            'Qualification démarrée.',
            $userId
        );
    }

    public function assignOwner(Prospect $prospect, ?int $ownerId, ?int $userId = null): Prospect
    {
        if ($prospect->isConverted()) {
            throw new \RuntimeException('Un prospect converti ne peut plus être réassigné.');
        }

        $userId = $userId ?? auth('tenant')->id();
        $oldOwnerId = $prospect->owner_id;
        if ((int) ($oldOwnerId ?? 0) === (int) ($ownerId ?? 0)) {
            return $prospect;
        }

        $prospect->owner_id = $ownerId ?: null;
        $prospect->updated_by = $userId;
        $prospect->save();

        $oldName = $oldOwnerId
            ? (User::find($oldOwnerId)?->name ?? '#'.$oldOwnerId)
            : 'Non assigné';
        $newName = $ownerId
            ? (User::find($ownerId)?->name ?? '#'.$ownerId)
            : 'Non assigné';

        $this->addActivity(
            $prospect,
            ProspectActivity::TYPE_NOTE,
            'Commercial : '.$oldName.' → '.$newName,
            $userId
        );

        return $prospect->fresh(['owner']);
    }

    /**
     * @param  array{type:string,body?:?string,summary?:?string,due_at:string,assignee_id?:?int}  $data
     */
    public function scheduleActivity(Prospect $prospect, array $data, ?int $userId = null): ProspectActivity
    {
        if ($prospect->isLost() && $prospect->status === Prospect::STATUS_NON_QUALIFIE) {
            throw new \RuntimeException('Impossible de planifier une action sur un prospect non qualifié.');
        }

        $type = (string) ($data['type'] ?? ProspectActivity::TYPE_CALL);
        if (! array_key_exists($type, ProspectActivity::actionableTypeOptions())) {
            $type = ProspectActivity::TYPE_TASK;
        }

        $dueAt = $data['due_at'] ?? null;
        if (! $dueAt) {
            throw new \InvalidArgumentException('Indiquez la date / heure de la prochaine action.');
        }

        $summary = trim((string) ($data['summary'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            $body = $summary !== ''
                ? $summary
                : ProspectActivity::typeLabel($type).' planifié(e).';
        }

        $assigneeId = isset($data['assignee_id']) && $data['assignee_id'] !== '' && $data['assignee_id'] !== null
            ? (int) $data['assignee_id']
            : ($prospect->owner_id ?: ($userId ?? auth('tenant')->id()));

        return ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'user_id' => $userId ?? auth('tenant')->id(),
            'assignee_id' => $assigneeId,
            'type' => $type,
            'summary' => $summary !== '' ? $summary : ProspectActivity::typeLabel($type),
            'state' => ProspectActivity::STATE_PLANNED,
            'body' => $body,
            'due_at' => $dueAt,
            'completed_at' => null,
        ]);
    }

    public function completeActivity(
        ProspectActivity $activity,
        ?string $note = null,
        ?int $userId = null,
        ?string $result = null,
        ?array $nextAction = null
    ): ProspectActivity {
        if ($activity->state === ProspectActivity::STATE_DONE) {
            return $activity;
        }

        $activity->state = ProspectActivity::STATE_DONE;
        $activity->completed_at = now();
        if ($result) {
            $activity->result = $result;
        }
        if ($note) {
            $activity->body = trim($activity->body."\n".trim($note));
        }
        $activity->save();

        $prospect = $activity->prospect;
        if ($prospect && $activity->type !== ProspectActivity::TYPE_STATUS) {
            $prospect->last_contacted_at = now();
            $prospect->save();
        }

        if ($nextAction && ! empty($nextAction['due_at']) && $prospect) {
            $this->scheduleActivity($prospect, [
                'type' => $nextAction['type'] ?? ProspectActivity::TYPE_FOLLOWUP,
                'summary' => $nextAction['summary'] ?? null,
                'body' => $nextAction['body'] ?? null,
                'due_at' => $nextAction['due_at'],
                'assignee_id' => $nextAction['assignee_id'] ?? $activity->assignee_id,
            ], $userId);
        }

        return $activity;
    }

    public function cancelActivity(ProspectActivity $activity, ?int $userId = null): ProspectActivity
    {
        if ($activity->state !== ProspectActivity::STATE_PLANNED) {
            return $activity;
        }

        $activity->state = ProspectActivity::STATE_CANCELLED;
        $activity->save();

        return $activity;
    }

    /**
     * @return array{prospect: Prospect, client: Client}
     */
    public function convertToClient(Prospect $prospect, ?int $userId = null): array
    {
        if (! $prospect->canConvert()) {
            throw new \RuntimeException('Ce prospect ne peut pas être converti.');
        }

        $gaps = $prospect->conversionGaps();
        if ($gaps !== []) {
            throw new \RuntimeException(
                "Impossible de convertir : " . implode(' ', $gaps)
            );
        }

        // Aligné sur Clients : RCCM / NIU uniques
        if ($prospect->type === 'company') {
            $rccm = trim((string) $prospect->rccm);
            $niu = trim((string) $prospect->niu);
            if ($rccm !== '' && Client::query()->where('rccm', $rccm)->whereNull('deleted_at')->exists()) {
                throw new \RuntimeException('Ce RCCM est déjà attribué à un autre client.');
            }
            if ($niu !== '' && Client::query()->where('niu', $niu)->whereNull('deleted_at')->exists()) {
                throw new \RuntimeException('Ce NIU est déjà attribué à un autre client.');
            }
        }

        $userId = $userId ?? auth('tenant')->id();

        return DB::connection('tenant')->transaction(function () use ($prospect, $userId) {
            $client = new Client();
            $client->fill([
                'code' => app(ClientCodeGenerator::class)->next(),
                'name' => $prospect->name,
                'type' => in_array($prospect->type, ['individual', 'company'], true) ? $prospect->type : 'company',
                'email' => $prospect->email,
                'phone' => $prospect->phone,
                'address' => $prospect->address,
                'tax_id' => $prospect->tax_id,
                'rccm' => $prospect->rccm,
                'niu' => $prospect->niu,
                'salesrep_id' => $prospect->owner_id,
                'credit_limit' => 0,
                'discount_rate' => 0,
                'price_tier' => 'retail',
                'is_active' => true,
                'notes' => $this->buildClientNotes($prospect),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (Schema::connection('tenant')->hasColumn('clients', 'store_id') && $prospect->store_id) {
                $client->store_id = $prospect->store_id;
            }

            $client->save();

            $oldStatus = $prospect->status;
            $prospect->status = Prospect::STATUS_CONVERTI;
            $prospect->converted_client_id = $client->id;
            $prospect->converted_at = now();
            $prospect->updated_by = $userId;
            $prospect->save();

            $this->addActivity(
                $prospect,
                ProspectActivity::TYPE_STATUS,
                'Converti en client ' . $client->code . ' — ' . $client->name,
                $userId,
                $oldStatus,
                Prospect::STATUS_CONVERTI
            );

            return [
                'prospect' => $prospect->fresh(['owner', 'convertedClient', 'activities.user']),
                'client' => $client,
            ];
        });
    }

    public function delete(Prospect $prospect): void
    {
        if ($prospect->isConverted()) {
            throw new \RuntimeException('Impossible de supprimer un prospect déjà converti.');
        }

        $prospect->delete();
    }

    /**
     * @return array{total:int,nouveau:int,contacte:int,qualifie:int,converti:int,perdu:int,conversion_rate:float,by_source:list<array{source:string,label:string,total:int,converted:int,rate:float}>}
     */
    public function summarize(?string $status = null, ?string $source = null, ?int $ownerId = null): array
    {
        $base = Prospect::query()
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($source && $source !== 'all', fn ($q) => $q->where('source', $source))
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId));

        $total = (clone $base)->count();
        $counts = [];
        foreach (array_keys(Prospect::statusOptions()) as $st) {
            $counts[$st] = (clone $base)->where('status', $st)->count();
        }

        $converted = $counts[Prospect::STATUS_CONVERTI] ?? 0;
        $closed = $converted + ($counts[Prospect::STATUS_PERDU] ?? 0);
        $conversionRate = $closed > 0 ? round(($converted / $closed) * 100, 1) : 0.0;

        $bySource = [];
        foreach (Prospect::sourceOptions() as $src => $label) {
            $srcTotal = (clone $base)->where('source', $src)->count();
            if ($srcTotal === 0) {
                continue;
            }
            $srcConverted = (clone $base)->where('source', $src)->where('status', Prospect::STATUS_CONVERTI)->count();
            $srcLost = (clone $base)->where('source', $src)->where('status', Prospect::STATUS_PERDU)->count();
            $srcClosed = $srcConverted + $srcLost;
            $bySource[] = [
                'source' => $src,
                'label' => $label,
                'total' => $srcTotal,
                'converted' => $srcConverted,
                'rate' => $srcClosed > 0 ? round(($srcConverted / $srcClosed) * 100, 1) : 0.0,
            ];
        }

        return array_merge([
            'total' => $total,
            'conversion_rate' => $conversionRate,
            'by_source' => $bySource,
        ], $counts);
    }

    private function buildClientNotes(Prospect $prospect): ?string
    {
        $parts = [];
        if ($prospect->notes) {
            $parts[] = trim((string) $prospect->notes);
        }
        $parts[] = 'Issu du prospect ' . $prospect->reference
            . ' (source : ' . Prospect::sourceLabel((string) $prospect->source) . ')';

        return implode("\n", $parts);
    }

    private function generateReference(): string
    {
        $prefix = 'PR-' . now()->format('Y') . '-';
        $last = Prospect::query()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('reference');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function validateStatus(string $status): string
    {
        $allowed = array_merge(
            array_keys(Prospect::statusOptions()),
            [Prospect::STATUS_GAGNE, Prospect::STATUS_PERDU, Prospect::STATUS_CONTACTE, Prospect::STATUS_NEGOCIATION]
        );
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        return $status;
    }

    private function resolveStoreId(): ?int
    {
        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }
}
