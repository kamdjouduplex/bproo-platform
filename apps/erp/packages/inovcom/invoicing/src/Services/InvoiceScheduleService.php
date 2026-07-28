<?php

namespace InovCom\Invoicing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Models\InvoiceSchedule;

class InvoiceScheduleService
{
    public function tableExists(): bool
    {
        return Schema::connection('tenant')->hasTable('invoice_schedules');
    }

    /**
     * Génère un échéancier mensuel sur le solde restant de la facture.
     * Remplace un échéancier existant uniquement s'il n'a encore rien d'imputé.
     *
     * @return \Illuminate\Support\Collection<int, InvoiceSchedule>
     */
    public function generateMonthlySchedule(
        Invoice $invoice,
        int $months,
        string $firstDueDate,
        bool $replace = false
    ) {
        if (!$this->tableExists()) {
            throw new \RuntimeException('La table des échéanciers n\'est pas installée. Lancez la migration tenant.');
        }

        if ($invoice->isCancellationDocument() || $invoice->isSuperseded()) {
            throw new \RuntimeException('Cette facture ne peut pas être échelonnée.');
        }

        if (!in_array($invoice->status, ['issued', 'partial'], true)) {
            throw new \RuntimeException('Seules les factures émises (ou partiellement payées) peuvent être échelonnées.');
        }

        if ($months < 2 || $months > 36) {
            throw new \InvalidArgumentException('Le nombre de mois doit être entre 2 et 36.');
        }

        $amountToSchedule = round((float) $invoice->balance, 2);
        if ($amountToSchedule <= 0.01) {
            throw new \RuntimeException('Aucun solde à échelonner sur cette facture.');
        }

        $firstDue = Carbon::parse($firstDueDate)->startOfDay();

        return DB::connection('tenant')->transaction(function () use (
            $invoice,
            $months,
            $firstDue,
            $amountToSchedule,
            $replace
        ) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $existing = InvoiceSchedule::query()
                ->where('invoice_id', $invoice->id)
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                $hasAllocated = $existing->contains(fn (InvoiceSchedule $s) => (float) $s->amount_paid > 0.01);
                if ($hasAllocated) {
                    throw new \RuntimeException(
                        'Impossible de régénérer : des paiements sont déjà imputés sur l\'échéancier.'
                    );
                }
                if (!$replace) {
                    throw new \RuntimeException('Un échéancier existe déjà. Cochez « Remplacer » pour le régénérer.');
                }
                InvoiceSchedule::query()->where('invoice_id', $invoice->id)->delete();
            }

            $base = round($amountToSchedule / $months, 2);
            $allocated = 0.0;
            $created = collect();

            for ($i = 1; $i <= $months; $i++) {
                $dueAmount = $i === $months
                    ? round($amountToSchedule - $allocated, 2)
                    : $base;
                $allocated = round($allocated + $dueAmount, 2);

                $created->push(InvoiceSchedule::create([
                    'invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'due_date' => $firstDue->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                    'amount_due' => $dueAmount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'notes' => 'Échéance ' . $i . '/' . $months,
                ]));
            }

            $this->reallocateFromInvoicePaid($invoice->fresh());
            $this->syncInvoiceDueDate($invoice->fresh());

            return $created;
        });
    }

    public function clearSchedule(Invoice $invoice, bool $force = false): void
    {
        if (!$this->tableExists()) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($invoice, $force) {
            $rows = InvoiceSchedule::query()
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $hasAllocated = $rows->contains(fn (InvoiceSchedule $s) => (float) $s->amount_paid > 0.01);
            if ($hasAllocated && !$force) {
                throw new \RuntimeException(
                    'Impossible de supprimer : des paiements sont déjà imputés sur l\'échéancier.'
                );
            }

            InvoiceSchedule::query()->where('invoice_id', $invoice->id)->delete();
        });
    }

    /**
     * Réimpute le montant total encaissé sur la facture vers les échéances (plus anciennes d'abord).
     */
    public function reallocateFromInvoicePaid(Invoice $invoice): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $schedules = InvoiceSchedule::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->orderBy('id')
            ->get();

        if ($schedules->isEmpty()) {
            return;
        }

        $remainingPaid = round((float) $invoice->amount_paid, 2);
        // Si l'échéancier a été créé sur le solde restant après un acompte,
        // on n'impute que ce qui dépasse (total - somme des amount_due)… Non :
        // à la création, amount_due couvre le balance au moment T.
        // Les acomptes antérieurs ne sont PAS dans amount_paid des schedules.
        // Donc on impute seulement le paid depuis la création — mais on n'a pas ce snapshot.
        //
        // Approche V1 : à la génération, amount_due = balance (reste). amount_paid invoice
        // peut déjà être > 0. On impute min(invoice.amount_paid, sum(amount_due)) en partant
        // du fait que l'échéancier représente le reste à payer : donc on utilise
        // le solde « déjà payé sur l'échéancier » = max(0, amount_paid - (total - sum(amount_due)))
        // = max(0, amount_paid - (total - scheduledTotal))
        // = amount_paid - (total - scheduledTotal) when positive
        //
        // scheduledTotal ≈ balance at generation = total - amount_paid_at_gen
        // paid_on_schedule = amount_paid_now - amount_paid_at_gen = amount_paid_now - (total - scheduledTotal)
        $scheduledTotal = round((float) $schedules->sum('amount_due'), 2);
        $paidBeforeSchedule = round((float) $invoice->total - $scheduledTotal, 2);
        $toAllocate = max(0, round((float) $invoice->amount_paid - $paidBeforeSchedule, 2));
        // Cap at scheduled total
        $toAllocate = min($toAllocate, $scheduledTotal);

        $today = Carbon::today();

        foreach ($schedules as $schedule) {
            $due = round((float) $schedule->amount_due, 2);
            $apply = min($due, $toAllocate);
            $schedule->amount_paid = $apply;
            $toAllocate = round($toAllocate - $apply, 2);

            $remaining = round($due - $apply, 2);
            if ($remaining <= 0.01) {
                $schedule->status = 'paid';
                $schedule->amount_paid = $due;
            } elseif ($apply > 0.01) {
                $schedule->status = $schedule->due_date->lt($today) ? 'overdue' : 'partial';
            } else {
                $schedule->status = $schedule->due_date->lt($today) ? 'overdue' : 'pending';
            }

            $schedule->save();
        }

        $this->syncInvoiceDueDate($invoice);
    }

    /**
     * Aligne invoices.due_date sur la prochaine échéance non soldée (pour relances existantes).
     */
    public function syncInvoiceDueDate(Invoice $invoice): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $next = InvoiceSchedule::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->first();

        if (!$next) {
            return;
        }

        if ($invoice->due_date?->toDateString() !== $next->due_date->toDateString()) {
            $invoice->due_date = $next->due_date->toDateString();
            $invoice->save();
        }
    }

    /**
     * Montant actuellement dû (échéances en retard ou arrivées à échéance).
     */
    public function amountCurrentlyDue(Invoice $invoice): float
    {
        if (!$this->tableExists()) {
            return round((float) $invoice->balance, 2);
        }

        $today = Carbon::today()->toDateString();

        $sum = (float) InvoiceSchedule::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<=', $today)
            ->get()
            ->sum(fn (InvoiceSchedule $s) => $s->remaining());

        if ($sum <= 0.01) {
            // Pas encore d'échéance due : 0 pour « à payer maintenant », le solde reste sur la facture.
            $hasSchedule = InvoiceSchedule::query()->where('invoice_id', $invoice->id)->exists();
            return $hasSchedule ? 0.0 : round((float) $invoice->balance, 2);
        }

        return round($sum, 2);
    }

    public function refreshOverdueStatuses(Invoice $invoice): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $today = Carbon::today();

        InvoiceSchedule::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get()
            ->each(function (InvoiceSchedule $schedule) use ($today) {
                $remaining = $schedule->remaining();
                if ($remaining <= 0.01) {
                    $schedule->status = 'paid';
                } elseif ($schedule->due_date->lt($today)) {
                    $schedule->status = (float) $schedule->amount_paid > 0.01 ? 'overdue' : 'overdue';
                } elseif ((float) $schedule->amount_paid > 0.01) {
                    $schedule->status = 'partial';
                } else {
                    $schedule->status = 'pending';
                }
                $schedule->save();
            });
    }
}
