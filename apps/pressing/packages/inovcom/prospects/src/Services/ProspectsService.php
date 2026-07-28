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

class ProspectsService
{
    public function create(array $data, ?int $userId = null): Prospect
    {
        $userId = $userId ?? auth('tenant')->id();

        return DB::connection('tenant')->transaction(function () use ($data, $userId) {
            $prospect = Prospect::create([
                'reference' => $this->generateReference(),
                'name' => trim((string) ($data['name'] ?? '')),
                'type' => $data['type'] ?? 'company',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'rccm' => $data['rccm'] ?? null,
                'niu' => $data['niu'] ?? null,
                'source' => $data['source'] ?? Prospect::SOURCE_OTHER,
                'status' => Prospect::STATUS_NOUVEAU,
                'cost' => (float) ($data['cost'] ?? 0),
                'expected_value' => isset($data['expected_value']) && $data['expected_value'] !== ''
                    ? (float) $data['expected_value']
                    : null,
                'owner_id' => $data['owner_id'] ?? null,
                'notes' => $data['notes'] ?? null,
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
            'type' => $data['type'] ?? $prospect->type,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'rccm' => $data['rccm'] ?? null,
            'niu' => $data['niu'] ?? null,
            'source' => $data['source'] ?? $prospect->source,
            'cost' => (float) ($data['cost'] ?? $prospect->cost),
            'expected_value' => isset($data['expected_value']) && $data['expected_value'] !== ''
                ? (float) $data['expected_value']
                : null,
            'owner_id' => $data['owner_id'] ?? null,
            'notes' => $data['notes'] ?? null,
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

        if ($newStatus === Prospect::STATUS_PERDU && blank($lostReason) && blank($prospect->lost_reason)) {
            throw new \RuntimeException('Indiquez le motif de perte.');
        }

        return DB::connection('tenant')->transaction(function () use ($prospect, $newStatus, $oldStatus, $lostReason, $note, $userId) {
            $prospect->status = $newStatus;
            $prospect->updated_by = $userId;
            if ($newStatus === Prospect::STATUS_PERDU) {
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
            if ($newStatus === Prospect::STATUS_PERDU && $prospect->lost_reason) {
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
        ?string $toStatus = null
    ): ProspectActivity {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Le contenu de l\'activité ne peut pas être vide.');
        }

        if (! array_key_exists($type, ProspectActivity::typeOptions())) {
            $type = ProspectActivity::TYPE_NOTE;
        }

        return ProspectActivity::create([
            'prospect_id' => $prospect->id,
            'user_id' => $userId ?? auth('tenant')->id(),
            'type' => $type,
            'body' => $body,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
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
        if (! array_key_exists($status, Prospect::statusOptions())) {
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
