<?php

namespace InovCom\Debts\Services;

use InovCom\Clients\Models\Client;
use InovCom\Debts\Models\Debt;
use InovCom\Debts\Models\DebtPayment;
use InovCom\Debts\Models\DebtSchedule;
use InovCom\Kernel\Contracts\SalesApi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebtsService
{
    public function createDebt(array $data, ?int $userId = null): Debt
    {
        $data['reference'] = $this->generateDebtReference();
        $data['balance'] = $data['total_amount'];
        $data['opened_at'] = $data['opened_at'] ?? now()->toDateString();
        $data['created_by'] = $userId ?? auth('tenant')->id();
        $data['status'] = 'open';
        if (Debt::supportsValidationWorkflow()) {
            $data['is_validated'] = false;
            $data['validated_by'] = null;
            $data['validated_at'] = null;
        }

        $debt = Debt::create($data);

        // Sync client balance: add to what they owe
        $client = Client::find($debt->client_id);
        if ($client) {
            $client->current_balance = (float) $client->current_balance + (float) $debt->total_amount;
            $client->save();
        }

        return $debt->fresh();
    }

    public function recordPayment(
        int $debtId,
        float $amount,
        string $paymentDate,
        string $paymentMethod = 'cash',
        ?string $notes = null,
        ?string $externalRef = null,
        ?int $userId = null
    ): DebtPayment {
        $debt = Debt::findOrFail($debtId);

        if (Debt::supportsValidationWorkflow() && !(bool) $debt->is_validated) {
            throw new \Exception('Cette dette doit être validée avant enregistrement des paiements.');
        }

        if ($amount <= 0) {
            throw new \Exception('Le montant du paiement doit être positif.');
        }

        if ($amount > (float) $debt->balance) {
            throw new \Exception('Le montant dépasse le solde restant (' . fmt_money($debt->balance) . ' FCFA).');
        }

        $payment = DB::connection('tenant')->transaction(function () use (
            $debt,
            $debtId,
            $amount,
            $paymentDate,
            $paymentMethod,
            $notes,
            $externalRef,
            $userId
        ) {
            $payment = DebtPayment::create([
                'reference' => $this->generatePaymentReference(),
                'debt_id' => $debtId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'external_reference' => $externalRef,
                'notes' => $notes,
                'created_by' => $userId ?? auth('tenant')->id(),
            ]);

            $debt->balance = (float) $debt->balance - $amount;
            $debt->updateStatus();
            $debt->save();

            $client = Client::find($debt->client_id);
            if ($client) {
                $client->current_balance = (float) $client->current_balance - $amount;
                $client->save();
            }

            $this->syncLinkedSalePayments($debt->fresh(['payments']));

            return $payment;
        });

        // Auto-capture caisse : seuls les règlements espèces alimentent le tiroir.
        if ($paymentMethod === 'cash') {
            \App\Support\CashLedger::recordIn(
                \App\Support\CashLedger::DEBT_PAYMENT_CASH_IN,
                $amount,
                'Encaissement dette ' . $debt->reference,
                'debt',
                DebtPayment::class,
                (int) $payment->id,
                $payment->reference,
                ['debt_id' => $debt->id, 'client_id' => $debt->client_id],
                $userId
            );
        }

        return $payment->fresh();
    }

    public function getTotalOutstanding(?int $clientId = null): float
    {
        $query = Debt::whereIn('status', ['open', 'partial', 'overdue']);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return (float) $query->sum('balance');
    }

    public function getDebtsByClient(int $clientId): Collection
    {
        return Debt::where('client_id', $clientId)
            ->with(['payments', 'creator'])
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Open a debt from a POS credit sale. Idempotent on sale_id.
     * Auto-validated: the sale itself already granted the credit.
     */
    public function createFromCreditSale(
        int $clientId,
        float $amount,
        int $saleId,
        string $saleNumber,
        ?int $userId = null,
        ?string $openedAt = null
    ): ?Debt {
        if (! Schema::connection('tenant')->hasTable('debts')) {
            return null;
        }

        $amount = round($amount, 2);
        if ($clientId <= 0 || $saleId <= 0 || $amount <= 0.01) {
            return null;
        }

        $existing = Debt::query()->where('sale_id', $saleId)->first();
        if ($existing) {
            return $existing;
        }

        $debt = $this->createDebt([
            'client_id' => $clientId,
            'total_amount' => $amount,
            'opened_at' => $openedAt ?: now()->toDateString(),
            'due_date' => null,
            'description' => 'Vente à crédit '.$saleNumber,
            'sale_id' => $saleId,
        ], $userId);

        if (Debt::supportsValidationWorkflow() && ! $debt->is_validated) {
            $debt->is_validated = true;
            $debt->validated_by = $userId ?? auth('tenant')->id();
            $debt->validated_at = now();
            $debt->save();
        }

        return $debt->fresh();
    }

    /**
     * Reduce the debt opened by a credit sale when that credit is refunded.
     * Also mirrors the client current_balance decrement.
     */
    public function applyCreditSaleReturn(int $saleId, float $amount): bool
    {
        if (! Schema::connection('tenant')->hasTable('debts')) {
            return false;
        }

        $amount = round($amount, 2);
        if ($saleId <= 0 || $amount <= 0.01) {
            return false;
        }

        $debt = Debt::query()->where('sale_id', $saleId)->first();
        if (! $debt) {
            return false;
        }

        $reduceBalance = min($amount, (float) $debt->balance);
        $debt->balance = round((float) $debt->balance - $reduceBalance, 2);
        $debt->total_amount = round(max((float) $debt->total_amount - $amount, (float) $debt->balance), 2);
        $debt->updateStatus();

        $client = Client::find($debt->client_id);
        if ($client) {
            $client->current_balance = max(0, (float) $client->current_balance - $amount);
            $client->save();
        }

        $this->syncLinkedSalePayments($debt->fresh(['payments']));

        return true;
    }

    /**
     * @param  array<int, int>  $saleIds
     */
    public function syncLinkedSales(array $saleIds): void
    {
        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds))));
        if ($saleIds === [] || ! Schema::connection('tenant')->hasColumn('debts', 'sale_id')) {
            return;
        }

        $debts = Debt::query()
            ->whereIn('sale_id', $saleIds)
            ->with('payments')
            ->get();

        foreach ($debts as $debt) {
            $this->syncLinkedSalePayments($debt);
        }
    }

    /**
     * Convert the POS "credit" line into collected payments so the sales list
     * no longer shows "À crédit" after the linked debt is settled.
     */
    public function syncLinkedSalePayments(Debt $debt): void
    {
        if (! $debt->sale_id || ! app()->bound(SalesApi::class)) {
            return;
        }

        $api = app(SalesApi::class);
        $collections = $debt->payments
            ->sortBy('id')
            ->map(static fn (DebtPayment $payment) => [
                'amount' => (float) $payment->amount,
                'method' => (string) $payment->payment_method,
                'reference' => $payment->external_reference,
                'user_id' => $payment->created_by,
            ])
            ->values()
            ->all();

        $api->syncLinkedDebtCollections(
            (int) $debt->sale_id,
            (float) $debt->balance,
            (string) $debt->reference,
            $collections
        );
    }

    public function addSchedule(int $debtId, string $dueDate, float $amountDue, ?string $notes = null): DebtSchedule
    {
        $debt = Debt::findOrFail($debtId);

        return DebtSchedule::create([
            'debt_id' => $debtId,
            'due_date' => $dueDate,
            'amount_due' => $amountDue,
            'amount_paid' => 0,
            'status' => 'pending',
            'notes' => $notes,
        ]);
    }

    private function generateDebtReference(): string
    {
        $year = now()->year;
        $last = Debt::whereYear('created_at', $year)->orderBy('id', 'desc')->get(['reference']);
        $next = 1;

        foreach ($last as $debt) {
            if (preg_match('/^DBT-\d{4}-(\d{6})$/', (string) $debt->reference, $matches)) {
                $next = max($next, ((int) $matches[1]) + 1);
                break;
            }
        }

        return 'DBT-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function generatePaymentReference(): string
    {
        $year = now()->year;
        $last = DebtPayment::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $next = $last ? ((int) preg_replace('/[^0-9]/', '', $last->reference)) + 1 : 1;
        return 'PAY-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
