<?php

namespace InovCom\Devis\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Devis\Models\Quote;
use InovCom\Devis\Models\QuoteLine;
use InovCom\Devis\Services\CreateInvoiceFromQuoteService;
use InovCom\Devis\Services\QuoteDuplicationService;
use InovCom\Devis\Services\QuoteImportService;
use InovCom\Devis\Services\QuoteSourceArchiveService;
use InovCom\Devis\Support\QuoteRefuseReasons;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Offres\Models\Offer;
use InovCom\Facturation\Models\Invoice;
use InovCom\Projets\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuoteForm extends Component
{
    use AuthorizesWithTenant, WithFileUploads;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int    $quoteId      = null;
    public int     $quoteVersion = 1;
    public string  $code         = '';

    // ── Fields ────────────────────────────────────────────────────────
    public int     $offer_id   = 0;
    public int     $client_id  = 0;
    public string  $title      = '';
    public string  $status     = 'draft';
    public ?string $valid_until = null;
    public string  $notes      = '';
    public string  $internal_notes = '';
    public string  $terms      = '';
    public string  $currency   = '';

    // ── Financial ─────────────────────────────────────────────────────
    public string $tax_rate        = '0';        // % e.g. "18"
    public string $discount_percent = '0';       // % global discount

    // ── Line items ────────────────────────────────────────────────────
    /** @var array<int, array{item_id:int|null,description:string,quantity:string,unit:string,unit_price:string,discount_percent:string,cost:string,amount:string,line_type:string}> */
    public array $lines = [];

    // ── Refusal ───────────────────────────────────────────────────────
    public bool   $showRefuseModal = false;
    public string $refuse_category  = '';
    public string $refuse_comment   = '';

    // ── Excel import wizard ───────────────────────────────────────────
    public bool   $showImportModal   = false;
    public int    $importStep        = 1;
    public        $importFile        = null;
    public int    $importHeaderRow   = 1;
    /** @var array<int, string> */
    public array  $importHeaders     = [];
    /** @var array<string, int|null> */
    public array  $importMapping     = [];
    /** @var array<int, array<int, mixed>> */
    public array  $importRawRows      = [];
    /** @var list<array<string, mixed>> */
    public array  $importPreviewLines = [];
    public int    $importSkippedRows  = 0;
    public int    $importTotalLines   = 0;
    /** @var list<string> */
    public array  $importWarnings     = [];
    public string $importMode         = 'replace';

    // ── Quick import (drag & drop) ────────────────────────────────────
    public        $dropImportFile         = null;
    public        $pendingSourceFile      = null;
    public ?string $pendingSourceFilename = null;
    public bool   $highlightDropZone      = false;
    public int    $documentsRefreshKey     = 0;

    // ── Item catalog picker ───────────────────────────────────────────
    public bool   $showItemPicker = false;
    public string $itemSearch     = '';

    // ── Client / offer pickers (search on demand) ─────────────────────
    public bool    $showClientPicker = false;
    public string  $clientSearch     = '';
    public array   $clientResults    = [];
    public ?string $clientLabel      = null;

    public bool    $showOfferPicker = false;
    public string  $offerSearch     = '';
    public array   $offerResults    = [];
    public ?string $offerLabel      = null;

    // ── Lines table UI ────────────────────────────────────────────────
    public bool $showLineTypeColumn = false;

    // ── Computed (read-only display) ──────────────────────────────────
    public float $computed_total_ht    = 0;
    public float $computed_discount    = 0;
    public float $computed_net_ht      = 0;
    public float $computed_tax         = 0;
    public float $computed_total_ttc   = 0;
    public float $computed_margin_pct  = 0;

    public function mount(?Quote $quote = null): void
    {
        $this->tenantAuthorize('devis.view');

        // Defaults from tenant settings
        $tenant = app(TenantManager::class)->tenant();
        $this->currency = config('inovcom.currency', 'XOF');
        $this->tax_rate = (string) ($tenant?->getSetting('tax_rate', '0') ?? '0');

        if ($quote && $quote->exists) {
            if (!$quote->isEditable()) {
                $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
                $this->redirect(
                    route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quote->id]),
                    navigate: true
                );
                return;
            }

            $this->quoteId        = $quote->id;
            $this->quoteVersion   = (int) ($quote->version ?? 1);
            $this->code           = $quote->code;
            $this->offer_id       = $quote->offer_id ?? 0;
            $this->client_id      = $quote->client_id;
            $this->title          = $quote->title;
            $this->status         = $quote->status;
            $this->valid_until    = $quote->valid_until?->format('Y-m-d');
            $this->notes          = $quote->notes ?? '';
            $this->internal_notes = $quote->internal_notes ?? '';
            $this->terms          = $quote->terms ?? '';
            $this->currency       = $quote->currency ?? config('inovcom.currency', 'XOF');
            $this->tax_rate       = (string) ($quote->tax_rate ?? $tenant?->getSetting('tax_rate', '0') ?? 0);
            $this->discount_percent = (string) ($quote->discount_percent ?? 0);

            foreach ($quote->lines as $line) {
                $this->lines[] = [
                    'item_id'          => $line->item_id,
                    'description'      => $line->description,
                    'quantity'         => (string) $line->quantity,
                    'unit'             => (string) ($line->unit ?? ''),
                    'unit_price'       => (string) $line->unit_price,
                    'discount_percent' => (string) ($line->discount_percent ?? 0),
                    'cost'             => (string) ($line->cost ?? 0),
                    'amount'           => (string) $line->amount,
                    'line_type'        => $line->line_type ?? 'service',
                ];
            }
        }

        // ── Pre-fill from offer when redirected via from_offer=id ────
        if (!$this->quoteId) {
            $fromOfferId = (int) request()->query('from_offer', 0);
            if ($fromOfferId) {
                $sourceOffer = Offer::on('tenant')->find($fromOfferId);
                if ($sourceOffer) {
                    $this->offer_id  = $sourceOffer->id;
                    $this->client_id = $sourceOffer->client_id ?? 0;
                    $this->title     = $sourceOffer->title;
                }
            }

            if (empty($this->valid_until)) {
                $this->valid_until = now()->addMonth()->format('Y-m-d');
            }
        }

        $this->syncClientLabel();
        $this->syncOfferLabel();

        if (empty($this->lines)) {
            $this->addLine();
        }
        $this->recalculate();

        if (request()->boolean('import') && $this->tenantCan('devis.import')) {
            $this->highlightDropZone = true;
        }

        if ($this->quoteId) {
            $this->syncExpiryIfNeeded();
        }
    }

    protected function isQuoteEditable(): bool
    {
        return $this->status === 'draft';
    }

    protected function syncExpiryIfNeeded(): void
    {
        $quote = Quote::on('tenant')->find($this->quoteId);
        if (!$quote || $quote->status !== 'sent' || !$quote->isExpiredByDate()) {
            return;
        }

        if ($quote->canTransitionTo('expired')) {
            try {
                $quote->transitionTo('expired', auth('tenant')->id(), __('Validité dépassée'));
                $this->status = 'expired';
                notify()->warning(__('Ce devis a expiré (date de validité dépassée).'));
            } catch (InvalidWorkflowTransitionException) {
            }
        }
    }

    // ── Validation ────────────────────────────────────────────────────
    public function rules(): array
    {
        $rules = [
            'title'           => ['required', 'string', 'max:255'],
            'client_id'       => ['required', 'integer', 'min:1'],
            'offer_id'        => ['nullable', 'integer', 'min:0'],
            'status'          => ['required', 'in:draft,sent,accepted,refused,expired'],
            'valid_until'     => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
            'internal_notes'  => ['nullable', 'string'],
            'terms'           => ['nullable', 'string'],
            'currency'        => ['required', 'string', 'size:3'],
            'tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent'=> ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
        if ($this->quoteId) {
            $rules['code'] = [
                'required',
                'string',
                'max:50',
                Rule::unique(Quote::class, 'code')
                    ->where(fn ($query) => $query->where('version', $this->quoteVersion))
                    ->ignore($this->quoteId),
            ];
        }
        return $rules;
    }

    protected function syncClientLabel(): void
    {
        if ($this->client_id <= 0) {
            $this->clientLabel = null;
            return;
        }

        $client = Client::on('tenant')->find($this->client_id, ['id', 'name', 'code']);
        $this->clientLabel = $client ? "{$client->name} ({$client->code})" : null;
    }

    protected function syncOfferLabel(): void
    {
        if ($this->offer_id <= 0) {
            $this->offerLabel = null;
            return;
        }

        $offer = Offer::on('tenant')->find($this->offer_id, ['id', 'code', 'title']);
        $this->offerLabel = $offer
            ? $offer->code . ' – ' . \Illuminate\Support\Str::limit($offer->title, 50)
            : null;
    }

    public function openClientPicker(): void
    {
        $this->showClientPicker = true;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    public function closeClientPicker(): void
    {
        $this->showClientPicker = false;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    public function updatedClientSearch(): void
    {
        $term = trim($this->clientSearch);
        if ($term === '') {
            $this->clientResults = [];
            return;
        }

        $this->clientResults = Client::on('tenant')
            ->active()
            ->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('code', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'code', 'type'])
            ->toArray();
    }

    public function selectClient(int $id): void
    {
        $client = Client::on('tenant')->find($id, ['id', 'name', 'code']);
        if (!$client) {
            return;
        }

        $this->client_id = $client->id;
        $this->clientLabel = "{$client->name} ({$client->code})";
        $this->closeClientPicker();
    }

    public function clearClient(): void
    {
        $this->client_id = 0;
        $this->clientLabel = null;
    }

    public function openOfferPicker(): void
    {
        $this->showOfferPicker = true;
        $this->offerSearch = '';
        $this->offerResults = [];
    }

    public function closeOfferPicker(): void
    {
        $this->showOfferPicker = false;
        $this->offerSearch = '';
        $this->offerResults = [];
    }

    public function updatedOfferSearch(): void
    {
        $term = trim($this->offerSearch);
        if ($term === '') {
            $this->offerResults = [];
            return;
        }

        $this->offerResults = Offer::on('tenant')
            ->with('client:id,name,code')
            ->where('status', '!=', Offer::STATUS_CLOSED)
            ->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhere('title', 'ilike', "%{$term}%");
            })
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'code', 'title', 'status', 'client_id'])
            ->map(fn (Offer $offer) => [
                'id'          => $offer->id,
                'code'        => $offer->code,
                'title'       => $offer->title,
                'status'      => $offer->status,
                'client_id'   => $offer->client_id,
                'client_name' => $offer->client?->name,
            ])
            ->toArray();
    }

    public function selectOffer(int $id): void
    {
        $offer = Offer::on('tenant')->with('client')->find($id);
        if (!$offer) {
            return;
        }

        $this->offer_id = $offer->id;
        $this->offerLabel = $offer->code . ' – ' . \Illuminate\Support\Str::limit($offer->title, 50);

        if ($offer->client_id) {
            $this->client_id = $offer->client_id;
            $this->syncClientLabel();
        }

        if (trim($this->title) === '') {
            $this->title = $offer->title;
        }

        $this->closeOfferPicker();
    }

    public function clearOffer(): void
    {
        $this->offer_id = 0;
        $this->offerLabel = null;
    }

    // ── Line management ───────────────────────────────────────────────

    /** Add a blank manual line. */
    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'          => null,
            'description'      => '',
            'quantity'         => '1',
            'unit'             => '',
            'unit_price'       => '0',
            'discount_percent' => '0',
            'cost'             => '0',
            'amount'           => '0',
            'line_type'        => 'service',
        ];
    }

    public function addSectionLine(): void
    {
        $this->lines[] = [
            'item_id'          => null,
            'description'      => '',
            'quantity'         => '0',
            'unit'             => '',
            'unit_price'       => '0',
            'discount_percent' => '0',
            'cost'             => '0',
            'amount'           => '0',
            'line_type'        => 'section',
        ];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
        $this->recalculate();
    }

    public function updatedLines($value, $key): void
    {
        if (preg_match('/^(\d+)\.line_type$/', $key, $m)) {
            $idx = (int) $m[1];
            if (isset($this->lines[$idx]) && ($this->lines[$idx]['line_type'] ?? '') === 'section') {
                $this->lines[$idx]['quantity'] = '0';
                $this->lines[$idx]['unit'] = '';
                $this->lines[$idx]['unit_price'] = '0';
                $this->lines[$idx]['discount_percent'] = '0';
                $this->lines[$idx]['cost'] = '0';
                $this->lines[$idx]['amount'] = '0';
            }
        }

        if (preg_match('/^(\d+)\.(quantity|unit_price|discount_percent|cost)$/', $key, $m)) {
            $idx = (int) $m[1];
            if (!isset($this->lines[$idx])) {
                return;
            }
            $qty   = (float) ($this->lines[$idx]['quantity'] ?? 0);
            $price = (float) ($this->lines[$idx]['unit_price'] ?? 0);
            $disc  = (float) ($this->lines[$idx]['discount_percent'] ?? 0);
            $this->lines[$idx]['amount'] = (string) round($qty * $price * (1 - $disc / 100), 2);
        }
        $this->recalculate();
    }

    public function updatedTaxRate(): void   { $this->recalculate(); }
    public function updatedDiscountPercent(): void { $this->recalculate(); }

    /** Recalculate all financial summary fields from current lines. */
    protected function recalculate(): void
    {
        $totalHt   = 0;
        $totalCost = 0;
        foreach ($this->lines as $line) {
            if (($line['line_type'] ?? '') === 'section') {
                continue;
            }
            $totalHt   += (float) ($line['amount'] ?? 0);
            $totalCost += (float) ($line['cost'] ?? 0);
        }
        $discount  = round($totalHt * ((float) $this->discount_percent / 100), 2);
        $netHt     = round($totalHt - $discount, 2);
        $tax       = round($netHt * ((float) $this->tax_rate / 100), 2);
        $totalTtc  = round($netHt + $tax, 2);
        $marginPct = $totalHt > 0 ? round(($totalHt - $totalCost) / $totalHt * 100, 2) : 0;

        $this->computed_total_ht   = $totalHt;
        $this->computed_discount   = $discount;
        $this->computed_net_ht     = $netHt;
        $this->computed_tax        = $tax;
        $this->computed_total_ttc  = $totalTtc;
        $this->computed_margin_pct = $marginPct;
    }

    // ── Item catalog picker ───────────────────────────────────────────

    public function openItemPicker(): void
    {
        $this->itemSearch = '';
        $this->showItemPicker = true;
    }

    public function closeItemPicker(): void
    {
        $this->showItemPicker = false;
        $this->itemSearch = '';
    }

    /**
     * Add a catalog item as a quote line.
     * Fetches item data and appends a pre-filled line.
     */
    public function addItemFromCatalog(int $itemId): void
    {
        if (!class_exists(\InovCom\Items\Models\Item::class)) {
            return;
        }

        $item = \InovCom\Items\Models\Item::on('tenant')
            ->with(['unit', 'category'])
            ->find($itemId);

        if (!$item) {
            return;
        }

        $desc = $item->name;
        if ($item->description) {
            $desc .= "\n" . $item->description;
        }

        $this->lines[] = [
            'item_id'          => $item->id,
            'description'      => $item->name,
            'quantity'         => '1',
            'unit'             => (string) ($item->unit?->symbol ?? $item->unit?->name ?? ''),
            'unit_price'       => (string) $item->price,
            'discount_percent' => '0',
            'cost'             => (string) ($item->cost ?? 0),
            'amount'           => (string) $item->price,
            'line_type'        => 'product',
        ];

        $this->recalculate();

        notify()->success(__(':item ajouté au devis.', ['item' => $item->name]));
    }

    // ── Auto-fill from Offer ──────────────────────────────────────────
    public function updatedOfferId($value): void
    {
        $this->syncOfferLabel();

        $offer = Offer::on('tenant')->find((int) $value);
        if ($offer?->client_id) {
            $this->client_id = $offer->client_id;
            $this->syncClientLabel();
        }
    }

    // ── Workflow actions ──────────────────────────────────────────────

    public function sendToClient(): void
    {
        $this->tenantAuthorize('devis.send');
        $quote = Quote::on('tenant')->findOrFail($this->quoteId);

        try {
            $quote->transitionTo('sent', auth('tenant')->id());
            $this->status = 'sent';
            notify()->success(__('Devis marqué comme envoyé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markExpired(): void
    {
        $this->tenantAuthorize('devis.send');
        $quote = Quote::on('tenant')->findOrFail($this->quoteId);

        try {
            $quote->transitionTo('expired', auth('tenant')->id(), __('Marqué expiré manuellement'));
            $this->status = 'expired';
            notify()->success(__('Devis marqué comme expiré.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function createInvoiceFromQuote(string $mode = 'full'): void
    {
        $this->tenantAuthorize('facturation.create');

        if (!class_exists(Invoice::class)) {
            notify()->error(__('Le module facturation n\'est pas activé.'));
            return;
        }

        $quote = Quote::on('tenant')->with('lines')->findOrFail($this->quoteId);

        try {
            $invoice = app(CreateInvoiceFromQuoteService::class)->create(
                $quote,
                $mode === 'advance' ? 'advance' : 'full',
                30
            );

            $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
            notify()->success(__('Facture :code créée en brouillon.', ['code' => $invoice->code]));

            $this->redirect(
                route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $invoice->id]),
                navigate: true
            );
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function goToLinkedProject(): void
    {
        $project = Project::on('tenant')->where('quote_id', $this->quoteId)->first();
        if (!$project) {
            notify()->error(__('Aucun projet lié à ce devis.'));
            return;
        }

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(
            route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $project->id]),
            navigate: true
        );
    }

    public function acceptQuote(): void
    {
        $this->tenantAuthorize('devis.accept');
        $quote = Quote::on('tenant')->findOrFail($this->quoteId);

        try {
            $quote->transitionTo('accepted', auth('tenant')->id());
            $this->status = 'accepted';
            notify()->success(__('Devis accepté. Un projet a été créé automatiquement.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function refuseQuote(): void
    {
        $this->tenantAuthorize('devis.accept');
        $this->showRefuseModal = true;
    }

    public function confirmRefuseQuote(): void
    {
        $this->tenantAuthorize('devis.accept');
        $this->validate([
            'refuse_category' => ['required', 'string', Rule::in(QuoteRefuseReasons::keys())],
            'refuse_comment'  => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->refuse_category === 'other' && trim($this->refuse_comment) === '') {
            $this->addError('refuse_comment', __('Précisez le motif pour « Autre ».'));
            return;
        }

        $quote = Quote::on('tenant')->findOrFail($this->quoteId);
        $reason = QuoteRefuseReasons::compose($this->refuse_category, $this->refuse_comment);

        try {
            $quote->transitionTo('refused', auth('tenant')->id());
            $quote->update(['refuse_reason' => $reason]);
            $this->status = 'refused';
            $this->showRefuseModal = false;
            $this->refuse_category = '';
            $this->refuse_comment = '';
            notify()->success(__('Devis marqué comme refusé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function cancelRefuseQuote(): void
    {
        $this->showRefuseModal = false;
        $this->refuse_category = '';
        $this->refuse_comment = '';
    }

    public function duplicateQuote(): void
    {
        $this->tenantAuthorize('devis.create');
        $source = Quote::on('tenant')->with('lines')->findOrFail($this->quoteId);
        $newQuote = app(QuoteDuplicationService::class)->revise($source);

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        notify()->success(__('Révision v:version de :devis créée.', [
            'devis' => $newQuote->code,
            'version' => $newQuote->version,
        ]));

        $this->redirect(
            route('tenant.devis.edit', ['tenant' => $tenantCode, 'quote' => $newQuote->id]),
            navigate: true
        );
    }

    // ── Excel import ──────────────────────────────────────────────────

    public function updatedDropImportFile(): void
    {
        if (!$this->dropImportFile || $this->status !== 'draft') {
            return;
        }

        $this->tenantAuthorize('devis.import');

        $this->validate([
            'dropImportFile' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $filename = $this->dropImportFile->getClientOriginalName();

        if (trim($this->title) === '') {
            $this->title = pathinfo($filename, PATHINFO_FILENAME);
        }

        $file = $this->dropImportFile;

        if ($this->attemptQuickImportFromFile($file)) {
            $this->stageSourceFileForArchive($file, $filename);
            $this->dropImportFile = null;
            $this->highlightDropZone = false;

            notify()->success(__(':count lignes chargées. Modifiez les prix ci-dessous puis enregistrez.', [
                'count' => count(array_filter($this->lines, fn ($l) => trim($l['description'] ?? '') !== '')),
            ]));

            if ($this->quoteId) {
                $this->archivePendingSource(Quote::on('tenant')->findOrFail($this->quoteId));
            }

            return;
        }

        try {
            $analysis = app(QuoteImportService::class)->analyzeFile(
                $file->getRealPath(),
                $file->getClientOriginalExtension(),
                null
            );

            $this->importFile = $file;
            $this->dropImportFile = null;
            $this->loadAnalysisState($analysis);
            $this->showImportModal = true;
            $this->importStep = 2;
            $this->highlightDropZone = false;

            notify()->warning(__('Format non reconnu automatiquement — associez les colonnes manuellement.'));
        } catch (\Throwable $e) {
            $this->dropImportFile = null;
            notify()->error(__('Erreur de lecture : :msg', ['msg' => $e->getMessage()]));
        }
    }

    protected function attemptQuickImportFromFile($file): bool
    {
        try {
            $service = app(QuoteImportService::class);
            $analysis = $service->analyzeFile(
                $file->getRealPath(),
                $file->getClientOriginalExtension(),
                null
            );

            $mapping = $analysis['mapping'];
            if (!$this->mappingIsUsable($mapping)) {
                return false;
            }

            $result = $service->buildLines(
                $analysis['rows'],
                $analysis['suggested_header_row'],
                $mapping,
                0
            );

            if (empty($result['lines'])) {
                return false;
            }

            $this->applyImportedLines($result['lines'], 'replace');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, int|null|string>  $mapping
     */
    protected function mappingIsUsable(array $mapping): bool
    {
        $description = $mapping['description'] ?? null;

        return $description !== null && $description !== '';
    }

    /**
     * @param  array{rows: array, suggested_header_row: int, headers: array, mapping: array}  $analysis
     */
    protected function loadAnalysisState(array $analysis): void
    {
        $this->importRawRows = $analysis['rows'];
        $this->importHeaderRow = $analysis['suggested_header_row'];
        $this->importHeaders = $analysis['headers'];
        $this->importMapping = $analysis['mapping'];
        $this->refreshImportPreview();
    }

    /**
     * @param  list<array<string, mixed>>  $importedLines
     */
    protected function applyImportedLines(array $importedLines, ?string $mode = null): void
    {
        $mode ??= $this->importMode;

        if ($mode === 'replace') {
            $this->lines = $importedLines;
        } else {
            $existing = array_values(array_filter(
                $this->lines,
                fn ($l) => trim($l['description'] ?? '') !== ''
            ));
            $this->lines = array_merge($existing, $importedLines);
        }

        if (empty($this->lines)) {
            $this->addLine();
        }

        $this->recalculate();
    }

    protected function stageSourceFileForArchive($file, string $filename): void
    {
        $this->pendingSourceFile = $file;
        $this->pendingSourceFilename = $filename;
    }

    protected function archivePendingSource(Quote $quote): void
    {
        if (!$this->pendingSourceFile) {
            return;
        }

        if (!app(QuoteSourceArchiveService::class)->isAvailable()) {
            $this->pendingSourceFile = null;
            $this->pendingSourceFilename = null;

            return;
        }

        $archived = app(QuoteSourceArchiveService::class)->archive($quote, $this->pendingSourceFile);

        $this->pendingSourceFile = null;
        $this->pendingSourceFilename = null;

        if ($archived) {
            $this->documentsRefreshKey++;
        }
    }

    public function openImportModal(): void
    {
        $this->tenantAuthorize('devis.import');
        $this->resetImportState();
        $this->showImportModal = true;
        $this->importStep = 1;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->resetImportState();
    }

    protected function resetImportState(): void
    {
        $this->importStep = 1;
        $this->importFile = null;
        $this->importHeaderRow = 1;
        $this->importHeaders = [];
        $this->importMapping = array_fill_keys(QuoteImportService::MAPPABLE_FIELDS, null);
        $this->importRawRows = [];
        $this->importPreviewLines = [];
        $this->importSkippedRows = 0;
        $this->importTotalLines = 0;
        $this->importWarnings = [];
        $this->importMode = 'replace';
        $this->resetValidation();
    }

    public function parseImportFile(): void
    {
        $this->tenantAuthorize('devis.import');
        $this->validate([
            'importFile' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ]);

        try {
            $path = $this->importFile->getRealPath();
            $extension = $this->importFile->getClientOriginalExtension();
            $service = app(QuoteImportService::class);
            $analysis = $service->analyzeFile($path, $extension, $this->importHeaderRow ?: null);

            $this->importRawRows = $analysis['rows'];
            $this->importHeaderRow = $analysis['suggested_header_row'];
            $this->importHeaders = $analysis['headers'];
            $this->importMapping = $analysis['mapping'];
            $this->importStep = 2;
            $this->refreshImportPreview();
        } catch (\Throwable $e) {
            notify()->error(__('Erreur de lecture : :msg', ['msg' => $e->getMessage()]));
        }
    }

    public function updatedImportHeaderRow(): void
    {
        if (empty($this->importRawRows)) {
            return;
        }

        $headerIndex = max(0, $this->importHeaderRow - 1);
        $row = $this->importRawRows[$headerIndex] ?? [];
        $service = app(QuoteImportService::class);
        $this->importHeaders = [];
        foreach ($row as $index => $cell) {
            $label = trim((string) $cell);
            $this->importHeaders[$index] = $label !== '' ? $label : __('Colonne :n', ['n' => $index + 1]);
        }
        $this->importMapping = $service->suggestMapping($this->importHeaders);
        $this->refreshImportPreview();
    }

    public function updatedImportMapping(): void
    {
        $this->refreshImportPreview();
    }

    protected function mappingHasDescription(): bool
    {
        $col = $this->importMapping['description'] ?? null;

        return $col !== null && $col !== '';
    }

    public function goToImportPreview(): void
    {
        if (!$this->mappingHasDescription()) {
            notify()->error(__('Veuillez associer au moins la colonne Désignation.'));
            return;
        }

        $this->refreshImportPreview();

        if ($this->importTotalLines === 0) {
            notify()->error(__('Aucune ligne importable détectée avec ce mapping.'));
            return;
        }

        $this->importStep = 3;
    }

    protected function refreshImportPreview(): void
    {
        if (empty($this->importRawRows)) {
            return;
        }

        $service = app(QuoteImportService::class);
        $preview = $service->buildLines(
            $this->importRawRows,
            $this->importHeaderRow,
            $this->importMapping,
            15
        );

        $this->importPreviewLines = $preview['lines'];
        $this->importSkippedRows = $preview['skipped'];
        $this->importWarnings = $preview['warnings'];
        $this->importTotalLines = $service->countImportableLines(
            $this->importRawRows,
            $this->importHeaderRow,
            $this->importMapping
        );
    }

    public function confirmImport(): void
    {
        $this->tenantAuthorize('devis.import');

        if (!$this->mappingHasDescription()) {
            notify()->error(__('Veuillez associer au moins la colonne Désignation.'));
            return;
        }

        $service = app(QuoteImportService::class);
        $result = $service->buildLines(
            $this->importRawRows,
            $this->importHeaderRow,
            $this->importMapping,
            0
        );

        if (empty($result['lines'])) {
            notify()->error(__('Aucune ligne à importer.'));
            return;
        }

        $importedLines = $result['lines'];
        $this->applyImportedLines($importedLines);

        if ($this->importFile) {
            $this->stageSourceFileForArchive(
                $this->importFile,
                $this->importFile->getClientOriginalName()
            );
            $this->importFile = null;
        }

        $this->closeImportModal();

        notify()->success(__(':count lignes importées. :skipped ignorées.', [
            'count' => count($importedLines),
            'skipped' => $result['skipped'],
        ]));

        if ($this->quoteId) {
            $this->archivePendingSource(Quote::on('tenant')->findOrFail($this->quoteId));
        }
    }

    public function downloadPdf(): void
    {
        $this->tenantAuthorize('devis.view');
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $url = route('tenant.devis.pdf', [
            'tenant' => $tenantCode,
            'quote'  => $this->quoteId,
        ]);
        $this->js("window.open(" . json_encode($url) . ", '_blank')");
    }

    public function downloadExcel(): void
    {
        $this->tenantAuthorize('devis.export');
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $url = route('tenant.devis.excel', [
            'tenant' => $tenantCode,
            'quote'  => $this->quoteId,
        ]);
        $this->js("window.open(" . json_encode($url) . ", '_blank')");
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->quoteId ? 'devis.edit' : 'devis.create');

        if ($this->quoteId && !$this->isQuoteEditable()) {
            notify()->error(__('Ce devis est verrouillé. Réouvrez-le en brouillon ou créez une révision.'));
            return;
        }

        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->recalculate();

        $validLines = [];
        foreach ($this->lines as $row) {
            $desc = trim($row['description'] ?? '');
            if ($desc === '') {
                continue;
            }
            if (($row['line_type'] ?? '') === 'section') {
                $validLines[] = [
                    'item_id'          => null,
                    'description'      => $desc,
                    'quantity'         => 0,
                    'unit'             => null,
                    'unit_price'       => 0,
                    'discount_percent' => 0,
                    'cost'             => 0,
                    'amount'           => 0,
                    'line_type'        => 'section',
                ];
                continue;
            }
            $qty   = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $disc  = (float) ($row['discount_percent'] ?? 0);
            $cost  = (float) ($row['cost'] ?? 0);
            $validLines[] = [
                'item_id'          => $row['item_id'] ?? null,
                'description'      => $desc,
                'quantity'         => $qty,
                'unit'             => trim($row['unit'] ?? '') ?: null,
                'unit_price'       => $price,
                'discount_percent' => $disc,
                'cost'             => $cost,
                'amount'           => round($qty * $price * (1 - $disc / 100), 2),
                'line_type'        => $row['line_type'] ?? 'service',
            ];
        }

        $data = [
            'offer_id'         => $this->offer_id > 0 ? $this->offer_id : null,
            'client_id'        => $this->client_id,
            'title'            => $this->title,
            'valid_until'      => $this->valid_until ?: null,
            'notes'            => $this->notes ?: null,
            'internal_notes'   => $this->internal_notes ?: null,
            'terms'            => $this->terms ?: null,
            'currency'         => $this->currency,
            'tax_rate'         => (float) $this->tax_rate,
            'discount_percent' => (float) $this->discount_percent,
            // Computed totals
            'total_ht'         => $this->computed_total_ht,
            'discount_amount'  => $this->computed_discount,
            'net_ht'           => $this->computed_net_ht,
            'tax_amount'       => $this->computed_tax,
            'total_ttc'        => $this->computed_total_ttc,
            'margin_percent'   => $this->computed_margin_pct,
        ];

        if ($this->quoteId) {
            $quote = Quote::on('tenant')->findOrFail($this->quoteId);

            if ($quote->status !== $this->status) {
                try {
                    $quote->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }

            $quote->update($data);
            $quote->lines()->delete();
        } else {
            $code  = $this->generateNextCode();
            $quote = Quote::create(array_merge($data, ['code' => $code, 'status' => $this->status]));

            if ($this->offer_id) {
                try {
                    Offer::on('tenant')->where('id', $this->offer_id)
                        ->whereNull('quote_id')
                        ->update(['quote_id' => $quote->id]);
                } catch (\Throwable) {}
            }
        }

        foreach ($validLines as $pos => $row) {
            QuoteLine::create([
                'quote_id'         => $quote->id,
                'position'         => $pos,
                'item_id'          => $row['item_id'],
                'description'      => $row['description'],
                'quantity'         => $row['quantity'],
                'unit'             => $row['unit'] ?? null,
                'unit_price'       => $row['unit_price'],
                'discount_percent' => $row['discount_percent'],
                'cost'             => $row['cost'],
                'amount'           => $row['amount'],
                'line_type'        => $row['line_type'],
            ]);
        }

        $this->archivePendingSource($quote);

        notify()->success($this->quoteId ? __('Devis mis à jour.') : __('Devis créé.'));
        $this->redirect(
            route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quote->id]),
            navigate: true
        );
    }

    protected function generateNextCode(): string
    {
        $max = Quote::on('tenant')
            ->where('code', 'like', 'DEV%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();
        return 'DEV' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Render ────────────────────────────────────────────────────────
    public function render()
    {
        $linkedProject = null;
        $linkedInvoices = collect();
        $quoteRecord = null;
        $versionFamily = collect();

        if ($this->quoteId) {
            $quoteRecord = Quote::on('tenant')->with(['parent', 'revisions'])->find($this->quoteId);
            $allowedTransitions = $quoteRecord?->allowedTransitions()[$this->status] ?? [];
            $quoteVersion = $quoteRecord?->version;
            $parentQuote = $quoteRecord?->parent;
            $versionFamily = $quoteRecord ? $quoteRecord->versionFamily() : collect();

            if (class_exists(Project::class)) {
                $linkedProject = Project::on('tenant')->where('quote_id', $this->quoteId)->first();
            }
            if (class_exists(Invoice::class)) {
                $linkedInvoices = Invoice::on('tenant')->where('quote_id', $this->quoteId)->ordered()->get();
            }
        }

        // Catalog items for the picker – only load when picker is open
        $catalogItems = collect();
        if ($this->showItemPicker && class_exists(\InovCom\Items\Models\Item::class)) {
            $query = \InovCom\Items\Models\Item::on('tenant')
                ->with(['category', 'unit'])
                ->where('is_active', true);

            if ($this->itemSearch !== '') {
                $term = $this->itemSearch;
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'ilike', "%{$term}%")
                      ->orWhere('sku', 'ilike', "%{$term}%")
                      ->orWhere('description', 'ilike', "%{$term}%");
                });
            }

            $catalogItems = $query->orderBy('name')->limit($this->itemSearch !== '' ? 50 : 10)->get();
        }

        $addedItemIds = collect($this->lines)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $tenant = app(TenantManager::class)->tenant();
        $dmsEnabled = class_exists(\InovCom\Dms\Models\Document::class)
            && $tenant
            && app(ModuleRegistry::class)->isEnabled('dms', $tenant);

        return view('inovcom-devis::livewire.quotes.form', [
            'allowedTransitions' => $allowedTransitions ?? [],
            'catalogItems'       => $catalogItems,
            'addedItemIds'       => $addedItemIds,
            'quoteVersion'       => $quoteVersion ?? null,
            'parentQuote'        => $parentQuote ?? null,
            'importFields'       => QuoteImportService::MAPPABLE_FIELDS,
            'canImport'          => $this->tenantCan('devis.import') && $this->isQuoteEditable(),
            'canDuplicate'       => $this->tenantCan('devis.create'),
            'canExport'          => $this->tenantCan('devis.export'),
            'canFacturation'     => $this->tenantCan('facturation.create') && class_exists(Invoice::class),
            'isLocked'           => (bool) ($this->quoteId && !$this->isQuoteEditable()),
            'linkedProject'      => $linkedProject,
            'linkedInvoices'     => $linkedInvoices,
            'quoteRecord'        => $quoteRecord,
            'versionFamily'      => $versionFamily,
            'dmsEnabled'         => $dmsEnabled,
            'canViewDms'         => $dmsEnabled && $this->tenantCan('dms.view'),
            'sourceArchiveAvailable' => app(QuoteSourceArchiveService::class)->isAvailable(),
            'refuseCategories'   => QuoteRefuseReasons::options(),
        ])->layout('layouts.app', [
            'title'    => $this->quoteId ? __('Modifier le devis') : __('Nouveau devis'),
            'subtitle' => $this->code
                ? (($this->quoteId && ($quoteVersion ?? 1) > 1 && ($versionFamily->count() ?? 0) > 1)
                    ? $this->code . ' · v' . $quoteVersion
                    : $this->code)
                : '',
        ]);
    }
}
