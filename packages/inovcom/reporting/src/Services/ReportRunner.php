<?php

namespace InovCom\Reporting\Services;

use App\Services\StoreContextService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Reporting\Support\ReportFigures;

class ReportRunner
{
    public const EXPORT_MAX = 5000;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     title: string,
     *     headers: array<int, array{key: string, label: string, type: string}>,
     *     rows: array<int, array<string, mixed>>,
     *     totals: array<string, float|int>,
     *     paginator: LengthAwarePaginator,
     *     truncated: bool
     * }
     */
    public function run(array $filters, int $page, int $perPage, bool $export = false): array
    {
        $report = (string) ($filters['report'] ?? '');
        $perPage = $export ? self::EXPORT_MAX : max(10, min(100, $perPage));
        $page = max(1, $page);

        return match ($report) {
            'synthese' => $this->periodSummary($filters),
            'journal_factures' => $this->invoiceJournal($filters, $page, $perPage, $export),
            'ca_ht_tva' => $this->invoiceTaxSummary($filters),
            'ca_client' => $this->clientRevenue($filters, $page, $perPage, $export),
            'ca_commercial' => $this->commercialRevenue($filters, $page, $perPage, $export),
            'factures_par_statut' => $this->invoicesByStatus($filters, $page, $perPage, $export),
            'creances' => $this->receivables($filters, $page, $perPage, $export),
            'creances_aging' => $this->receivablesAging($filters),
            'journal_ventes' => $this->salesJournal($filters, $page, $perPage, $export),
            'top_produits' => $this->topProducts($filters, $page, $perPage, $export, 'revenue'),
            'top_produits_marge' => $this->topProducts($filters, $page, $perPage, $export, 'margin'),
            'top_produits_qty' => $this->topProducts($filters, $page, $perPage, $export, 'quantity'),
            'ca_categorie' => $this->categoryRevenue($filters, $page, $perPage, $export),
            'journal_encaissements' => $this->paymentsJournal($filters, $page, $perPage, $export),
            'encaissements_par_mode' => $this->paymentsByMethod($filters, $page, $perPage, $export),
            'devis_par_statut' => $this->quotationsByStatus($filters, $page, $perPage, $export),
            'journal_devis' => $this->quotationsJournal($filters, $page, $perPage, $export),
            'journal_achats' => $this->purchasesJournal($filters, $page, $perPage, $export),
            'achats_par_fournisseur' => $this->purchasesByProvider($filters, $page, $perPage, $export),
            'journal_depenses' => $this->expensesJournal($filters, $page, $perPage, $export),
            'depenses_par_categorie' => $this->expensesByCategory($filters, $page, $perPage, $export),
            'valeur_stock' => $this->stockValue($filters, $page, $perPage, $export),
            'stock_par_categorie' => $this->stockByCategory($filters, $page, $perPage, $export),
            'stock_low' => $this->stockAlert($filters, $page, $perPage, $export, 'low'),
            'stock_out' => $this->stockAlert($filters, $page, $perPage, $export, 'out'),
            'stock_dormant' => $this->stockDormant($filters, $page, $perPage, $export),
            default => $this->emptyResult('Rapport'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoiceJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'number', 'label' => 'N° document', 'type' => 'text'],
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'store', 'label' => 'Entité / Point de vente', 'type' => 'text'],
            ['key' => 'user', 'label' => 'Commercial', 'type' => 'text'],
            ['key' => 'ht', 'label' => 'Montant HT', 'type' => 'money'],
            ['key' => 'tva', 'label' => 'TVA', 'type' => 'money'],
            ['key' => 'ttc', 'label' => 'Montant TTC', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
            ['key' => 'payment', 'label' => 'Mode de paiement', 'type' => 'text'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('Journal des factures', $headers);
        }

        $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
        $vatSql = $this->invoiceVatSelect();
        $base = $this->postedInvoicesQuery($filters);

        $this->joinClient($base, 'invoices.client_id');
        $this->joinStore($base, 'invoices.store_id');
        $this->joinUser($base, 'invoices.created_by');
        $this->constrainItemOn('invoices.id', 'invoice_lines', 'invoice_id', $base, $filters);
        $this->constrainAmount($base, 'invoices.total', $filters);

        $totals = (clone $base)->selectRaw("
            COUNT(invoices.id) as row_count,
            COALESCE(SUM({$htSql}), 0) as ht,
            COALESCE(SUM({$vatSql}), 0) as tva,
            COALESCE(SUM(invoices.total), 0) as ttc
        ")->first();

        $sort = $this->sortColumn($filters['sort'] ?? 'date', [
            'date' => 'invoices.invoice_date',
            'number' => 'invoices.invoice_number',
            'client' => 'clients.name',
            'ht' => DB::raw($htSql),
            'tva' => DB::raw($vatSql),
            'ttc' => 'invoices.total',
            'status' => 'invoices.status',
        ], 'invoices.invoice_date');
        $dir = $this->sortDir($filters['dir'] ?? 'desc');

        $select = [
            'invoices.invoice_date as date',
            'invoices.invoice_number as number',
            'invoices.status',
            'invoices.total as ttc',
            DB::raw("{$htSql} as ht"),
            DB::raw("{$vatSql} as tva"),
        ];
        if ($this->hasTable('clients')) {
            $select[] = 'clients.name as client';
        }
        if ($this->hasColumn('invoices', 'payment_mode')) {
            $select[] = 'invoices.payment_mode as payment';
        }
        if ($this->hasColumn('invoices', 'store_id') && $this->hasTable('stores')) {
            $select[] = 'stores.name as store';
        }
        if ($this->hasColumn('invoices', 'created_by') && $this->hasTable('users')) {
            $select[] = 'users.name as user';
        }

        $query = $base->select($select)->orderBy($sort, $dir)->orderByDesc('invoices.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'number' => (string) $row->number,
                'client' => $row->client ?: '—',
                'store' => $row->store ?? '—',
                'user' => $row->user ?? '—',
                'ht' => round((float) $row->ht, 0, PHP_ROUND_HALF_UP),
                'tva' => round((float) $row->tva, 0, PHP_ROUND_HALF_UP),
                'ttc' => round((float) $row->ttc, 0, PHP_ROUND_HALF_UP),
                'status' => (string) $row->status,
                'status_label' => $this->invoiceStatusLabel((string) $row->status),
                'payment' => $row->payment ?: '—',
            ];
        }

        return [
            'title' => 'Journal des factures',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'ht' => round((float) ($totals->ht ?? 0), 0, PHP_ROUND_HALF_UP),
                'tva' => round((float) ($totals->tva ?? 0), 0, PHP_ROUND_HALF_UP),
                'ttc' => round((float) ($totals->ttc ?? 0), 0, PHP_ROUND_HALF_UP),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoiceTaxSummary(array $filters): array
    {
        $headers = [
            ['key' => 'label', 'label' => 'Indicateur', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Montant', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('CA HT / TVA', $headers);
        }

        $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
        $vatSql = $this->invoiceVatSelect();
        $base = $this->postedInvoicesQuery($filters);

        $row = $base->selectRaw("
            COALESCE(SUM({$htSql}), 0) as ht,
            COALESCE(SUM({$vatSql}), 0) as tva,
            COALESCE(SUM(invoices.total), 0) as ttc,
            COUNT(invoices.id) as row_count
        ")->first();

        $ht = round((float) ($row->ht ?? 0), 0, PHP_ROUND_HALF_UP);
        $tva = round((float) ($row->tva ?? 0), 0, PHP_ROUND_HALF_UP);
        $ttc = round($ht + $tva, 0, PHP_ROUND_HALF_UP);

        $rows = [
            ['label' => 'CA HT (facturé)', 'amount' => $ht],
            ['label' => 'TVA facturée', 'amount' => $tva],
            ['label' => 'CA TTC (HT + TVA)', 'amount' => $ttc],
            ['label' => 'Nombre de factures', 'amount' => (int) ($row->row_count ?? 0), 'type' => 'int'],
        ];

        $paginator = $this->wrapPage($rows, count($rows), 1, 25);

        return [
            'title' => 'CA HT / TVA',
            'headers' => $headers,
            'rows' => $rows,
            'totals' => ['amount' => $ttc],
            'paginator' => $paginator,
            'truncated' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function clientRevenue(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'ht', 'label' => 'CA HT', 'type' => 'money'],
            ['key' => 'tva', 'label' => 'TVA', 'type' => 'money'],
            ['key' => 'ttc', 'label' => 'CA TTC', 'type' => 'money'],
            ['key' => 'count', 'label' => 'Factures', 'type' => 'int'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('CA par client', $headers);
        }

        $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
        $vatSql = $this->invoiceVatSelect();
        $base = $this->postedInvoicesQuery($filters);
        $this->joinClient($base, 'invoices.client_id');

        $grouped = (clone $base)
            ->selectRaw("
                invoices.client_id,
                MAX(clients.name) as client,
                COALESCE(SUM({$htSql}), 0) as ht,
                COALESCE(SUM({$vatSql}), 0) as tva,
                COALESCE(SUM(invoices.total), 0) as ttc,
                COUNT(invoices.id) as invoice_count
            ")
            ->groupBy('invoices.client_id');

        $totals = (clone $base)->selectRaw("
            COUNT(DISTINCT invoices.client_id) as row_count,
            COALESCE(SUM({$htSql}), 0) as ht,
            COALESCE(SUM({$vatSql}), 0) as tva,
            COALESCE(SUM(invoices.total), 0) as ttc
        ")->first();

        $query = $grouped->orderByDesc('ttc');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'client' => $row->client ?: '—',
                'ht' => round((float) $row->ht, 0, PHP_ROUND_HALF_UP),
                'tva' => round((float) $row->tva, 0, PHP_ROUND_HALF_UP),
                'ttc' => round((float) $row->ttc, 0, PHP_ROUND_HALF_UP),
                'count' => (int) $row->invoice_count,
            ];
        }

        return [
            'title' => 'CA par client',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'ht' => round((float) ($totals->ht ?? 0), 0, PHP_ROUND_HALF_UP),
                'tva' => round((float) ($totals->tva ?? 0), 0, PHP_ROUND_HALF_UP),
                'ttc' => round((float) ($totals->ttc ?? 0), 0, PHP_ROUND_HALF_UP),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function periodSummary(array $filters): array
    {
        $headers = [
            ['key' => 'label', 'label' => 'Indicateur', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'int'],
            ['key' => 'amount', 'label' => 'Montant', 'type' => 'money'],
        ];

        $rows = [];

        if ($this->hasTable('invoices')) {
            $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
            $vatSql = $this->invoiceVatSelect();
            $billed = $this->postedInvoicesQuery($filters)->selectRaw("
                COUNT(invoices.id) as row_count,
                COUNT(DISTINCT invoices.client_id) as clients,
                COALESCE(SUM({$htSql}), 0) as ht,
                COALESCE(SUM({$vatSql}), 0) as tva
            ")->first();
            $ht = ReportFigures::francs((float) ($billed->ht ?? 0));
            $tva = ReportFigures::francs((float) ($billed->tva ?? 0));
            $count = (int) ($billed->row_count ?? 0);
            $rows[] = ['label' => 'Factures émises — CA HT', 'count' => $count, 'amount' => $ht];
            $rows[] = ['label' => 'TVA facturée', 'count' => null, 'amount' => $tva];
            $rows[] = ['label' => 'CA TTC (HT + TVA)', 'count' => $count, 'amount' => ReportFigures::francs($ht + $tva)];
            $rows[] = ['label' => 'Clients facturés', 'count' => (int) ($billed->clients ?? 0), 'amount' => null];

            $open = $this->openInvoicesQuery($filters, false)->selectRaw('
                COUNT(invoices.id) as row_count,
                COALESCE(SUM(invoices.balance), 0) as balance
            ')->first();
            $rows[] = [
                'label' => 'Créances ouvertes (aujourd’hui)',
                'count' => (int) ($open->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($open->balance ?? 0)),
            ];

            $openPeriod = $this->openInvoicesQuery($filters, true)->selectRaw('
                COUNT(invoices.id) as row_count,
                COALESCE(SUM(invoices.balance), 0) as balance
            ')->first();
            $rows[] = [
                'label' => 'Créances nées sur la période',
                'count' => (int) ($openPeriod->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($openPeriod->balance ?? 0)),
            ];
        }

        if ($this->hasTable('invoice_payments')) {
            $pay = $this->paymentsBase($filters)->selectRaw('
                COUNT(invoice_payments.id) as row_count,
                COALESCE(SUM(invoice_payments.amount), 0) as amount
            ')->first();
            $rows[] = [
                'label' => 'Encaissements (espèces)',
                'count' => (int) ($pay->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($pay->amount ?? 0)),
            ];
        }

        if ($this->hasTable('sales')) {
            $sales = DB::connection('tenant')->table('sales')
                ->whereBetween('sales.sale_date', [$filters['from'], $filters['to']]);
            $this->applyStoreFilter($sales, 'sales');
            $this->filterEquals($sales, 'sales.store_id', $filters['store_id'] ?? null);
            $saleRow = $sales->selectRaw('COUNT(sales.id) as row_count, COALESCE(SUM(sales.total), 0) as total')->first();
            $rows[] = [
                'label' => 'Ventes caisse',
                'count' => (int) ($saleRow->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($saleRow->total ?? 0)),
            ];
        }

        if ($this->hasTable('expenses')) {
            $exp = DB::connection('tenant')->table('expenses')
                ->whereBetween('expenses.expense_date', [$filters['from'], $filters['to']]);
            $this->applyStoreFilter($exp, 'expenses');
            $expRow = $exp->selectRaw('COUNT(expenses.id) as row_count, COALESCE(SUM(expenses.amount), 0) as amount')->first();
            $rows[] = [
                'label' => 'Dépenses',
                'count' => (int) ($expRow->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($expRow->amount ?? 0)),
            ];
        }

        if ($this->hasTable('quotations')) {
            $quotes = DB::connection('tenant')->table('quotations')
                ->whereBetween('quotations.quote_date', [$filters['from'], $filters['to']]);
            $this->applyStoreFilter($quotes, 'quotations');
            $this->filterEquals($quotes, 'quotations.store_id', $filters['store_id'] ?? null);
            $quoteRow = $quotes->selectRaw('COUNT(quotations.id) as row_count, COALESCE(SUM(quotations.total), 0) as total')->first();
            $rows[] = [
                'label' => 'Devis',
                'count' => (int) ($quoteRow->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($quoteRow->total ?? 0)),
            ];
        }

        if ($this->hasTable('stock_levels') && $this->hasTable('items')) {
            $stock = $this->stockBase($filters)
                ->whereRaw('COALESCE(stock_levels.quantity, 0) <> 0');
            $stockRow = $stock->selectRaw('
                COUNT(items.id) as row_count,
                COALESCE(SUM(stock_levels.quantity * COALESCE(items.cost, 0)), 0) as valeur
            ')->first();
            $rows[] = [
                'label' => 'Stock actuel (valeur d’achat)',
                'count' => (int) ($stockRow->row_count ?? 0),
                'amount' => ReportFigures::francs((float) ($stockRow->valeur ?? 0)),
            ];
        }

        if ($rows === []) {
            return $this->emptyResult('Synthèse de la période', $headers);
        }

        $paginator = $this->wrapPage($rows, count($rows), 1, max(count($rows), 25));

        return [
            'title' => 'Synthèse de la période',
            'headers' => $headers,
            'rows' => $rows,
            'totals' => [],
            'paginator' => $paginator,
            'truncated' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function commercialRevenue(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'user', 'label' => 'Commercial', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Factures', 'type' => 'int'],
            ['key' => 'ht', 'label' => 'CA HT', 'type' => 'money'],
            ['key' => 'tva', 'label' => 'TVA', 'type' => 'money'],
            ['key' => 'ttc', 'label' => 'CA TTC', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('CA par commercial', $headers);
        }

        $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
        $vatSql = $this->invoiceVatSelect();
        $base = $this->postedInvoicesQuery($filters);
        $this->joinUser($base, 'invoices.created_by');
        $userExpr = $this->hasTable('users') ? 'MAX(users.name)' : "'—'";

        $grouped = (clone $base)->selectRaw("
                invoices.created_by,
                {$userExpr} as user,
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM({$htSql}), 0) as ht,
                COALESCE(SUM({$vatSql}), 0) as tva
            ")
            ->groupBy('invoices.created_by');

        $totals = (clone $base)->selectRaw("
            COUNT(DISTINCT invoices.created_by) as row_count,
            COALESCE(SUM({$htSql}), 0) as ht,
            COALESCE(SUM({$vatSql}), 0) as tva
        ")->first();

        $query = $grouped->orderByDesc('ht');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $ht = ReportFigures::francs((float) $row->ht);
            $tva = ReportFigures::francs((float) $row->tva);
            $mapped[] = [
                'user' => $row->user ?: '—',
                'count' => (int) $row->invoice_count,
                'ht' => $ht,
                'tva' => $tva,
                'ttc' => ReportFigures::francs($ht + $tva),
            ];
        }

        $htTotal = ReportFigures::francs((float) ($totals->ht ?? 0));
        $tvaTotal = ReportFigures::francs((float) ($totals->tva ?? 0));

        return [
            'title' => 'CA par commercial',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'ht' => $htTotal,
                'tva' => $tvaTotal,
                'ttc' => ReportFigures::francs($htTotal + $tvaTotal),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoicesByStatus(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'int'],
            ['key' => 'ht', 'label' => 'CA HT', 'type' => 'money'],
            ['key' => 'tva', 'label' => 'TVA', 'type' => 'money'],
            ['key' => 'ttc', 'label' => 'CA TTC', 'type' => 'money'],
            ['key' => 'balance', 'label' => 'Solde', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('Factures par statut', $headers);
        }

        $htSql = 'GREATEST(0, invoices.subtotal - COALESCE(invoices.discount_amount, 0))';
        $vatSql = $this->invoiceVatSelect();
        $base = $this->invoicesInPeriodQuery($filters);

        $grouped = (clone $base)->selectRaw("
                invoices.status,
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM({$htSql}), 0) as ht,
                COALESCE(SUM({$vatSql}), 0) as tva,
                COALESCE(SUM(invoices.balance), 0) as balance
            ")
            ->groupBy('invoices.status');

        $totals = (clone $base)->selectRaw("
            COUNT(DISTINCT invoices.status) as row_count,
            COUNT(invoices.id) as invoice_count,
            COALESCE(SUM({$htSql}), 0) as ht,
            COALESCE(SUM({$vatSql}), 0) as tva,
            COALESCE(SUM(invoices.balance), 0) as balance
        ")->first();

        $query = $grouped->orderByDesc('invoice_count');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $ht = ReportFigures::francs((float) $row->ht);
            $tva = ReportFigures::francs((float) $row->tva);
            $mapped[] = [
                'status' => $status,
                'status_label' => $this->invoiceStatusLabel($status),
                'count' => (int) $row->invoice_count,
                'ht' => $ht,
                'tva' => $tva,
                'ttc' => ReportFigures::francs($ht + $tva),
                'balance' => ReportFigures::francs((float) $row->balance),
            ];
        }

        $htTotal = ReportFigures::francs((float) ($totals->ht ?? 0));
        $tvaTotal = ReportFigures::francs((float) ($totals->tva ?? 0));

        return [
            'title' => 'Factures par statut',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->invoice_count ?? 0),
                'ht' => $htTotal,
                'tva' => $tvaTotal,
                'ttc' => ReportFigures::francs($htTotal + $tvaTotal),
                'balance' => ReportFigures::francs((float) ($totals->balance ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function receivables(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'number', 'label' => 'N° facture', 'type' => 'text'],
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'due', 'label' => 'Échéance', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Retard (j)', 'type' => 'int'],
            ['key' => 'bucket', 'label' => 'Ancienneté', 'type' => 'text'],
            ['key' => 'ttc', 'label' => 'TTC', 'type' => 'money'],
            ['key' => 'balance', 'label' => 'Solde', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('Créances clients', $headers);
        }

        $asOf = (string) ($filters['to'] ?? now()->toDateString());
        $daysSql = $this->daysOverdueSql($asOf);
        $bucketSql = $this->agingBucketSql($asOf, $daysSql);
        $base = $this->openInvoicesQuery($filters, true);
        $this->joinClient($base, 'invoices.client_id');

        $totals = (clone $base)->selectRaw('
            COUNT(invoices.id) as row_count,
            COALESCE(SUM(invoices.total), 0) as ttc,
            COALESCE(SUM(invoices.balance), 0) as balance
        ')->first();

        $select = [
            'invoices.invoice_date as date',
            'invoices.invoice_number as number',
            'invoices.total as ttc',
            'invoices.balance',
            DB::raw("{$daysSql} as days"),
            DB::raw("{$bucketSql} as bucket"),
        ];
        if ($this->hasColumn('invoices', 'due_date')) {
            $select[] = 'invoices.due_date as due';
        }
        if ($this->hasTable('clients')) {
            $select[] = 'clients.name as client';
        }

        $query = $base->select($select)->orderByDesc(DB::raw($daysSql))->orderByDesc('invoices.balance');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $due = $row->due ?? null;
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'number' => (string) $row->number,
                'client' => $row->client ?: '—',
                'due' => $due ? Carbon::parse($due)->format('d/m/Y') : '—',
                'days' => max(0, (int) $row->days),
                'bucket' => (string) $row->bucket,
                'ttc' => ReportFigures::francs((float) $row->ttc),
                'balance' => ReportFigures::francs((float) $row->balance),
            ];
        }

        return [
            'title' => 'Créances clients',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'ttc' => ReportFigures::francs((float) ($totals->ttc ?? 0)),
                'balance' => ReportFigures::francs((float) ($totals->balance ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function receivablesAging(array $filters): array
    {
        $headers = [
            ['key' => 'bucket', 'label' => 'Ancienneté', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Factures', 'type' => 'int'],
            ['key' => 'balance', 'label' => 'Solde', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoices')) {
            return $this->emptyResult('Créances par ancienneté', $headers);
        }

        $asOf = (string) ($filters['to'] ?? now()->toDateString());
        $daysSql = $this->daysOverdueSql($asOf);
        $bucketSql = $this->agingBucketSql($asOf, $daysSql);
        $base = $this->openInvoicesQuery($filters, true);

        $rows = (clone $base)->selectRaw("
                {$bucketSql} as bucket,
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM(invoices.balance), 0) as balance
            ")
            ->groupByRaw($bucketSql)
            ->orderByRaw("MIN({$daysSql})")
            ->get();

        $order = ['À échoir' => 0, '1–30 j' => 1, '31–60 j' => 2, '61–90 j' => 3, '+90 j' => 4];
        $mapped = [];
        $totalBalance = 0.0;
        $totalCount = 0;
        foreach ($rows as $row) {
            $balance = ReportFigures::francs((float) $row->balance);
            $count = (int) $row->invoice_count;
            $totalBalance += $balance;
            $totalCount += $count;
            $mapped[] = [
                'bucket' => (string) $row->bucket,
                'count' => $count,
                'balance' => $balance,
                '_ord' => $order[(string) $row->bucket] ?? 9,
            ];
        }
        usort($mapped, fn ($a, $b) => $a['_ord'] <=> $b['_ord']);
        $mapped = array_map(function ($row) {
            unset($row['_ord']);

            return $row;
        }, $mapped);

        $paginator = $this->wrapPage($mapped, count($mapped), 1, 25);

        return [
            'title' => 'Créances par ancienneté',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => $totalCount,
                'balance' => ReportFigures::francs($totalBalance),
            ],
            'paginator' => $paginator,
            'truncated' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'number', 'label' => 'N° document', 'type' => 'text'],
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'store', 'label' => 'Entité / Point de vente', 'type' => 'text'],
            ['key' => 'user', 'label' => 'Commercial', 'type' => 'text'],
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
        ];

        if (! $this->hasTable('sales')) {
            return $this->emptyResult('Journal des ventes', $headers);
        }

        $base = DB::connection('tenant')->table('sales')
            ->whereBetween('sales.sale_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($base, 'sales');
        $this->filterEquals($base, 'sales.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($base, 'sales.client_id', $filters['client_id'] ?? null);
        $this->filterEquals($base, 'sales.created_by', $filters['user_id'] ?? null);
        $this->constrainAmount($base, 'sales.total', $filters);
        $this->constrainItemOn('sales.id', 'sale_lines', 'sale_id', $base, $filters);
        $this->joinClient($base, 'sales.client_id');
        $this->joinStore($base, 'sales.store_id');
        $this->joinUser($base, 'sales.created_by');

        $totals = (clone $base)->selectRaw('COUNT(sales.id) as row_count, COALESCE(SUM(sales.total), 0) as total')->first();

        $select = [
            'sales.sale_date as date',
            'sales.sale_number as number',
            'sales.total',
        ];
        if ($this->hasTable('clients')) {
            $select[] = 'clients.name as client';
        }
        if ($this->hasColumn('sales', 'store_id') && $this->hasTable('stores')) {
            $select[] = 'stores.name as store';
        }
        if ($this->hasColumn('sales', 'created_by') && $this->hasTable('users')) {
            $select[] = 'users.name as user';
        }

        $query = $base->select($select)->orderByDesc('sales.sale_date')->orderByDesc('sales.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'number' => (string) $row->number,
                'client' => $row->client ?: 'Comptoir',
                'store' => $row->store ?? '—',
                'user' => $row->user ?? '—',
                'total' => round((float) $row->total, 0, PHP_ROUND_HALF_UP),
            ];
        }

        return [
            'title' => 'Journal des ventes',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => ['total' => round((float) ($totals->total ?? 0), 0, PHP_ROUND_HALF_UP)],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function topProducts(array $filters, int $page, int $perPage, bool $export, string $sortBy): array
    {
        $title = match ($sortBy) {
            'quantity' => 'Top articles par quantité',
            'margin' => 'Top articles par bénéfice',
            default => 'Top articles par CA',
        };
        $headers = [
            ['key' => 'sku', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Article', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'Quantité', 'type' => 'qty'],
            ['key' => 'revenue', 'label' => 'CA', 'type' => 'money'],
            ['key' => 'cost', 'label' => 'Coût', 'type' => 'money'],
            ['key' => 'benefit', 'label' => 'Bénéfice', 'type' => 'money'],
            ['key' => 'margin_pct', 'label' => 'Marge %', 'type' => 'percent'],
            ['key' => 'share_pct', 'label' => 'Part CA %', 'type' => 'percent'],
            ['key' => 'docs', 'label' => 'Documents', 'type' => 'int'],
        ];

        $union = $this->productLineUnion($filters);
        if ($union === null) {
            return $this->emptyResult($title, $headers);
        }

        $grouped = DB::connection('tenant')->query()
            ->fromSub($union, 'p')
            ->selectRaw('
                item_key,
                MAX(sku) as sku,
                MAX(name) as name,
                MAX(category) as category,
                SUM(qty) as qty,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(docs) as docs
            ')
            ->groupBy('item_key');

        $totals = DB::connection('tenant')->query()
            ->fromSub($grouped, 'agg')
            ->selectRaw('
                COUNT(*) as row_count,
                COALESCE(SUM(qty), 0) as qty,
                COALESCE(SUM(revenue), 0) as revenue,
                COALESCE(SUM(cost), 0) as cost,
                COALESCE(SUM(docs), 0) as docs
            ')
            ->first();

        $default = match ($sortBy) {
            'quantity' => 'qty',
            'margin' => 'benefit',
            default => 'revenue',
        };
        $requested = (string) ($filters['sort'] ?? '');
        $orderCol = in_array($requested, ['qty', 'revenue', 'cost', 'benefit', 'docs', 'name', 'sku'], true)
            ? $requested
            : $default;
        $dir = $this->sortDir($filters['dir'] ?? 'desc');

        $query = DB::connection('tenant')->query()
            ->fromSub($union, 'p')
            ->selectRaw('
                item_key,
                MAX(sku) as sku,
                MAX(name) as name,
                MAX(category) as category,
                SUM(qty) as qty,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(revenue) - SUM(cost) as benefit,
                SUM(docs) as docs
            ')
            ->groupBy('item_key')
            ->orderBy($orderCol, $dir);

        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $totalRevenue = (float) ($totals->revenue ?? 0);
        $mapped = [];
        foreach ($rows as $row) {
            $revenue = ReportFigures::francs((float) $row->revenue);
            $cost = ReportFigures::francs((float) $row->cost);
            $mapped[] = [
                'sku' => $row->sku ?: '—',
                'name' => $row->name ?: '—',
                'category' => $row->category ?: 'Sans catégorie',
                'qty' => (float) $row->qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'benefit' => ReportFigures::francs($revenue - $cost),
                'margin_pct' => ReportFigures::marginPct($revenue, $cost),
                'share_pct' => ReportFigures::sharePct($revenue, ReportFigures::francs($totalRevenue)),
                'docs' => (int) $row->docs,
            ];
        }

        $revTotal = ReportFigures::francs($totalRevenue);
        $costTotal = ReportFigures::francs((float) ($totals->cost ?? 0));

        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'qty' => (float) ($totals->qty ?? 0),
                'revenue' => $revTotal,
                'cost' => $costTotal,
                'benefit' => ReportFigures::francs($revTotal - $costTotal),
                'margin_pct' => ReportFigures::marginPct($revTotal, $costTotal),
                'docs' => (int) ($totals->docs ?? 0),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function categoryRevenue(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'Quantité', 'type' => 'qty'],
            ['key' => 'revenue', 'label' => 'CA', 'type' => 'money'],
            ['key' => 'cost', 'label' => 'Coût', 'type' => 'money'],
            ['key' => 'benefit', 'label' => 'Bénéfice', 'type' => 'money'],
            ['key' => 'margin_pct', 'label' => 'Marge %', 'type' => 'percent'],
            ['key' => 'share_pct', 'label' => 'Part CA %', 'type' => 'percent'],
            ['key' => 'docs', 'label' => 'Documents', 'type' => 'int'],
        ];

        $union = $this->productLineUnion($filters);
        if ($union === null) {
            return $this->emptyResult('CA par catégorie', $headers);
        }

        $grouped = DB::connection('tenant')->query()
            ->fromSub($union, 'p')
            ->selectRaw('
                category_key,
                MAX(category) as category,
                SUM(qty) as qty,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(docs) as docs
            ')
            ->groupBy('category_key');

        $totals = DB::connection('tenant')->query()
            ->fromSub($grouped, 'agg')
            ->selectRaw('
                COUNT(*) as row_count,
                COALESCE(SUM(qty), 0) as qty,
                COALESCE(SUM(revenue), 0) as revenue,
                COALESCE(SUM(cost), 0) as cost,
                COALESCE(SUM(docs), 0) as docs
            ')
            ->first();

        $query = DB::connection('tenant')->query()
            ->fromSub($union, 'p')
            ->selectRaw('
                category_key,
                MAX(category) as category,
                SUM(qty) as qty,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(revenue) - SUM(cost) as benefit,
                SUM(docs) as docs
            ')
            ->groupBy('category_key')
            ->orderByDesc('revenue');

        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $totalRevenue = ReportFigures::francs((float) ($totals->revenue ?? 0));
        $mapped = [];
        foreach ($rows as $row) {
            $revenue = ReportFigures::francs((float) $row->revenue);
            $cost = ReportFigures::francs((float) $row->cost);
            $mapped[] = [
                'category' => $row->category ?: 'Sans catégorie',
                'qty' => (float) $row->qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'benefit' => ReportFigures::francs($revenue - $cost),
                'margin_pct' => ReportFigures::marginPct($revenue, $cost),
                'share_pct' => ReportFigures::sharePct($revenue, $totalRevenue),
                'docs' => (int) $row->docs,
            ];
        }

        $costTotal = ReportFigures::francs((float) ($totals->cost ?? 0));

        return [
            'title' => 'CA par catégorie',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'qty' => (float) ($totals->qty ?? 0),
                'revenue' => $totalRevenue,
                'cost' => $costTotal,
                'benefit' => ReportFigures::francs($totalRevenue - $costTotal),
                'margin_pct' => ReportFigures::marginPct($totalRevenue, $costTotal),
                'docs' => (int) ($totals->docs ?? 0),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentsJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'reference', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'invoice', 'label' => 'Facture', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Espèces', 'type' => 'money'],
            ['key' => 'settled', 'label' => 'Réglé', 'type' => 'money'],
            ['key' => 'method', 'label' => 'Mode', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
        ];

        if (! $this->hasTable('invoice_payments')) {
            return $this->emptyResult('Journal des encaissements', $headers);
        }

        $base = DB::connection('tenant')->table('invoice_payments')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->whereBetween('invoice_payments.payment_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($base, 'invoices');
        $this->joinClient($base, 'invoices.client_id');
        $this->filterEquals($base, 'invoices.client_id', $filters['client_id'] ?? null);

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && $this->hasColumn('invoice_payments', 'status')) {
            $base->where('invoice_payments.status', $status);
        }

        $settledSql = $this->hasColumn('invoice_payments', 'settled_amount')
            ? 'CASE WHEN invoice_payments.settled_amount IS NULL THEN invoice_payments.amount ELSE invoice_payments.settled_amount END'
            : 'invoice_payments.amount';

        $totals = (clone $base)->selectRaw("
            COUNT(invoice_payments.id) as row_count,
            COALESCE(SUM(invoice_payments.amount), 0) as amount,
            COALESCE(SUM({$settledSql}), 0) as settled
        ")->first();

        $select = [
            'invoice_payments.payment_date as date',
            'invoice_payments.reference',
            'invoice_payments.amount',
            'invoice_payments.payment_method as method',
            'invoices.invoice_number as invoice',
            DB::raw("{$settledSql} as settled"),
        ];
        if ($this->hasTable('clients')) {
            $select[] = 'clients.name as client';
        }
        if ($this->hasColumn('invoice_payments', 'status')) {
            $select[] = 'invoice_payments.status';
        }

        $query = $base->select($select)->orderByDesc('invoice_payments.payment_date')->orderByDesc('invoice_payments.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $method = (string) ($row->method ?? '');
            $statusKey = (string) ($row->status ?? 'active');
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'reference' => (string) ($row->reference ?: '—'),
                'client' => $row->client ?: '—',
                'invoice' => (string) ($row->invoice ?? ''),
                'amount' => round((float) $row->amount, 0, PHP_ROUND_HALF_UP),
                'settled' => round((float) $row->settled, 0, PHP_ROUND_HALF_UP),
                'method' => class_exists(InvoicePayment::class) ? InvoicePayment::methodLabel($method) : $method,
                'status' => $statusKey,
                'status_label' => $statusKey === 'cancelled' ? 'Annulé' : 'Actif',
            ];
        }

        return [
            'title' => 'Journal des encaissements',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'amount' => round((float) ($totals->amount ?? 0), 0, PHP_ROUND_HALF_UP),
                'settled' => round((float) ($totals->settled ?? 0), 0, PHP_ROUND_HALF_UP),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentsByMethod(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'method', 'label' => 'Mode', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'int'],
            ['key' => 'amount', 'label' => 'Espèces', 'type' => 'money'],
            ['key' => 'settled', 'label' => 'Réglé', 'type' => 'money'],
        ];

        if (! $this->hasTable('invoice_payments')) {
            return $this->emptyResult('Encaissements par mode', $headers);
        }

        $base = $this->paymentsBase($filters);
        $settledSql = $this->hasColumn('invoice_payments', 'settled_amount')
            ? 'CASE WHEN invoice_payments.settled_amount IS NULL THEN invoice_payments.amount ELSE invoice_payments.settled_amount END'
            : 'invoice_payments.amount';

        $grouped = (clone $base)->selectRaw("
                invoice_payments.payment_method as method,
                COUNT(invoice_payments.id) as payment_count,
                COALESCE(SUM(invoice_payments.amount), 0) as amount,
                COALESCE(SUM({$settledSql}), 0) as settled
            ")
            ->groupBy('invoice_payments.payment_method');

        $totals = (clone $base)->selectRaw("
            COUNT(DISTINCT invoice_payments.payment_method) as row_count,
            COUNT(invoice_payments.id) as payment_count,
            COALESCE(SUM(invoice_payments.amount), 0) as amount,
            COALESCE(SUM({$settledSql}), 0) as settled
        ")->first();

        $query = $grouped->orderByDesc('amount');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $method = (string) ($row->method ?? '');
            $mapped[] = [
                'method' => class_exists(InvoicePayment::class) ? InvoicePayment::methodLabel($method) : ($method ?: '—'),
                'count' => (int) $row->payment_count,
                'amount' => ReportFigures::francs((float) $row->amount),
                'settled' => ReportFigures::francs((float) $row->settled),
            ];
        }

        return [
            'title' => 'Encaissements par mode',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->payment_count ?? 0),
                'amount' => ReportFigures::francs((float) ($totals->amount ?? 0)),
                'settled' => ReportFigures::francs((float) ($totals->settled ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function quotationsByStatus(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'int'],
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
        ];

        if (! $this->hasTable('quotations')) {
            return $this->emptyResult('Devis par statut', $headers);
        }

        $base = $this->quotationsBase($filters);
        $grouped = (clone $base)->selectRaw('
                quotations.status,
                COUNT(quotations.id) as quote_count,
                COALESCE(SUM(quotations.total), 0) as total
            ')
            ->groupBy('quotations.status');

        $totals = (clone $base)->selectRaw('
            COUNT(DISTINCT quotations.status) as row_count,
            COUNT(quotations.id) as quote_count,
            COALESCE(SUM(quotations.total), 0) as total
        ')->first();

        $query = $grouped->orderByDesc('total');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $mapped[] = [
                'status' => $status,
                'status_label' => $this->quotationStatusLabel($status),
                'count' => (int) $row->quote_count,
                'total' => ReportFigures::francs((float) $row->total),
            ];
        }

        return [
            'title' => 'Devis par statut',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->quote_count ?? 0),
                'total' => ReportFigures::francs((float) ($totals->total ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function quotationsJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'number', 'label' => 'N° devis', 'type' => 'text'],
            ['key' => 'client', 'label' => 'Client', 'type' => 'text'],
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
            ['key' => 'valid', 'label' => 'Validité', 'type' => 'date'],
        ];

        if (! $this->hasTable('quotations')) {
            return $this->emptyResult('Journal des devis', $headers);
        }

        $base = $this->quotationsBase($filters);
        $this->joinClient($base, 'quotations.client_id');
        $this->constrainAmount($base, 'quotations.total', $filters);

        $totals = (clone $base)->selectRaw('COUNT(quotations.id) as row_count, COALESCE(SUM(quotations.total), 0) as total')->first();

        $select = [
            'quotations.quote_date as date',
            'quotations.number',
            'quotations.total',
            'quotations.status',
        ];
        if ($this->hasColumn('quotations', 'valid_until')) {
            $select[] = 'quotations.valid_until as valid';
        }
        if ($this->hasTable('clients')) {
            $select[] = 'clients.name as client';
        }

        $query = $base->select($select)->orderByDesc('quotations.quote_date')->orderByDesc('quotations.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'number' => (string) $row->number,
                'client' => $row->client ?: '—',
                'total' => ReportFigures::francs((float) $row->total),
                'status' => $status,
                'status_label' => $this->quotationStatusLabel($status),
                'valid' => $row->valid ? Carbon::parse($row->valid)->format('d/m/Y') : '—',
            ];
        }

        return [
            'title' => 'Journal des devis',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => ['total' => ReportFigures::francs((float) ($totals->total ?? 0))],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function purchasesJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'number', 'label' => 'N° commande', 'type' => 'text'],
            ['key' => 'provider', 'label' => 'Fournisseur', 'type' => 'text'],
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
        ];

        if (! $this->hasTable('purchase_orders')) {
            return $this->emptyResult('Journal des achats', $headers);
        }

        $base = DB::connection('tenant')->table('purchase_orders')
            ->whereBetween('purchase_orders.order_date', [$filters['from'], $filters['to']]);
        if ($this->hasTable('providers')) {
            $base->leftJoin('providers', 'providers.id', '=', 'purchase_orders.provider_id');
        }
        $this->filterEquals($base, 'purchase_orders.status', $filters['status'] ?? null, allowEmpty: false);
        $this->constrainAmount($base, 'purchase_orders.total', $filters);

        $totals = (clone $base)->selectRaw('COUNT(purchase_orders.id) as row_count, COALESCE(SUM(purchase_orders.total), 0) as total')->first();

        $select = [
            'purchase_orders.order_date as date',
            'purchase_orders.order_number as number',
            'purchase_orders.total',
            'purchase_orders.status',
        ];
        if ($this->hasTable('providers')) {
            $select[] = 'providers.name as provider';
        }

        $query = $base->select($select)->orderByDesc('purchase_orders.order_date')->orderByDesc('purchase_orders.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $labels = [
            'draft' => 'Brouillon',
            'sent' => 'Envoyée',
            'received' => 'Reçue',
            'cancelled' => 'Annulée',
        ];
        $mapped = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'number' => (string) $row->number,
                'provider' => $row->provider ?? '—',
                'total' => round((float) $row->total, 0, PHP_ROUND_HALF_UP),
                'status' => $status,
                'status_label' => $labels[$status] ?? $status,
            ];
        }

        return [
            'title' => 'Journal des achats',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => ['total' => round((float) ($totals->total ?? 0), 0, PHP_ROUND_HALF_UP)],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function purchasesByProvider(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'provider', 'label' => 'Fournisseur', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Commandes', 'type' => 'int'],
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
        ];

        if (! $this->hasTable('purchase_orders')) {
            return $this->emptyResult('Achats par fournisseur', $headers);
        }

        $base = DB::connection('tenant')->table('purchase_orders')
            ->whereBetween('purchase_orders.order_date', [$filters['from'], $filters['to']]);
        if ($this->hasTable('providers')) {
            $base->leftJoin('providers', 'providers.id', '=', 'purchase_orders.provider_id');
        }
        $this->filterEquals($base, 'purchase_orders.status', $filters['status'] ?? null, allowEmpty: false);
        $providerExpr = $this->hasTable('providers') ? 'MAX(providers.name)' : "'—'";

        $grouped = (clone $base)->selectRaw("
                purchase_orders.provider_id,
                {$providerExpr} as provider,
                COUNT(purchase_orders.id) as order_count,
                COALESCE(SUM(purchase_orders.total), 0) as total
            ")
            ->groupBy('purchase_orders.provider_id');

        $totals = (clone $base)->selectRaw('
            COUNT(DISTINCT purchase_orders.provider_id) as row_count,
            COUNT(purchase_orders.id) as order_count,
            COALESCE(SUM(purchase_orders.total), 0) as total
        ')->first();

        $query = $grouped->orderByDesc('total');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'provider' => $row->provider ?: '—',
                'count' => (int) $row->order_count,
                'total' => ReportFigures::francs((float) $row->total),
            ];
        }

        return [
            'title' => 'Achats par fournisseur',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->order_count ?? 0),
                'total' => ReportFigures::francs((float) ($totals->total ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function expensesJournal(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'reference', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Montant', 'type' => 'money'],
            ['key' => 'method', 'label' => 'Mode', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
        ];

        if (! $this->hasTable('expenses')) {
            return $this->emptyResult('Journal des dépenses', $headers);
        }

        $base = DB::connection('tenant')->table('expenses')
            ->whereBetween('expenses.expense_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($base, 'expenses');
        if ($this->hasTable('expense_categories')) {
            $base->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        }
        $this->filterEquals($base, 'expenses.expense_category_id', $filters['category_id'] ?? null);
        $this->filterEquals($base, 'expenses.status', $filters['status'] ?? null, allowEmpty: false);
        $this->constrainAmount($base, 'expenses.amount', $filters);

        $totals = (clone $base)->selectRaw('COUNT(expenses.id) as row_count, COALESCE(SUM(expenses.amount), 0) as amount')->first();

        $select = [
            'expenses.expense_date as date',
            'expenses.reference',
            'expenses.amount',
            'expenses.payment_method as method',
            'expenses.status',
        ];
        if ($this->hasTable('expense_categories')) {
            $select[] = 'expense_categories.name as category';
        }

        $query = $base->select($select)->orderByDesc('expenses.expense_date')->orderByDesc('expenses.id');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $labels = [
            'draft' => 'Brouillon',
            'pending' => 'En attente',
            'approved' => 'Validée',
            'paid' => 'Payée',
            'rejected' => 'Rejetée',
        ];
        $mapped = [];
        foreach ($rows as $row) {
            $method = (string) ($row->method ?? '');
            $status = (string) $row->status;
            $mapped[] = [
                'date' => $row->date ? Carbon::parse($row->date)->format('d/m/Y') : '',
                'reference' => (string) $row->reference,
                'category' => $row->category ?? '—',
                'amount' => round((float) $row->amount, 0, PHP_ROUND_HALF_UP),
                'method' => class_exists(InvoicePayment::class) ? InvoicePayment::methodLabel($method) : $method,
                'status' => $status,
                'status_label' => $labels[$status] ?? $status,
            ];
        }

        return [
            'title' => 'Journal des dépenses',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => ['amount' => round((float) ($totals->amount ?? 0), 0, PHP_ROUND_HALF_UP)],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function expensesByCategory(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'int'],
            ['key' => 'amount', 'label' => 'Montant', 'type' => 'money'],
        ];

        if (! $this->hasTable('expenses')) {
            return $this->emptyResult('Dépenses par catégorie', $headers);
        }

        $base = DB::connection('tenant')->table('expenses')
            ->whereBetween('expenses.expense_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($base, 'expenses');
        if ($this->hasTable('expense_categories')) {
            $base->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        }
        $this->filterEquals($base, 'expenses.status', $filters['status'] ?? null, allowEmpty: false);

        $catName = $this->hasTable('expense_categories')
            ? 'MAX(expense_categories.name) as category'
            : "'—' as category";

        $grouped = (clone $base)->selectRaw("
                expenses.expense_category_id,
                {$catName},
                COUNT(expenses.id) as expense_count,
                COALESCE(SUM(expenses.amount), 0) as amount
            ")
            ->groupBy('expenses.expense_category_id');

        $totals = (clone $base)->selectRaw('
            COUNT(DISTINCT expenses.expense_category_id) as row_count,
            COUNT(expenses.id) as expense_count,
            COALESCE(SUM(expenses.amount), 0) as amount
        ')->first();

        $query = $grouped->orderByDesc('amount');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'category' => $row->category ?: 'Sans catégorie',
                'count' => (int) $row->expense_count,
                'amount' => ReportFigures::francs((float) $row->amount),
            ];
        }

        return [
            'title' => 'Dépenses par catégorie',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->expense_count ?? 0),
                'amount' => ReportFigures::francs((float) ($totals->amount ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockValue(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'sku', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Article', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'Qté physique', 'type' => 'qty'],
            ['key' => 'available', 'label' => 'Disponible', 'type' => 'qty'],
            ['key' => 'cost', 'label' => 'PU achat', 'type' => 'money'],
            ['key' => 'valeur', 'label' => 'Valeur achat', 'type' => 'money'],
            ['key' => 'price', 'label' => 'PU vente', 'type' => 'money'],
            ['key' => 'valeur_vente', 'label' => 'Valeur vente', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Statut', 'type' => 'badge'],
        ];

        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return $this->emptyResult('Valeur du stock', $headers);
        }

        $base = $this->stockBase($filters)->whereRaw('COALESCE(stock_levels.quantity, stock_levels.available_quantity, 0) <> 0');

        $totals = (clone $base)->selectRaw('
            COUNT(items.id) as row_count,
            COALESCE(SUM(stock_levels.quantity), 0) as qty,
            COALESCE(SUM(stock_levels.available_quantity), 0) as available,
            COALESCE(SUM(stock_levels.quantity * COALESCE(items.cost, 0)), 0) as valeur,
            COALESCE(SUM(stock_levels.quantity * COALESCE(items.price, 0)), 0) as valeur_vente
        ')->first();

        $select = [
            'items.sku',
            'items.name',
            'items.cost',
            'items.price',
            'stock_levels.quantity as qty',
            'stock_levels.available_quantity as available',
            'stock_levels.reorder_point as reorder',
        ];
        if ($this->hasTable('categories')) {
            $select[] = 'categories.name as category';
        }

        $query = $base->select($select)->orderByDesc(DB::raw('stock_levels.quantity * COALESCE(items.cost, 0)'));
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $qty = (float) $row->qty;
            $available = (float) $row->available;
            $cost = ReportFigures::francs((float) $row->cost);
            $price = ReportFigures::francs((float) ($row->price ?? 0));
            [$status, $label] = $this->stockStatus($available, $row->reorder ?? null);
            $mapped[] = [
                'sku' => $row->sku ?: '—',
                'name' => (string) $row->name,
                'category' => $row->category ?? 'Sans catégorie',
                'qty' => $qty,
                'available' => $available,
                'cost' => $cost,
                'valeur' => ReportFigures::francs($qty * (float) $row->cost),
                'price' => $price,
                'valeur_vente' => ReportFigures::francs($qty * (float) ($row->price ?? 0)),
                'status' => $status,
                'status_label' => $label,
            ];
        }

        return [
            'title' => 'Valeur du stock',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'qty' => (float) ($totals->qty ?? 0),
                'available' => (float) ($totals->available ?? 0),
                'valeur' => ReportFigures::francs((float) ($totals->valeur ?? 0)),
                'valeur_vente' => ReportFigures::francs((float) ($totals->valeur_vente ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockByCategory(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Articles', 'type' => 'int'],
            ['key' => 'qty', 'label' => 'Qté physique', 'type' => 'qty'],
            ['key' => 'valeur', 'label' => 'Valeur achat', 'type' => 'money'],
            ['key' => 'valeur_vente', 'label' => 'Valeur vente', 'type' => 'money'],
        ];

        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return $this->emptyResult('Stock par catégorie', $headers);
        }

        $base = $this->stockBase($filters)->whereRaw('COALESCE(stock_levels.quantity, stock_levels.available_quantity, 0) <> 0');
        $catId = $this->hasColumn('items', 'category_id') ? 'items.category_id' : 'NULL';
        $catName = $this->hasTable('categories') ? 'MAX(categories.name) as category' : "'Sans catégorie' as category";

        $grouped = (clone $base)->selectRaw("
                {$catId} as category_id,
                {$catName},
                COUNT(items.id) as item_count,
                COALESCE(SUM(stock_levels.quantity), 0) as qty,
                COALESCE(SUM(stock_levels.quantity * COALESCE(items.cost, 0)), 0) as valeur,
                COALESCE(SUM(stock_levels.quantity * COALESCE(items.price, 0)), 0) as valeur_vente
            ")
            ->groupByRaw($catId);

        $totals = (clone $base)->selectRaw("
            COUNT(DISTINCT {$catId}) as row_count,
            COUNT(items.id) as item_count,
            COALESCE(SUM(stock_levels.quantity), 0) as qty,
            COALESCE(SUM(stock_levels.quantity * COALESCE(items.cost, 0)), 0) as valeur,
            COALESCE(SUM(stock_levels.quantity * COALESCE(items.price, 0)), 0) as valeur_vente
        ")->first();

        $query = $grouped->orderByDesc('valeur');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'category' => $row->category ?: 'Sans catégorie',
                'count' => (int) $row->item_count,
                'qty' => (float) $row->qty,
                'valeur' => ReportFigures::francs((float) $row->valeur),
                'valeur_vente' => ReportFigures::francs((float) $row->valeur_vente),
            ];
        }

        return [
            'title' => 'Stock par catégorie',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'count' => (int) ($totals->item_count ?? 0),
                'qty' => (float) ($totals->qty ?? 0),
                'valeur' => ReportFigures::francs((float) ($totals->valeur ?? 0)),
                'valeur_vente' => ReportFigures::francs((float) ($totals->valeur_vente ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockAlert(array $filters, int $page, int $perPage, bool $export, string $mode): array
    {
        $headers = [
            ['key' => 'sku', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Article', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'Disponible', 'type' => 'qty'],
            ['key' => 'reorder', 'label' => 'Seuil', 'type' => 'qty'],
            ['key' => 'cost', 'label' => 'PU achat', 'type' => 'money'],
            ['key' => 'valeur', 'label' => 'Valeur achat', 'type' => 'money'],
        ];
        $title = $mode === 'out' ? 'Ruptures de stock' : 'Stock faible';

        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return $this->emptyResult($title, $headers);
        }

        $base = $this->stockBase($filters);
        if ($mode === 'out') {
            $base->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= 0');
        } else {
            $base->whereNotNull('stock_levels.reorder_point')
                ->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= stock_levels.reorder_point')
                ->whereRaw('COALESCE(stock_levels.available_quantity, 0) > 0');
        }

        $totals = (clone $base)->selectRaw('
            COUNT(items.id) as row_count,
            COALESCE(SUM(stock_levels.available_quantity), 0) as qty,
            COALESCE(SUM(COALESCE(stock_levels.quantity, stock_levels.available_quantity, 0) * COALESCE(items.cost, 0)), 0) as valeur
        ')->first();

        $select = [
            'items.sku',
            'items.name',
            'items.cost',
            'stock_levels.available_quantity as qty',
            'stock_levels.quantity as on_hand',
            'stock_levels.reorder_point as reorder',
        ];
        if ($this->hasTable('categories')) {
            $select[] = 'categories.name as category';
        }

        $query = $base->select($select)->orderBy('stock_levels.available_quantity');
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $onHand = (float) ($row->on_hand ?? $row->qty);
            $mapped[] = [
                'sku' => $row->sku ?: '—',
                'name' => (string) $row->name,
                'category' => $row->category ?? 'Sans catégorie',
                'qty' => (float) $row->qty,
                'reorder' => $row->reorder !== null ? (float) $row->reorder : null,
                'cost' => ReportFigures::francs((float) $row->cost),
                'valeur' => ReportFigures::francs($onHand * (float) $row->cost),
            ];
        }

        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'qty' => (float) ($totals->qty ?? 0),
                'valeur' => ReportFigures::francs((float) ($totals->valeur ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockDormant(array $filters, int $page, int $perPage, bool $export): array
    {
        $headers = [
            ['key' => 'sku', 'label' => 'Référence', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Article', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Catégorie', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'Qté physique', 'type' => 'qty'],
            ['key' => 'cost', 'label' => 'PU achat', 'type' => 'money'],
            ['key' => 'valeur', 'label' => 'Valeur achat', 'type' => 'money'],
        ];

        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return $this->emptyResult('Stock sans mouvement', $headers);
        }

        $base = $this->stockBase($filters)
            ->whereRaw('COALESCE(stock_levels.quantity, stock_levels.available_quantity, 0) > 0');

        if ($this->hasTable('invoice_lines') && $this->hasTable('invoices')) {
            $base->whereNotExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('invoice_lines')
                    ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
                    ->whereColumn('invoice_lines.item_id', 'items.id')
                    ->whereBetween('invoices.invoice_date', [$filters['from'], $filters['to']])
                    ->whereNotIn('invoices.status', ['draft', 'cancelled', 'superseded']);
            });
        }
        if ($this->hasTable('sale_lines') && $this->hasTable('sales')) {
            $base->whereNotExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('sale_lines')
                    ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
                    ->whereColumn('sale_lines.item_id', 'items.id')
                    ->whereBetween('sales.sale_date', [$filters['from'], $filters['to']]);
            });
        }

        $totals = (clone $base)->selectRaw('
            COUNT(items.id) as row_count,
            COALESCE(SUM(stock_levels.quantity), 0) as qty,
            COALESCE(SUM(stock_levels.quantity * COALESCE(items.cost, 0)), 0) as valeur
        ')->first();

        $select = [
            'items.sku',
            'items.name',
            'items.cost',
            'stock_levels.quantity as qty',
        ];
        if ($this->hasTable('categories')) {
            $select[] = 'categories.name as category';
        }

        $query = $base->select($select)->orderByDesc(DB::raw('stock_levels.quantity * COALESCE(items.cost, 0)'));
        [$rows, $paginator, $truncated] = $this->page($query, (int) ($totals->row_count ?? 0), $page, $perPage, $export);

        $mapped = [];
        foreach ($rows as $row) {
            $qty = (float) $row->qty;
            $mapped[] = [
                'sku' => $row->sku ?: '—',
                'name' => (string) $row->name,
                'category' => $row->category ?? 'Sans catégorie',
                'qty' => $qty,
                'cost' => ReportFigures::francs((float) $row->cost),
                'valeur' => ReportFigures::francs($qty * (float) $row->cost),
            ];
        }

        return [
            'title' => 'Stock sans mouvement',
            'headers' => $headers,
            'rows' => $mapped,
            'totals' => [
                'qty' => (float) ($totals->qty ?? 0),
                'valeur' => ReportFigures::francs((float) ($totals->valeur ?? 0)),
            ],
            'paginator' => $paginator,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function postedInvoicesQuery(array $filters): Builder
    {
        $query = DB::connection('tenant')->table('invoices')
            ->whereBetween('invoices.invoice_date', [$filters['from'], $filters['to']])
            ->whereNotIn('invoices.status', ['draft', 'cancelled', 'superseded']);

        if ($this->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        $this->applyStoreFilter($query, 'invoices');
        $this->filterEquals($query, 'invoices.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'invoices.client_id', $filters['client_id'] ?? null);
        $this->filterEquals($query, 'invoices.created_by', $filters['user_id'] ?? null);
        $this->filterEquals($query, 'invoices.status', $filters['status'] ?? null, allowEmpty: false);
        $this->joinVatAggregate($query);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoicesInPeriodQuery(array $filters): Builder
    {
        $query = DB::connection('tenant')->table('invoices')
            ->whereBetween('invoices.invoice_date', [$filters['from'], $filters['to']]);

        if ($this->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        $this->applyStoreFilter($query, 'invoices');
        $this->filterEquals($query, 'invoices.store_id', $filters['store_id'] ?? null);
        $this->joinVatAggregate($query);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function openInvoicesQuery(array $filters, bool $inPeriod): Builder
    {
        $query = DB::connection('tenant')->table('invoices')
            ->whereIn('invoices.status', ['issued', 'partial']);

        if ($this->hasColumn('invoices', 'balance')) {
            $query->where('invoices.balance', '>', 0.01);
        }
        if ($inPeriod) {
            $query->whereBetween('invoices.invoice_date', [$filters['from'], $filters['to']]);
        }
        if ($this->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        $this->applyStoreFilter($query, 'invoices');
        $this->filterEquals($query, 'invoices.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'invoices.client_id', $filters['client_id'] ?? null);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentsBase(array $filters): Builder
    {
        $base = DB::connection('tenant')->table('invoice_payments')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->whereBetween('invoice_payments.payment_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($base, 'invoices');
        $this->filterEquals($base, 'invoices.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($base, 'invoices.client_id', $filters['client_id'] ?? null);
        if ($this->hasColumn('invoice_payments', 'status')) {
            $base->where('invoice_payments.status', '!=', 'cancelled');
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function quotationsBase(array $filters): Builder
    {
        $query = DB::connection('tenant')->table('quotations')
            ->whereBetween('quotations.quote_date', [$filters['from'], $filters['to']]);
        $this->applyStoreFilter($query, 'quotations');
        $this->filterEquals($query, 'quotations.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'quotations.client_id', $filters['client_id'] ?? null);

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'accepted') {
            $query->whereIn('quotations.status', ['accepted', 'validated']);
        } else {
            $this->filterEquals($query, 'quotations.status', $status, allowEmpty: false);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockBase(array $filters): Builder
    {
        $query = DB::connection('tenant')->table('items')
            ->join('stock_levels', 'stock_levels.item_id', '=', 'items.id');

        if ($this->hasTable('categories') && $this->hasColumn('items', 'category_id')) {
            $query->leftJoin('categories', 'categories.id', '=', 'items.category_id');
        }

        $this->applyStoreFilter($query, 'stock_levels');
        $this->filterEquals($query, 'stock_levels.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'items.category_id', $filters['category_id'] ?? null);
        $this->filterEquals($query, 'items.id', $filters['item_id'] ?? null);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function productLineUnion(array $filters): ?Builder
    {
        $parts = array_values(array_filter([
            $this->invoiceProductAgg($filters),
            $this->salesProductAgg($filters),
        ]));

        if ($parts === []) {
            return null;
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return $union;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoiceProductAgg(array $filters): ?Builder
    {
        if (! $this->hasTable('invoice_lines') || ! $this->hasTable('invoices')) {
            return null;
        }

        $itemKey = "CASE WHEN invoice_lines.item_id IS NOT NULL THEN CONCAT('i:', invoice_lines.item_id) ELSE CONCAT('n:', invoice_lines.item_name) END";
        $categoryKey = $this->hasColumn('items', 'category_id')
            ? "CASE WHEN items.category_id IS NOT NULL THEN CONCAT('c:', items.category_id) ELSE 'c:0' END"
            : "'c:0'";
        $categoryName = $this->hasTable('categories')
            ? "MAX(COALESCE(categories.name, 'Sans catégorie'))"
            : "'Sans catégorie'";
        $skuExpr = $this->hasTable('items')
            ? "MAX(COALESCE(invoice_lines.item_sku, items.sku, ''))"
            : "MAX(COALESCE(invoice_lines.item_sku, ''))";
        $nameExpr = $this->hasTable('items')
            ? "MAX(COALESCE(invoice_lines.item_name, items.name, 'Sans article'))"
            : "MAX(COALESCE(invoice_lines.item_name, 'Sans article'))";
        $costSql = $this->invoiceLineCostSql();

        $query = DB::connection('tenant')->table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->whereBetween('invoices.invoice_date', [$filters['from'], $filters['to']])
            ->whereNotIn('invoices.status', ['draft', 'cancelled', 'superseded']);

        if ($this->hasColumn('invoices', 'document_type')) {
            $query->where(function ($q) {
                $q->whereNull('invoices.document_type')
                    ->orWhere('invoices.document_type', '!=', 'cancellation');
            });
        }

        if ($this->hasTable('items')) {
            $query->leftJoin('items', 'items.id', '=', 'invoice_lines.item_id');
        }
        if ($this->hasTable('categories') && $this->hasColumn('items', 'category_id')) {
            $query->leftJoin('categories', 'categories.id', '=', 'items.category_id');
        }

        $this->applyStoreFilter($query, 'invoices');
        $this->filterEquals($query, 'invoices.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'invoices.client_id', $filters['client_id'] ?? null);
        $this->filterEquals($query, 'invoice_lines.item_id', $filters['item_id'] ?? null);
        if ($this->hasTable('items')) {
            $this->filterEquals($query, 'items.category_id', $filters['category_id'] ?? null);
        }

        return $query->selectRaw("
                {$itemKey} as item_key,
                {$categoryKey} as category_key,
                {$skuExpr} as sku,
                {$nameExpr} as name,
                {$categoryName} as category,
                COALESCE(SUM(invoice_lines.quantity), 0) as qty,
                COALESCE(SUM(invoice_lines.line_total), 0) as revenue,
                COALESCE(SUM({$costSql}), 0) as cost,
                COUNT(DISTINCT invoices.id) as docs
            ")
            ->groupByRaw("{$itemKey}, {$categoryKey}");
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesProductAgg(array $filters): ?Builder
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales')) {
            return null;
        }

        $itemKey = "CASE WHEN sale_lines.item_id IS NOT NULL THEN CONCAT('i:', sale_lines.item_id) ELSE CONCAT('n:', sale_lines.item_name) END";
        $categoryKey = $this->hasColumn('items', 'category_id')
            ? "CASE WHEN items.category_id IS NOT NULL THEN CONCAT('c:', items.category_id) ELSE 'c:0' END"
            : "'c:0'";
        $categoryName = $this->hasTable('categories')
            ? "MAX(COALESCE(categories.name, 'Sans catégorie'))"
            : "'Sans catégorie'";
        $costSql = $this->saleLineCostSql();

        $query = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereBetween('sales.sale_date', [$filters['from'], $filters['to']]);

        if ($this->hasTable('items')) {
            $query->leftJoin('items', 'items.id', '=', 'sale_lines.item_id');
        }
        if ($this->hasTable('categories') && $this->hasColumn('items', 'category_id')) {
            $query->leftJoin('categories', 'categories.id', '=', 'items.category_id');
        }
        if ($this->hasTable('item_unit_prices') && $this->hasColumn('sale_lines', 'unit_id')) {
            $query->leftJoin('item_unit_prices', function ($join) {
                $join->on('sale_lines.item_id', '=', 'item_unit_prices.item_id')
                    ->on('sale_lines.unit_id', '=', 'item_unit_prices.unit_id');
            });
        }

        $this->applyStoreFilter($query, 'sales');
        $this->filterEquals($query, 'sales.store_id', $filters['store_id'] ?? null);
        $this->filterEquals($query, 'sales.client_id', $filters['client_id'] ?? null);
        $this->filterEquals($query, 'sale_lines.item_id', $filters['item_id'] ?? null);
        if ($this->hasTable('items')) {
            $this->filterEquals($query, 'items.category_id', $filters['category_id'] ?? null);
        }

        return $query->selectRaw("
                {$itemKey} as item_key,
                {$categoryKey} as category_key,
                MAX(COALESCE(sale_lines.item_sku, items.sku, '')) as sku,
                MAX(COALESCE(sale_lines.item_name, items.name, 'Sans article')) as name,
                {$categoryName} as category,
                COALESCE(SUM(sale_lines.quantity), 0) as qty,
                COALESCE(SUM(sale_lines.line_total), 0) as revenue,
                COALESCE(SUM({$costSql}), 0) as cost,
                COUNT(DISTINCT sales.id) as docs
            ")
            ->groupByRaw("{$itemKey}, {$categoryKey}");
    }

    private function invoiceLineCostSql(): string
    {
        $itemCost = $this->hasTable('items') ? 'COALESCE(items.cost, 0)' : '0';
        if ($this->hasColumn('invoice_lines', 'unit_cost')) {
            return "invoice_lines.quantity * COALESCE(invoice_lines.unit_cost, {$itemCost})";
        }

        return "invoice_lines.quantity * {$itemCost}";
    }

    private function saleLineCostSql(): string
    {
        $factor = $this->hasColumn('sale_lines', 'conversion_factor')
            ? 'COALESCE(sale_lines.conversion_factor, 1)'
            : '1';
        $itemCost = $this->hasTable('items') ? 'COALESCE(items.cost, 0)' : '0';

        if ($this->hasTable('item_unit_prices') && $this->hasColumn('sale_lines', 'unit_id')) {
            return "CASE WHEN item_unit_prices.id IS NOT NULL THEN sale_lines.quantity * COALESCE(item_unit_prices.cost, 0) ELSE sale_lines.quantity * {$factor} * {$itemCost} END";
        }

        return "sale_lines.quantity * {$factor} * {$itemCost}";
    }

    private function daysOverdueSql(string $asOf): string
    {
        $date = Carbon::parse($asOf)->toDateString();
        $due = $this->hasColumn('invoices', 'due_date')
            ? 'COALESCE(invoices.due_date, invoices.invoice_date)'
            : 'invoices.invoice_date';

        if (DB::connection('tenant')->getDriverName() === 'pgsql') {
            return "('{$date}'::date - {$due})";
        }

        return "DATEDIFF('{$date}', {$due})";
    }

    private function agingBucketSql(string $asOf, string $daysSql): string
    {
        $date = Carbon::parse($asOf)->toDateString();
        $due = $this->hasColumn('invoices', 'due_date')
            ? 'COALESCE(invoices.due_date, invoices.invoice_date)'
            : 'invoices.invoice_date';

        return "CASE
            WHEN {$due} >= '{$date}' THEN 'À échoir'
            WHEN {$daysSql} <= 30 THEN '1–30 j'
            WHEN {$daysSql} <= 60 THEN '31–60 j'
            WHEN {$daysSql} <= 90 THEN '61–90 j'
            ELSE '+90 j'
        END";
    }

    private function quotationStatusLabel(string $status): string
    {
        if (class_exists(\InovCom\Quotations\Models\Quotation::class)) {
            return \InovCom\Quotations\Models\Quotation::statusLabel($status);
        }

        return match ($status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted', 'validated' => 'Accepté',
            'suspended' => 'Suspendu',
            'rejected' => 'Rejeté',
            default => $status,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function stockStatus(float $available, mixed $reorder): array
    {
        if ($available <= 0) {
            return ['out', 'Rupture'];
        }
        if ($reorder !== null && $available <= (float) $reorder) {
            return ['low', 'Faible'];
        }

        return ['ok', 'OK'];
    }

    private function joinVatAggregate(Builder $query): void
    {
        if (! $this->hasTable('invoice_tax_lines')) {
            return;
        }

        $sub = DB::connection('tenant')
            ->table('invoice_tax_lines')
            ->select('invoice_id', DB::raw('SUM(tax_amount) as vat'))
            ->where(function ($q) {
                $q->whereRaw('LOWER(tax_name) LIKE ?', ['%tva%'])
                    ->orWhereRaw('LOWER(tax_name) LIKE ?', ['%vat%']);
            });

        if ($this->hasColumn('invoice_tax_lines', 'tax_effect')) {
            $sub->where(function ($q) {
                $q->whereNull('tax_effect')->orWhere('tax_effect', 'add');
            });
        }

        $query->leftJoinSub($sub->groupBy('invoice_id'), 'vat_lines', 'vat_lines.invoice_id', '=', 'invoices.id');
    }

    private function invoiceVatSelect(): string
    {
        if ($this->hasTable('invoice_tax_lines')) {
            $fallback = $this->hasColumn('invoices', 'tax_amount')
                ? 'GREATEST(0, COALESCE(invoices.tax_amount, 0))'
                : '0';

            return "COALESCE(vat_lines.vat, {$fallback})";
        }

        return $this->hasColumn('invoices', 'tax_amount')
            ? 'GREATEST(0, COALESCE(invoices.tax_amount, 0))'
            : '0';
    }

    private function joinClient(Builder $query, string $fk): void
    {
        if ($this->hasTable('clients')) {
            $query->leftJoin('clients', 'clients.id', '=', $fk);
        }
    }

    private function joinStore(Builder $query, string $fk): void
    {
        if ($this->hasTable('stores') && $this->hasColumn(explode('.', $fk)[0], 'store_id')) {
            $query->leftJoin('stores', 'stores.id', '=', $fk);
        }
    }

    private function joinUser(Builder $query, string $fk): void
    {
        $table = explode('.', $fk)[0];
        if ($this->hasTable('users') && $this->hasColumn($table, 'created_by')) {
            $query->leftJoin('users', 'users.id', '=', $fk);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function constrainItemOn(string $parentId, string $linesTable, string $fk, Builder $query, array $filters): void
    {
        $itemId = $filters['item_id'] ?? null;
        if (! $itemId || ! $this->hasTable($linesTable)) {
            return;
        }

        $query->whereExists(function ($sub) use ($parentId, $linesTable, $fk, $itemId) {
            $sub->select(DB::raw(1))
                ->from($linesTable)
                ->whereColumn("{$linesTable}.{$fk}", $parentId)
                ->where("{$linesTable}.item_id", (int) $itemId);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function constrainAmount(Builder $query, string $column, array $filters): void
    {
        $min = $filters['amount_min'] ?? null;
        $max = $filters['amount_max'] ?? null;
        if ($min !== null && $min !== '') {
            $query->where($column, '>=', (float) $min);
        }
        if ($max !== null && $max !== '') {
            $query->where($column, '<=', (float) $max);
        }
    }

    private function filterEquals(Builder $query, string $column, mixed $value, bool $allowEmpty = true): void
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return;
        }
        if (! $allowEmpty && $value === '') {
            return;
        }
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            if (! $this->hasColumn($table, $col)) {
                return;
            }
        }
        $query->where($column, $value);
    }

    private function applyStoreFilter(Builder $query, string $table): void
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        if ($storeId && $this->hasColumn($table, 'store_id')) {
            $query->where("{$table}.store_id", $storeId);
        }
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function sortColumn(string $requested, array $map, string $default)
    {
        return $map[$requested] ?? $default;
    }

    private function sortDir(string $dir): string
    {
        return strtolower($dir) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: LengthAwarePaginator, 2: bool}
     */
    private function page(Builder $query, int $total, int $page, int $perPage, bool $export): array
    {
        $truncated = false;
        if ($export) {
            if ($total > self::EXPORT_MAX) {
                $truncated = true;
            }
            $rows = $query->limit(self::EXPORT_MAX)->get();
            $paginator = $this->wrapPage($rows->all(), $total, 1, self::EXPORT_MAX);

            return [$rows, $paginator, $truncated];
        }

        $rows = $query->forPage($page, $perPage)->get();
        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return [$rows, $paginator, false];
    }

    private function wrapPage(array $items, int $total, int $page, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $headers
     * @return array<string, mixed>
     */
    private function emptyResult(string $title, array $headers = []): array
    {
        $paginator = $this->wrapPage([], 0, 1, 25);

        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => [],
            'totals' => [],
            'paginator' => $paginator,
            'truncated' => false,
        ];
    }

    private function invoiceStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Payée',
            'issued' => 'Impayée',
            'partial' => 'Partielle',
            'draft' => 'En attente',
            default => class_exists(Invoice::class) ? Invoice::statusLabel($status) : $status,
        };
    }

    private function hasTable(string $table): bool
    {
        return Schema::connection('tenant')->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::connection('tenant')->hasColumn($table, $column);
    }

    /**
     * @return array<string, bool>
     */
    public function availableModules(): array
    {
        return [
            'invoicing' => $this->hasTable('invoices'),
            'sales' => $this->hasTable('sales'),
            'payments' => $this->hasTable('invoice_payments'),
            'quotations' => $this->hasTable('quotations'),
            'purchases' => $this->hasTable('purchase_orders'),
            'expenses' => $this->hasTable('expenses'),
            'stock' => $this->hasTable('stock_levels'),
        ];
    }
}
