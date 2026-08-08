<?php

namespace InovCom\Facturation\Adapters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Support\PaymentMethodLabels;
use InovCom\Kernel\Contracts\InvoicingApi;

class BatInvoicingApiAdapter implements InvoicingApi
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
            ->where('code', $number)
            ->first();
    }

    public function invoiceExists(int $id): bool
    {
        return $this->findInvoice($id) !== null;
    }

    public function getBalance(int $invoiceId): float
    {
        $invoice = $this->findInvoice($invoiceId);
        if (! $invoice) {
            return 0.0;
        }

        if (Schema::connection('tenant')->hasColumn('invoices', 'amount_due')) {
            return (float) $invoice->amount_due;
        }

        $total = Schema::connection('tenant')->hasColumn('invoices', 'total_ttc')
            ? (float) $invoice->total_ttc
            : 0.0;
        $paid = Schema::connection('tenant')->hasColumn('invoices', 'amount_paid')
            ? (float) $invoice->amount_paid
            : 0.0;

        return max(0.0, $total - $paid);
    }

    public function getTotal(int $invoiceId): float
    {
        $invoice = $this->findInvoice($invoiceId);
        if (! $invoice) {
            return 0.0;
        }

        return Schema::connection('tenant')->hasColumn('invoices', 'total_ttc')
            ? (float) $invoice->total_ttc
            : 0.0;
    }

    public function getClientOutstandingBalance(int $clientId): float
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return 0.0;
        }

        $balanceCol = $this->balanceColumn();
        $query = DB::connection('tenant')
            ->table('invoices')
            ->where('client_id', $clientId)
            ->whereIn('status', ['sent', 'overdue'])
            ->where($balanceCol, '>', 0.01);

        return (float) $query->sum($balanceCol);
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

        $dateCol = $this->dateColumn();
        $totalCol = $this->totalColumn();
        $balanceCol = $this->balanceColumn();

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween($dateCol, [$startDate, $endDate])
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw("COALESCE(SUM({$totalCol}), 0) as total_sum")
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r->status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
        }

        $issuedStatuses = ['sent', 'overdue', 'paid'];
        $issuedCount = 0;
        $issuedTotal = 0.0;
        foreach ($issuedStatuses as $st) {
            $issuedCount += (int) ($byStatus[$st]['count'] ?? 0);
            $issuedTotal += (float) ($byStatus[$st]['total'] ?? 0);
        }

        $outstanding = (float) DB::connection('tenant')
            ->table('invoices')
            ->whereIn('status', ['sent', 'overdue'])
            ->where($balanceCol, '>', 0.01)
            ->sum($balanceCol);

        $partialCount = 0;
        $partialBalance = 0.0;
        if (Schema::connection('tenant')->hasColumn('invoices', 'amount_paid')) {
            $partialRows = DB::connection('tenant')
                ->table('invoices')
                ->whereIn('status', ['sent', 'overdue'])
                ->where('amount_paid', '>', 0.01)
                ->where($balanceCol, '>', 0.01)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw("COALESCE(SUM({$balanceCol}), 0) as bal")
                ->first();
            $partialCount = (int) ($partialRows->cnt ?? 0);
            $partialBalance = (float) ($partialRows->bal ?? 0);
        }

        return [
            'issued_count' => $issuedCount,
            'issued_total' => $issuedTotal,
            'draft_count' => (int) ($byStatus['draft']['count'] ?? 0),
            'paid_count' => (int) ($byStatus['paid']['count'] ?? 0),
            'paid_total' => (float) ($byStatus['paid']['total'] ?? 0),
            'partial_count' => $partialCount,
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

        $balanceCol = $this->balanceColumn();

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereIn('invoices.status', ['sent', 'overdue'])
            ->where("invoices.{$balanceCol}", '>', 0.01)
            ->select(
                'invoices.code as invoice_number',
                'clients.name as client_name',
                DB::raw("invoices.{$balanceCol} as balance"),
                'invoices.due_date',
                'invoices.status'
            )
            ->orderByDesc("invoices.{$balanceCol}")
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'invoice_number' => (string) $r->invoice_number,
            'client_name' => (string) ($r->client_name ?? ''),
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

        $dateCol = $this->dateColumn();
        $totalCol = $this->totalColumn();

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereBetween("invoices.{$dateCol}", [$startDate, $endDate])
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->select('clients.id as client_id', 'clients.name as client_name')
            ->selectRaw("COALESCE(SUM(invoices.{$totalCol}), 0) as invoice_revenue")
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

        $dateCol = $this->dateColumn();
        $issuedStatuses = ['sent', 'overdue', 'paid'];

        $htCol = Schema::connection('tenant')->hasColumn('invoices', 'net_ht')
            ? 'net_ht'
            : (Schema::connection('tenant')->hasColumn('invoices', 'total_ht') ? 'total_ht' : null);
        $ttcCol = $this->totalColumn();
        $taxCol = Schema::connection('tenant')->hasColumn('invoices', 'tax_amount')
            ? 'tax_amount'
            : null;

        $selects = [
            "COALESCE(SUM({$ttcCol}), 0) as ca_ttc",
        ];
        if ($htCol) {
            $selects[] = "COALESCE(SUM({$htCol}), 0) as ca_ht";
        } else {
            $selects[] = '0 as ca_ht';
        }
        if ($taxCol) {
            $selects[] = "COALESCE(SUM({$taxCol}), 0) as tax_total";
        } else {
            $selects[] = '0 as tax_total';
        }

        $agg = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween($dateCol, [$startDate, $endDate])
            ->whereIn('status', $issuedStatuses)
            ->selectRaw(implode(', ', $selects))
            ->first();

        $caHt = (float) ($agg->ca_ht ?? 0);
        $caTtc = (float) ($agg->ca_ttc ?? 0);
        $tvaTotal = (float) ($agg->tax_total ?? 0);
        $byTax = [];
        if ($tvaTotal > 0) {
            $byTax[] = ['name' => 'TVA', 'amount' => $tvaTotal];
        }

        return [
            'ca_ht' => round($caHt, 2),
            'tva_total' => round($tvaTotal, 2),
            'other_taxes_total' => 0.0,
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
            'method_label' => PaymentMethodLabels::label($r->payment_method),
            'total' => (float) $r->total,
            'count' => (int) $r->cnt,
        ])->all();
    }

    public function getDeliveriesSummary(string $startDate, string $endDate): array
    {
        // BAT facturation has no delivery_notes module.
        return ['confirmed_count' => 0, 'draft_count' => 0];
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

        $dateCol = $this->dateColumn();
        $totalCol = $this->totalColumn();
        $balanceCol = $this->balanceColumn();
        $htCol = Schema::connection('tenant')->hasColumn('invoices', 'net_ht')
            ? 'net_ht'
            : (Schema::connection('tenant')->hasColumn('invoices', 'total_ht') ? 'total_ht' : null);

        $query = DB::connection('tenant')
            ->table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereBetween("invoices.{$dateCol}", [$startDate, $endDate])
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->when($clientId, fn ($q) => $q->where('invoices.client_id', $clientId))
            ->orderByDesc("invoices.{$dateCol}")
            ->limit($limit);

        $select = [
            'invoices.code as invoice_number',
            DB::raw("invoices.{$dateCol} as invoice_date"),
            'invoices.status',
            'clients.name as client_name',
            DB::raw("invoices.{$totalCol} as total"),
            DB::raw("invoices.{$balanceCol} as balance"),
        ];

        if ($htCol) {
            $select[] = DB::raw("invoices.{$htCol} as subtotal");
        } else {
            $select[] = DB::raw('0 as subtotal');
        }

        if (Schema::connection('tenant')->hasColumn('invoices', 'discount_amount')) {
            $select[] = 'invoices.discount_amount';
        } else {
            $select[] = DB::raw('0 as discount_amount');
        }

        if (Schema::connection('tenant')->hasColumn('invoices', 'tax_amount')) {
            $select[] = 'invoices.tax_amount';
        } else {
            $select[] = DB::raw('0 as tax_amount');
        }

        $rows = $query->get($select);

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
            || ! Schema::connection('tenant')->hasColumn('invoices', 'quote_id')) {
            return 0;
        }

        $dateCol = $this->dateColumn();

        return (int) DB::connection('tenant')
            ->table('invoices')
            ->whereBetween($dateCol, [$startDate, $endDate])
            ->whereNotNull('quote_id')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->count(DB::raw('DISTINCT quote_id'));
    }

    private function dateColumn(): string
    {
        return Schema::connection('tenant')->hasColumn('invoices', 'issue_date')
            ? 'issue_date'
            : 'created_at';
    }

    private function totalColumn(): string
    {
        return Schema::connection('tenant')->hasColumn('invoices', 'total_ttc')
            ? 'total_ttc'
            : 'total_ht';
    }

    private function balanceColumn(): string
    {
        if (Schema::connection('tenant')->hasColumn('invoices', 'amount_due')) {
            return 'amount_due';
        }

        return $this->totalColumn();
    }
}
