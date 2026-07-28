<?php

namespace InovCom\Clients\Concerns;

use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientDocument;
use InovCom\Clients\Models\ClientNote;
use InovCom\Clients\Exports\ClientProductHistoryExporter;
use InovCom\Clients\Services\ClientCreditService;
use InovCom\Clients\Services\ClientProductHistoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesClientWorkspace
{
    public Client $client;

    public string $activeHistoryTab = 'quotations';

    public string $active360Section = 'activity';

    public string $productRefSearch = '';

    public ?string $productDateFrom = null;

    public ?string $productDateTo = null;

    /** @var array<int, array<string, mixed>> */
    public array $productHistoryResults = [];

    public string $newNoteBody = '';

    public string $newNoteType = 'note';

    public $newDocument = null;

    public string $newDocLabel = '';

    public string $newDocType = 'other';

    public function mountClientWorkspace(Client $client): void
    {
        $this->loadClient($client);

        app(ClientCreditService::class)->evaluate($this->client);
        $this->client->refresh();
    }

    public function toggleBlock(): void
    {
        if (! $this->can('clients.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $credit = app(ClientCreditService::class);
        $userId = Auth::guard('tenant')->id();

        if ($this->client->is_blocked) {
            $credit->unblock($this->client, $userId);
            session()->flash('success', 'Client débloqué.');
        } else {
            $credit->block($this->client, 'Blocage manuel', $userId);
            session()->flash('success', 'Client bloqué.');
        }

        $this->loadClient($this->client->fresh());
    }

    public function addNote(): void
    {
        if (! $this->can('clients.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'newNoteBody' => 'required|string|max:2000',
            'newNoteType' => 'required|in:' . implode(',', array_keys(ClientNote::TYPES)),
        ]);

        ClientNote::create([
            'client_id' => $this->client->id,
            'body' => $data['newNoteBody'],
            'type' => $data['newNoteType'],
            'author_id' => Auth::guard('tenant')->id(),
        ]);

        $this->newNoteBody = '';
        $this->newNoteType = 'note';
        session()->flash('success', 'Note ajoutée.');
    }

    public function deleteNote(int $noteId): void
    {
        if (! $this->can('clients.update')) {
            return;
        }

        ClientNote::where('client_id', $this->client->id)->whereKey($noteId)->delete();
    }

    public function uploadDocument(): void
    {
        if (! $this->can('clients.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->validate([
            'newDocument' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            'newDocLabel' => 'nullable|string|max:255',
            'newDocType' => 'required|in:' . implode(',', array_keys(ClientDocument::TYPES)),
        ]);

        $directory = $this->documentDirectory();
        Storage::disk('public')->makeDirectory($directory);

        $extension = $this->newDocument->getClientOriginalExtension();
        $filename = Str::random(24) . '.' . $extension;
        $path = $this->newDocument->storeAs($directory, $filename, 'public');

        ClientDocument::create([
            'client_id' => $this->client->id,
            'type' => $this->newDocType,
            'label' => $this->newDocLabel ?: $this->newDocument->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $this->newDocument->getMimeType(),
            'size_bytes' => $this->newDocument->getSize(),
            'uploaded_by' => Auth::guard('tenant')->id(),
        ]);

        $this->reset(['newDocument', 'newDocLabel']);
        $this->newDocType = 'other';
        session()->flash('success', 'Document ajouté.');
    }

    public function deleteDocument(int $documentId): void
    {
        if (! $this->can('clients.update')) {
            return;
        }

        $document = ClientDocument::where('client_id', $this->client->id)->find($documentId);
        if (! $document) {
            return;
        }

        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }
        $document->delete();
        session()->flash('success', 'Document supprimé.');
    }

    public function setHistoryTab(string $tab): void
    {
        $this->activeHistoryTab = $tab;
        $this->active360Section = 'activity';
    }

    public function set360Section(string $section): void
    {
        $allowed = ['finances', 'activity', 'contacts', 'documents', 'journal'];
        if (in_array($section, $allowed, true)) {
            $this->active360Section = $section;
        }
    }

    public function searchProductHistory(): void
    {
        if (! $this->can('clients.view')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->validate([
            'productRefSearch' => 'required|string|min:1|max:60',
            'productDateFrom' => 'nullable|date',
            'productDateTo' => 'nullable|date|after_or_equal:productDateFrom',
        ], [
            'productRefSearch.required' => 'Indiquez la référence produit (SKU).',
            'productDateTo.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);

        $this->productHistoryResults = app(ClientProductHistoryService::class)->search(
            $this->client,
            $this->productRefSearch,
            $this->productDateFrom,
            $this->productDateTo
        );

        $this->activeHistoryTab = 'product_ref';
        $this->active360Section = 'activity';

        if ($this->productHistoryResults === []) {
            session()->flash('success', 'Aucune ligne trouvée pour cette référence.');
        }
    }

    public function exportProductHistory()
    {
        if (! $this->can('clients.view')) {
            session()->flash('error', 'Permission refusée.');
            return null;
        }

        $sku = trim($this->productRefSearch);
        if ($sku === '') {
            session()->flash('error', 'Effectuez d\'abord une recherche par référence produit.');
            return null;
        }

        $rows = $this->productHistoryResults;
        if ($rows === []) {
            $rows = app(ClientProductHistoryService::class)->search(
                $this->client,
                $sku,
                $this->productDateFrom,
                $this->productDateTo
            );
        }

        if ($rows === []) {
            session()->flash('error', 'Aucune donnée à exporter pour cette recherche.');
            return null;
        }

        $safeSku = preg_replace('/[^A-Za-z0-9_-]+/', '-', $sku) ?: 'produit';

        return ClientProductHistoryExporter::download(
            'historique-produit-' . $this->client->code . '-' . $safeSku . '-' . now()->format('Y-m-d') . '.xls',
            $this->client,
            $sku,
            $rows,
            $this->productDateFrom,
            $this->productDateTo
        );
    }

    protected function loadClient(Client $client): void
    {
        $this->client = $client->load([
            'segment',
            'zone',
            'category',
            'contacts' => fn ($q) => $q->orderByDesc('is_primary'),
            'addresses',
        ]);
    }

    protected function documentDirectory(): string
    {
        $code = Str::slug($this->tenantCode() ?: 'tenant', '_');

        return 'tenants/' . $code . '/clients/' . $this->client->id . '/documents';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStats(): array
    {
        $stats = [
            'totalSales' => 0.0,
            'salesCount' => 0,
            'lastSaleAt' => null,
            'outstanding' => 0.0,
            'openDebts' => 0,
            'invoicesModule' => false,
            'invoicedTotal' => 0.0,
            'invoicedPaid' => 0.0,
            'invoicedUnpaid' => 0.0,
            'invoiceCount' => 0,
            'unpaidInvoiceCount' => 0,
            'quotationCount' => 0,
            'quotationTotal' => 0.0,
            'clientCredit' => 0.0,
        ];

        if (Schema::connection('tenant')->hasTable('sales')) {
            $q = DB::connection('tenant')->table('sales')->where('client_id', $this->client->id);
            $stats['totalSales'] = (float) $q->sum('total');
            $stats['salesCount'] = (int) $q->count();
            $stats['lastSaleAt'] = $q->max('created_at');
        }

        if (Schema::connection('tenant')->hasTable('invoices')) {
            $base = DB::connection('tenant')->table('invoices')
                ->where('client_id', $this->client->id)
                ->whereIn('status', ['issued', 'partial', 'paid']);
            $stats['invoicesModule'] = true;
            $stats['invoicedTotal'] = (float) (clone $base)->sum('total');
            $stats['invoicedPaid'] = (float) (clone $base)->sum('amount_paid');
            $stats['invoicedUnpaid'] = (float) (clone $base)->where('balance', '>', 0)->sum('balance');
            $stats['invoiceCount'] = (int) (clone $base)->count();
            $stats['unpaidInvoiceCount'] = (int) (clone $base)->where('balance', '>', 0)->count();
        }

        if (Schema::connection('tenant')->hasTable('quotations')) {
            $q = DB::connection('tenant')->table('quotations')
                ->where('client_id', $this->client->id)
                ->where('status', '!=', 'cancelled');
            $stats['quotationCount'] = (int) $q->count();
            $stats['quotationTotal'] = (float) $q->sum('total');
        }

        if (Schema::connection('tenant')->hasTable('debts')) {
            $q = DB::connection('tenant')->table('debts')->where('client_id', $this->client->id)->where('balance', '>', 0);
            $stats['outstanding'] = (float) $q->sum('balance');
            $stats['openDebts'] = (int) $q->count();
        }

        if (Schema::connection('tenant')->hasTable('credit_notes')) {
            $stats['clientCredit'] += (float) DB::connection('tenant')->table('credit_notes')
                ->where('client_id', $this->client->id)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('remaining_amount');
        }

        if (Schema::connection('tenant')->hasTable('customer_credits')) {
            $credit = (float) DB::connection('tenant')->table('customer_credits')
                ->where('client_id', $this->client->id)->where('direction', 'credit')->sum('amount');
            $debit = (float) DB::connection('tenant')->table('customer_credits')
                ->where('client_id', $this->client->id)->where('direction', 'debit')->sum('amount');
            $stats['clientCredit'] += round($credit - $debit, 2);
        }

        return $stats;
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, object>>
     */
    protected function buildHistory(): array
    {
        $history = [
            'sales' => collect(),
            'quotations' => collect(),
            'invoices' => collect(),
            'debts' => collect(),
            'creditNotes' => collect(),
        ];

        if (Schema::connection('tenant')->hasTable('sales')) {
            $history['sales'] = DB::connection('tenant')->table('sales')
                ->where('client_id', $this->client->id)
                ->orderByDesc('sale_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if (Schema::connection('tenant')->hasTable('quotations')) {
            $history['quotations'] = DB::connection('tenant')->table('quotations')
                ->where('client_id', $this->client->id)
                ->orderByDesc('quote_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if (Schema::connection('tenant')->hasTable('invoices')) {
            $history['invoices'] = DB::connection('tenant')->table('invoices')
                ->where('client_id', $this->client->id)
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if (Schema::connection('tenant')->hasTable('debts')) {
            $history['debts'] = DB::connection('tenant')->table('debts')
                ->where('client_id', $this->client->id)
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if (Schema::connection('tenant')->hasTable('credit_notes')) {
            $history['creditNotes'] = DB::connection('tenant')->table('credit_notes')
                ->where('client_id', $this->client->id)
                ->whereNull('deleted_at')
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return $history;
    }

    protected function paymentTerm(): ?object
    {
        if (! $this->client->payment_term_id || ! Schema::connection('tenant')->hasTable('payment_terms')) {
            return null;
        }

        return DB::connection('tenant')->table('payment_terms')->find($this->client->payment_term_id);
    }

    protected function salesrep(): ?object
    {
        if (! $this->client->salesrep_id || ! Schema::connection('tenant')->hasTable('users')) {
            return null;
        }

        return DB::connection('tenant')->table('users')->find($this->client->salesrep_id);
    }

    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
