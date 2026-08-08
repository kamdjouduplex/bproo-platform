<?php

namespace InovCom\Prescriptions\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\PrescriptionsApi;
use InovCom\Prescriptions\Models\Prescription;
use InovCom\Prescriptions\Models\PrescriptionLine;

class PrescriptionsApiService implements PrescriptionsApi
{
    /** Avoid repeating expire UPDATE on every POS search within the same request. */
    private static bool $expiredThisRequest = false;

    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('prescriptions')
            && Schema::connection('tenant')->hasTable('prescription_lines');
    }

    public function listActiveForSale(?int $clientId = null): Collection
    {
        if (! $this->isAvailable()) {
            return collect();
        }

        $this->expireOverdue();

        $query = Prescription::query()
            ->with(['lines.item', 'client'])
            ->where('status', Prescription::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
            })
            ->orderBy('number');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->get()->filter(fn (Prescription $rx) => $this->hasRemaining($rx))->values()
            ->map(function (Prescription $rx) {
                $rx->setAttribute('pos_label', $rx->number.' — '.$this->linesSummary($rx));

                return $rx;
            });
    }

    public function searchForSale(string $query, ?int $clientId = null, int $limit = 15): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $this->expireOverdue();
        $term = trim($query);

        $builder = Prescription::query()
            ->with(['lines.item', 'client'])
            ->where('status', Prescription::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
            })
            ->orderByDesc('updated_at')
            ->limit(max(1, min(30, $limit)));

        if ($clientId) {
            $builder->where('client_id', $clientId);
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $builder->where(function ($q) use ($like) {
                $q->where('number', 'like', $like)
                    ->orWhere('prescriber_name', 'like', $like)
                    ->orWhereHas('client', function ($cq) use ($like) {
                        $cq->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        return $builder->get()
            ->filter(fn (Prescription $rx) => $this->hasRemaining($rx))
            ->take($limit)
            ->map(fn (Prescription $rx) => $this->toSearchRow($rx))
            ->values()
            ->all();
    }

    public function createQuickForSale(array $data): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Module ordonnances indisponible.');
        }

        $clientId = (int) ($data['client_id'] ?? 0);
        $lines = array_values(array_filter($data['lines'] ?? [], fn ($l) => ! empty($l['item_id']) && (float) ($l['quantity'] ?? 0) > 0));
        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Sélectionnez le patient (client) avant de créer l’ordonnance.');
        }
        if ($lines === []) {
            throw new \InvalidArgumentException('Ajoutez au moins un médicament prescrit.');
        }

        $rx = new Prescription();
        $rx->fill([
            'number' => $this->generateNumber(),
            'client_id' => $clientId,
            'prescriber_name' => ($data['prescriber_name'] ?? null) ?: null,
            'prescriber_contact' => ($data['prescriber_contact'] ?? null) ?: null,
            'valid_from' => now()->toDateString(),
            'valid_until' => ! empty($data['valid_until']) ? $data['valid_until'] : now()->addDays(30)->toDateString(),
            'status' => Prescription::STATUS_ACTIVE,
            'notes' => ($data['notes'] ?? null) ?: null,
        ]);
        $rx->save();

        foreach ($lines as $idx => $row) {
            PrescriptionLine::create([
                'prescription_id' => $rx->id,
                'item_id' => (int) $row['item_id'],
                'quantity' => (float) $row['quantity'],
                'quantity_dispensed' => 0,
                'instructions' => $row['instructions'] ?? null,
                'sort_order' => $idx,
            ]);
        }

        $rx->load(['lines.item', 'client']);

        return [
            'id' => (int) $rx->id,
            'number' => (string) $rx->number,
            'status_label' => $rx->dispensationStatusLabel(),
            'lines_summary' => $this->linesSummary($rx),
        ];
    }

    public function snapshotForSale(int $prescriptionId): ?array
    {
        if (! $this->isAvailable() || $prescriptionId <= 0) {
            return null;
        }

        $this->expireOverdue();
        $rx = Prescription::with(['lines.item', 'client'])->find($prescriptionId);
        if (! $rx) {
            return null;
        }

        // Still show chip for an already-attached Rx even if fully dispensed mid-session.
        return $this->toSearchRow($rx);
    }

    public function closeRemaining(int $prescriptionId, ?string $reason = null): void
    {
        if (! $this->isAvailable() || $prescriptionId <= 0) {
            return;
        }

        $rx = Prescription::with('lines')->find($prescriptionId);
        if (! $rx) {
            throw new \InvalidArgumentException('Ordonnance introuvable.');
        }

        if ($rx->status === Prescription::STATUS_DISPENSED) {
            return;
        }

        $note = trim(($rx->notes ? $rx->notes."\n" : '').'Reste clôturé le '.now()->format('d/m/Y'));
        if ($reason) {
            $note .= ' — '.$reason;
        }
        $rx->notes = $note;
        $rx->status = Prescription::STATUS_CANCELLED;
        $rx->save();
    }

    public function expireOverdue(): int
    {
        if (! $this->isAvailable() || self::$expiredThisRequest) {
            return 0;
        }

        self::$expiredThisRequest = true;

        return Prescription::query()
            ->where('status', Prescription::STATUS_ACTIVE)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->update(['status' => Prescription::STATUS_EXPIRED]);
    }

    public function applyDispensationFromSale(int $prescriptionId, array $cartLines): array
    {
        if (! $this->isAvailable() || $prescriptionId <= 0) {
            return [];
        }

        $this->expireOverdue();

        $rx = Prescription::with('lines')->find($prescriptionId);
        if (! $rx) {
            return [];
        }

        if ($rx->status === Prescription::STATUS_EXPIRED || $rx->status === Prescription::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette ordonnance n’est plus délivrable (expirée ou clôturée).');
        }

        if ($rx->valid_until && $rx->valid_until->lt(now()->startOfDay())) {
            $rx->status = Prescription::STATUS_EXPIRED;
            $rx->save();
            throw new \RuntimeException('Ordonnance expirée (validité dépassée).');
        }

        $soldByItem = [];
        foreach ($cartLines as $cartItem) {
            $itemId = (int) ($cartItem['item_id'] ?? 0);
            if ($itemId <= 0 || ! empty($cartItem['is_set'])) {
                continue;
            }
            $soldByItem[$itemId] = ($soldByItem[$itemId] ?? 0) + (float) ($cartItem['quantity'] ?? 0);
        }

        $dispensedThisSale = [];

        foreach ($rx->lines as $rxLine) {
            $itemId = (int) $rxLine->item_id;
            if ($itemId <= 0 || empty($soldByItem[$itemId])) {
                continue;
            }

            $remaining = max(0, (float) $rxLine->quantity - (float) $rxLine->quantity_dispensed);
            if ($remaining <= 0) {
                continue;
            }

            $dispense = min($remaining, (float) $soldByItem[$itemId]);
            if ($dispense <= 0) {
                continue;
            }

            $rxLine->quantity_dispensed = (float) $rxLine->quantity_dispensed + $dispense;
            $rxLine->save();
            $soldByItem[$itemId] -= $dispense;

            $dispensedThisSale[] = [
                'item_id' => $itemId,
                'quantity' => round($dispense, 3),
                'prescription_line_id' => (int) $rxLine->id,
            ];
        }

        $rx->load('lines');
        $allDone = $rx->lines->isNotEmpty() && $rx->lines->every(function ($line) {
            return (float) $line->quantity_dispensed + 0.0001 >= (float) $line->quantity;
        });

        if ($allDone && $rx->status !== Prescription::STATUS_DISPENSED) {
            $rx->status = Prescription::STATUS_DISPENSED;
            $rx->save();
        } elseif ($rx->status === Prescription::STATUS_DRAFT) {
            $rx->status = Prescription::STATUS_ACTIVE;
            $rx->save();
        }

        return $dispensedThisSale;
    }

    public function saleDispensationSummary(int $prescriptionId, array $saleLines): ?array
    {
        if (! $this->isAvailable() || $prescriptionId <= 0) {
            return null;
        }

        $rx = Prescription::with(['lines.item'])->find($prescriptionId);
        if (! $rx) {
            return null;
        }

        $thisSaleByItem = [];
        $soldByItem = [];
        foreach ($saleLines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $baseQty = (float) ($line['quantity'] ?? 0) * (float) ($line['conversion_factor'] ?? 1);
            $soldByItem[$itemId] = ($soldByItem[$itemId] ?? 0) + $baseQty;

            $meta = is_array($line['metadata'] ?? null) ? $line['metadata'] : [];
            if (isset($meta['rx_dispensed_qty'])) {
                $thisSaleByItem[$itemId] = ($thisSaleByItem[$itemId] ?? 0) + (float) $meta['rx_dispensed_qty'];
            }
        }

        $lines = [];
        foreach ($rx->lines as $rxLine) {
            $itemId = (int) $rxLine->item_id;
            $prescribed = (float) $rxLine->quantity;
            $dispensed = (float) $rxLine->quantity_dispensed;
            $remaining = max(0, $prescribed - $dispensed);

            $thisSale = $thisSaleByItem[$itemId] ?? null;
            if ($thisSale === null) {
                $sold = (float) ($soldByItem[$itemId] ?? 0);
                $thisSale = $sold > 0 ? min($sold, $dispensed) : 0.0;
            }

            $lines[] = [
                'item_name' => $rxLine->item?->name ?? ('Article #'.$itemId),
                'prescribed' => $prescribed,
                'this_sale' => round((float) $thisSale, 3),
                'dispensed' => $dispensed,
                'remaining' => $remaining,
            ];
        }

        return [
            'number' => (string) $rx->number,
            'status' => (string) $rx->status,
            'status_label' => $rx->dispensationStatusLabel(),
            'lines' => $lines,
        ];
    }

    private function hasRemaining(Prescription $rx): bool
    {
        foreach ($rx->lines as $line) {
            if ($line->remaining_quantity > 0.0001) {
                return true;
            }
        }

        return false;
    }

    private function linesSummary(Prescription $rx): string
    {
        $parts = [];
        foreach ($rx->lines as $line) {
            $name = $line->item?->name ?? ('Article #'.$line->item_id);
            $parts[] = $name.': '.fmt_num_plain((float) $line->quantity_dispensed).'/'.fmt_num_plain((float) $line->quantity)
                .( $line->remaining_quantity > 0.0001 ? ' (reste '.fmt_num_plain($line->remaining_quantity).')' : '' );
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Aucune ligne';
    }

    /**
     * @return array{id:int,number:string,client_id:?int,client_name:?string,status_label:string,valid_until:?string,lines_summary:string,remaining_total:float,attachable:bool}
     */
    private function toSearchRow(Prescription $rx): array
    {
        $remaining = 0.0;
        foreach ($rx->lines as $line) {
            $remaining += $line->remaining_quantity;
        }

        $notExpired = ! $rx->valid_until || $rx->valid_until->gte(now()->startOfDay());
        $attachable = $rx->status === Prescription::STATUS_ACTIVE
            && $notExpired
            && $remaining > 0.0001;

        return [
            'id' => (int) $rx->id,
            'number' => (string) $rx->number,
            'client_id' => $rx->client_id ? (int) $rx->client_id : null,
            'client_name' => $rx->client?->name,
            'status_label' => $rx->dispensationStatusLabel(),
            'valid_until' => $rx->valid_until?->format('d/m/Y'),
            'lines_summary' => $this->linesSummary($rx),
            'remaining_total' => round($remaining, 3),
            'attachable' => $attachable,
        ];
    }

    private function generateNumber(): string
    {
        $prefix = 'RX-'.now()->format('Ymd').'-';
        $last = Prescription::where('number', 'like', $prefix.'%')->orderByDesc('id')->first();
        $seq = $last ? (int) substr($last->number, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
