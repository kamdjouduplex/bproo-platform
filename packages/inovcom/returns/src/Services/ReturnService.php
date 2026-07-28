<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\DB;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Returns\Enums\ItemCondition;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Enums\ReturnType;
use InovCom\Returns\Models\ReturnApprovalLog;
use InovCom\Returns\Models\ReturnComment;
use InovCom\Returns\Models\ReturnRequest;
use InovCom\Returns\Models\ReturnStatusHistory;

class ReturnService
{
    public function __construct(
        private ReturnNumberGenerator $numbers,
        private ReturnStockService $stock,
        private AuditLogger $audit,
    ) {
    }

    /**
     * Quantités retournables par ligne de facture.
     *
     * @return array<int, array{line: \InovCom\Invoicing\Models\InvoiceLine, returnable: float, already: float}>
     */
    public function returnableForInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');

        $already = $this->alreadyReturnedByLine($invoice->id);
        $out = [];

        foreach ($invoice->lines as $line) {
            $done = (float) ($already[$line->id] ?? 0);
            $out[$line->id] = [
                'line' => $line,
                'returnable' => max(0, round((float) $line->quantity - $done, 3)),
                'already' => $done,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, float> invoice_line_id => quantité déjà retournée (retours actifs)
     */
    public function alreadyReturnedByLine(int $invoiceId): array
    {
        return DB::connection('tenant')->table('return_items as ri')
            ->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->where('r.source_type', 'invoice')
            ->where('r.source_id', $invoiceId)
            ->whereNull('r.deleted_at')
            ->whereNotIn('r.status', ['draft', 'rejected', 'cancelled'])
            ->whereNotNull('ri.source_line_id')
            ->groupBy('ri.source_line_id')
            ->selectRaw('ri.source_line_id, SUM(ri.quantity) as qty')
            ->pluck('qty', 'ri.source_line_id')
            ->map(fn ($q) => (float) $q)
            ->toArray();
    }

    private function unitValue(\InovCom\Invoicing\Models\InvoiceLine $line): float
    {
        if ((float) $line->quantity > 0) {
            return round((float) $line->line_total / (float) $line->quantity, 4);
        }

        return (float) ($line->unit_price_net ?? $line->unit_price);
    }

    /**
     * Crée une demande de retour (brouillon) à partir d'une facture.
     *
     * @param array<int, array{quantity: float, reason_id?: int|null}> $lines  invoice_line_id => data
     * @param array<string, mixed> $data  reason_id, notes, return_date, type
     */
    public function createFromInvoice(int $invoiceId, array $lines, array $data = [], ?int $userId = null): ReturnRequest
    {
        return DB::connection('tenant')->transaction(function () use ($invoiceId, $lines, $data, $userId) {
            $invoice = Invoice::on('tenant')->with('lines')->findOrFail($invoiceId);
            $returnable = $this->returnableForInvoice($invoice);

            $selected = [];
            foreach ($lines as $lineId => $row) {
                $qty = round((float) ($row['quantity'] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }

                $info = $returnable[$lineId] ?? null;
                if (! $info) {
                    throw new \RuntimeException('Ligne de facture inconnue.');
                }

                if ($qty > $info['returnable'] + 0.001) {
                    throw new \RuntimeException(
                        'Quantité retournée (' . $qty . ') supérieure au retournable (' . $info['returnable'] . ') pour « ' . $info['line']->item_name . ' ».'
                    );
                }

                $selected[$lineId] = [
                    'quantity' => $qty,
                    'reason_id' => $row['reason_id'] ?? ($data['reason_id'] ?? null),
                    'line' => $info['line'],
                ];
            }

            if ($selected === []) {
                throw new \RuntimeException('Sélectionnez au moins une ligne à retourner.');
            }

            $subtotal = 0.0;
            foreach ($selected as $s) {
                $subtotal += round($this->unitValue($s['line']) * $s['quantity'], 2);
            }
            $subtotal = round($subtotal, 2);

            $totalQtyReturned = array_sum(array_map(fn ($s) => $s['quantity'], $selected));
            $totalQtyInvoice = (float) $invoice->lines->sum('quantity');
            $isTotal = abs($totalQtyReturned - $totalQtyInvoice) < 0.001
                && count($selected) === $invoice->lines->count();

            $type = ! empty($data['type'])
                ? ReturnType::from($data['type'])
                : ($isTotal ? ReturnType::Total : ReturnType::Partial);

            $return = ReturnRequest::create([
                'return_number' => $this->numbers->nextReturnNumber(),
                'client_id' => $invoice->client_id,
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'source_number' => $invoice->invoice_number,
                'status' => ReturnStatus::Draft->value,
                'type' => $type->value,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'subtotal_amount' => $subtotal,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'reason_id' => $data['reason_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'store_id' => $invoice->store_id,
                'created_by' => $userId ?? auth('tenant')->id(),
            ]);

            foreach ($selected as $lineId => $s) {
                $line = $s['line'];
                $unit = $this->unitValue($line);
                $return->items()->create([
                    'source_line_id' => $lineId,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item_name,
                    'item_sku' => $line->item_sku,
                    'quantity' => $s['quantity'],
                    'unit_price' => $line->unit_price,
                    'line_discount' => $line->line_discount ?? 0,
                    'tax_rate' => null,
                    'line_total' => round($unit * $s['quantity'], 2),
                    'reason_id' => $s['reason_id'],
                    'restock' => true,
                ]);
            }

            $this->recordHistory($return, null, ReturnStatus::Draft, 'Création de la demande de retour', $userId);
            $this->audit->log('return', $return->id, 'created', ['total' => $subtotal], $userId);

            return $return->fresh(['items']);
        });
    }

    /**
     * Transition de statut générique avec garde + historisation.
     */
    public function changeStatus(ReturnRequest $return, ReturnStatus $target, ?string $note = null, ?int $userId = null): ReturnRequest
    {
        $current = $return->status;
        if (! $current instanceof ReturnStatus) {
            $current = ReturnStatus::from((string) $current);
        }

        if ($current === $target) {
            return $return;
        }

        if (! $current->canTransitionTo($target)) {
            throw new \RuntimeException(
                'Transition non autorisée : ' . $current->label() . ' → ' . $target->label() . '.'
            );
        }

        $return->status = $target->value;
        $this->stampTransition($return, $target, $userId);
        $return->save();

        $this->recordHistory($return, $current, $target, $note, $userId);
        $this->audit->log('return', $return->id, 'status_changed', [
            'from' => $current->value,
            'to' => $target->value,
        ], $userId);

        return $return;
    }

    private function stampTransition(ReturnRequest $return, ReturnStatus $target, ?int $userId): void
    {
        $uid = $userId ?? auth('tenant')->id();

        match ($target) {
            ReturnStatus::Approved => [$return->approved_by = $uid, $return->approved_at = now()],
            ReturnStatus::Received => [$return->received_by = $uid, $return->received_at = now()],
            ReturnStatus::Inspected => [$return->inspected_by = $uid, $return->inspected_at = now()],
            ReturnStatus::Closed => [$return->closed_at = now()],
            default => null,
        };
    }

    public function submit(ReturnRequest $return, ?int $userId = null): ReturnRequest
    {
        return $this->changeStatus($return, ReturnStatus::Requested, 'Demande soumise', $userId);
    }

    public function approve(ReturnRequest $return, ?string $note = null, ?int $userId = null): ReturnRequest
    {
        $return = $this->changeStatus($return, ReturnStatus::Approved, $note ?? 'Demande approuvée', $userId);
        $this->logApproval($return, 'approved', $note, $userId);

        return $return;
    }

    public function reject(ReturnRequest $return, string $reason, ?int $userId = null): ReturnRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Indiquez le motif de refus.');
        }

        $return = $this->changeStatus($return, ReturnStatus::Rejected, $reason, $userId);
        $this->logApproval($return, 'rejected', $reason, $userId);

        return $return;
    }

    public function receive(ReturnRequest $return, ?string $note = null, ?int $userId = null): ReturnRequest
    {
        return $this->changeStatus($return, ReturnStatus::Received, $note ?? 'Marchandise réceptionnée', $userId);
    }

    public function cancel(ReturnRequest $return, ?string $reason = null, ?int $userId = null): ReturnRequest
    {
        return $this->changeStatus($return, ReturnStatus::Cancelled, $reason ?? 'Retour annulé', $userId);
    }

    /**
     * Contrôle qualité : fixe l'état par ligne, passe en INSPECTED et réintègre le stock.
     *
     * @param array<int, array{condition?: string, restock?: bool}> $conditions return_item_id => data
     */
    public function inspect(ReturnRequest $return, array $conditions, ?int $userId = null): ReturnRequest
    {
        return DB::connection('tenant')->transaction(function () use ($return, $conditions, $userId) {
            $return->loadMissing('items');

            foreach ($return->items as $item) {
                $data = $conditions[$item->id] ?? [];
                $condition = $data['condition'] ?? ItemCondition::Resellable->value;
                $item->condition = $condition;
                $item->restock = (bool) ($data['restock'] ?? ($condition === ItemCondition::Resellable->value));
                $item->save();
            }

            $return = $this->changeStatus($return, ReturnStatus::Inspected, 'Contrôle qualité effectué', $userId);

            $restocked = $this->stock->reintegrate($return, $userId);
            $this->audit->log('return', $return->id, 'stock_reintegrated', ['lines' => $restocked], $userId);

            return $return->fresh(['items']);
        });
    }

    public function addComment(ReturnRequest $return, string $body, ?int $userId = null): ReturnComment
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Le commentaire ne peut pas être vide.');
        }

        return ReturnComment::create([
            'return_id' => $return->id,
            'body' => $body,
            'author_id' => $userId ?? auth('tenant')->id(),
        ]);
    }

    private function recordHistory(ReturnRequest $return, ?ReturnStatus $from, ReturnStatus $to, ?string $note, ?int $userId): void
    {
        ReturnStatusHistory::create([
            'return_id' => $return->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'performed_by' => $userId ?? auth('tenant')->id(),
            'performed_at' => now(),
        ]);
    }

    private function logApproval(ReturnRequest $return, string $decision, ?string $reason, ?int $userId): void
    {
        ReturnApprovalLog::create([
            'approvable_type' => 'return',
            'approvable_id' => $return->id,
            'decision' => $decision,
            'reason' => $reason,
            'decided_by' => $userId ?? auth('tenant')->id(),
            'decided_at' => now(),
        ]);
    }
}
