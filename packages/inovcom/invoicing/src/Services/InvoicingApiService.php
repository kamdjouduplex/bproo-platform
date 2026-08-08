<?php

namespace InovCom\Invoicing\Services;

use App\Services\StoreContextService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Kernel\Contracts\InvoicingApi;

class InvoicingApiService implements InvoicingApi
{
    public function findInvoice(int $id): ?object
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return null;
        }

        return Invoice::on('tenant')->find($id);
    }

    public function findInvoiceByNumber(string $number): ?object
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return null;
        }

        return Invoice::on('tenant')
            ->where('invoice_number', $number)
            ->first();
    }

    public function invoiceExists(int $id): bool
    {
        return $this->findInvoice($id) !== null;
    }

    public function getBalance(int $invoiceId): float
    {
        $invoice = $this->findInvoice($invoiceId);

        return $invoice ? (float) $invoice->balance : 0.0;
    }

    public function getTotal(int $invoiceId): float
    {
        $invoice = $this->findInvoice($invoiceId);

        return $invoice ? (float) $invoice->total : 0.0;
    }

    public function getClientOutstandingBalance(int $clientId): float
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return 0.0;
        }

        $query = DB::connection('tenant')
            ->table('invoices')
            ->where('client_id', $clientId)
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01);

        $this->applyStoreFilter($query);

        return (float) $query->sum('balance');
    }

    public function getPeriodSummary(string $startDate, string $endDate): array
    {
        $empty = [
            'issued_count' => 0,
            'issued_total' => 0.0,
            'draft_count' => 0,
            'paid_count' => 0,
            'paid_total' => 0.0,
            'partial_count' => 0,
            'partial_balance' => 0.0,
            'outstanding_balance' => 0.0,
            'cancelled_count' => 0,
            'by_status' => [],
        ];

        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return $empty;
        }

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sum')
            ->selectRaw('COALESCE(SUM(balance), 0) as balance_sum')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r->status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
        }

        $issuedStatuses = ['issued', 'partial', 'paid'];
        $issuedCount = 0;
        $issuedTotal = 0.0;
        foreach ($issuedStatuses as $st) {
            $issuedCount += (int) ($byStatus[$st]['count'] ?? 0);
            $issuedTotal += (float) ($byStatus[$st]['total'] ?? 0);
        }

        $outstanding = (float) DB::connection('tenant')
            ->table('invoices')
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->sum('balance');

        $partialBalance = (float) DB::connection('tenant')
            ->table('invoices')
            ->where('status', 'partial')
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->sum('balance');

        return [
            'issued_count' => $issuedCount,
            'issued_total' => $issuedTotal,
            'draft_count' => (int) ($byStatus['draft']['count'] ?? 0),
            'paid_count' => (int) ($byStatus['paid']['count'] ?? 0),
            'paid_total' => (float) ($byStatus['paid']['total'] ?? 0),
            'partial_count' => (int) ($byStatus['partial']['count'] ?? 0),
            'partial_balance' => $partialBalance,
            'outstanding_balance' => $outstanding,
            'cancelled_count' => (int) ($byStatus['cancelled']['count'] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    public function getTopOutstandingInvoices(int $limit = 10): array
    {
        if (! Schema::connection('tenant')->hasTable('invoices')
            || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereIn('invoices.status', ['issued', 'partial'])
            ->where('invoices.balance', '>', 0.01)
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->select(
                'invoices.invoice_number',
                'clients.name as client_name',
                'invoices.balance',
                'invoices.due_date',
                'invoices.status'
            )
            ->orderByDesc('invoices.balance')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'invoice_number' => (string) $r->invoice_number,
            'client_name' => (string) $r->client_name,
            'balance' => (float) $r->balance,
            'due_date' => $r->due_date,
            'status' => (string) $r->status,
        ])->all();
    }

    public function getClientRevenueInPeriod(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('invoices')
            || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->select('clients.id as client_id', 'clients.name as client_name')
            ->selectRaw('COALESCE(SUM(invoices.total), 0) as invoice_revenue')
            ->selectRaw('COUNT(*) as invoice_count')
            ->groupBy('clients.id', 'clients.name')
            ->get();

        return $rows->map(fn ($r) => [
            'client_id' => (int) $r->client_id,
            'client_name' => (string) $r->client_name,
            'invoice_revenue' => (float) $r->invoice_revenue,
            'invoice_count' => (int) $r->invoice_count,
        ])->all();
    }

    public function getTaxBreakdown(string $startDate, string $endDate): array
    {
        $empty = [
            'ca_ht' => 0.0,
            'tva_total' => 0.0,
            'other_taxes_total' => 0.0,
            'ca_ttc' => 0.0,
            'by_tax' => [],
        ];

        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return $empty;
        }

        $issuedStatuses = ['issued', 'partial', 'paid'];

        $invoiceAgg = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', $issuedStatuses)
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(subtotal, 0) - COALESCE(discount_amount, 0), 0)), 0) as ca_ht')
            ->selectRaw('COALESCE(SUM(total), 0) as ca_ttc')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_fallback')
            ->first();

        $caHt = (float) ($invoiceAgg->ca_ht ?? 0);
        $caTtc = (float) ($invoiceAgg->ca_ttc ?? 0);
        $taxFallback = (float) ($invoiceAgg->tax_fallback ?? 0);

        $tvaTotal = 0.0;
        $otherTaxes = 0.0;
        $byTax = [];

        if (Schema::connection('tenant')->hasTable('invoice_tax_lines')) {
            $taxQuery = DB::connection('tenant')
                ->table('invoice_tax_lines as tl')
                ->join('invoices as i', 'i.id', '=', 'tl.invoice_id')
                ->whereBetween('i.invoice_date', [$startDate, $endDate])
                ->whereIn('i.status', $issuedStatuses)
                ->when(true, function ($q) {
                    $storeId = app(StoreContextService::class)->currentStoreId();
                    if (
                        $storeId
                        && Schema::connection('tenant')->hasColumn('invoices', 'store_id')
                    ) {
                        $q->where('i.store_id', $storeId);
                    }

                    return $q;
                });

            if (Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_effect')) {
                $taxQuery->where(function ($q) {
                    $q->whereNull('tl.tax_effect')
                        ->orWhere('tl.tax_effect', '<>', 'subtract');
                });
            }

            $taxRows = $taxQuery
                ->select('tl.tax_name')
                ->selectRaw('COALESCE(SUM(tl.tax_amount), 0) as amount')
                ->groupBy('tl.tax_name')
                ->get();

            foreach ($taxRows as $row) {
                $amount = (float) $row->amount;
                if ($amount <= 0) {
                    continue;
                }
                $name = (string) $row->tax_name;
                $byTax[] = ['name' => $name, 'amount' => $amount];
                if (str_contains(mb_strtoupper($name), 'TVA')) {
                    $tvaTotal += $amount;
                } else {
                    $otherTaxes += $amount;
                }
            }
        }

        if ($tvaTotal <= 0 && $otherTaxes <= 0 && $taxFallback > 0) {
            $tvaTotal = $taxFallback;
            $byTax[] = ['name' => 'TVA / taxes', 'amount' => $taxFallback];
        }

        return [
            'ca_ht' => round($caHt, 2),
            'tva_total' => round($tvaTotal, 2),
            'other_taxes_total' => round($otherTaxes, 2),
            'ca_ttc' => round($caTtc, 2),
            'by_tax' => $byTax,
        ];
    }

    public function getPaymentsTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('invoice_payments')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')
            ->table('invoice_payments')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');
    }

    public function getPaymentsByMethod(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('invoice_payments')) {
            return [];
        }

        $labels = [
            'cash' => 'Espèces',
            'check' => 'Chèque',
            'bank_transfer' => 'Virement',
            'mobile_money' => 'Mobile Money',
            'other' => 'Autre',
        ];

        $rows = DB::connection('tenant')
            ->table('invoice_payments')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->select('payment_method')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'method' => (string) $r->payment_method,
            'method_label' => $labels[$r->payment_method] ?? (string) $r->payment_method,
            'total' => (float) $r->total,
            'count' => (int) $r->cnt,
        ])->all();
    }

    public function getDeliveriesSummary(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('delivery_notes')) {
            return ['confirmed_count' => 0, 'draft_count' => 0];
        }

        $confirmed = (int) DB::connection('tenant')
            ->table('delivery_notes')
            ->where('status', 'confirmed')
            ->whereBetween('delivery_date', [$startDate, $endDate])
            ->when(true, function ($q) {
                $storeId = app(StoreContextService::class)->currentStoreId();
                if (
                    $storeId
                    && Schema::connection('tenant')->hasColumn('delivery_notes', 'store_id')
                ) {
                    $q->where('delivery_notes.store_id', $storeId);
                }

                return $q;
            })
            ->count();

        $draft = (int) DB::connection('tenant')
            ->table('delivery_notes')
            ->where('status', 'draft')
            ->whereBetween('delivery_date', [$startDate, $endDate])
            ->when(true, function ($q) {
                $storeId = app(StoreContextService::class)->currentStoreId();
                if (
                    $storeId
                    && Schema::connection('tenant')->hasColumn('delivery_notes', 'store_id')
                ) {
                    $q->where('delivery_notes.store_id', $storeId);
                }

                return $q;
            })
            ->count();

        return ['confirmed_count' => $confirmed, 'draft_count' => $draft];
    }

    public function listIssuedInvoices(
        string $startDate,
        string $endDate,
        ?int $clientId = null,
        int $limit = 100
    ): array {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return [];
        }

        $query = DB::connection('tenant')
            ->table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->when($clientId, fn ($q) => $q->where('invoices.client_id', $clientId))
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->orderByDesc('invoices.invoice_date')
            ->limit($limit);

        $rows = $query->get([
            'invoices.invoice_number',
            'invoices.invoice_date',
            'invoices.status',
            'invoices.subtotal',
            'invoices.discount_amount',
            'invoices.tax_amount',
            'invoices.total',
            'invoices.balance',
            'clients.name as client_name',
        ]);

        return $rows->map(fn ($i) => [
            'invoice_number' => (string) $i->invoice_number,
            'invoice_date' => $i->invoice_date,
            'status' => (string) $i->status,
            'client_name' => $i->client_name,
            'subtotal' => (float) $i->subtotal,
            'discount_amount' => (float) $i->discount_amount,
            'tax_amount' => (float) $i->tax_amount,
            'total' => (float) $i->total,
            'balance' => (float) $i->balance,
        ])->all();
    }

    public function countConvertedQuotesInPeriod(string $startDate, string $endDate): int
    {
        if (! Schema::connection('tenant')->hasTable('invoices')
            || ! Schema::connection('tenant')->hasColumn('invoices', 'quotation_id')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereNotNull('quotation_id')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->count(DB::raw('DISTINCT quotation_id'));
    }

    private function applyStoreFilter(Builder $query): Builder
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        if (
            $storeId
            && Schema::connection('tenant')->hasColumn('invoices', 'store_id')
        ) {
            $query->where('invoices.store_id', $storeId);
        }

        return $query;
    }
}
