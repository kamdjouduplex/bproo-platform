<?php

namespace InovCom\Facturation\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Devis\Models\Quote;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Models\InvoiceLine;
use InovCom\Facturation\Models\InvoicePayment;
use InovCom\Facturation\Support\InvoiceCodeGenerator;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Projets\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InvoiceForm extends Component
{
    use AuthorizesWithTenant;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int    $invoiceId   = null;
    public string  $code        = '';

    // ── Fields ────────────────────────────────────────────────────────
    public int     $client_id   = 0;
    public ?int    $project_id  = null;
    public ?int    $quote_id    = null;
    public string  $title       = '';
    public string  $status      = 'draft';
    public string  $invoice_type = 'invoice';   // invoice | credit_note | proforma
    public ?string $issue_date  = null;
    public ?string $due_date    = null;
    public string  $payment_terms = '30';       // days
    public string  $notes       = '';
    public string  $internal_notes = '';
    public string  $currency    = '';

    // ── Financial ─────────────────────────────────────────────────────
    public string $tax_rate         = '0';
    public string $discount_percent = '0';

    // ── Line items ────────────────────────────────────────────────────
    /** @var array<int, array{description:string,quantity:string,unit_price:string,amount:string}> */
    public array $lines = [];

    // ── Computed (read-only display) ──────────────────────────────────
    public float $computed_total_ht    = 0;
    public float $computed_discount    = 0;
    public float $computed_net_ht      = 0;
    public float $computed_tax         = 0;
    public float $computed_total_ttc   = 0;
    public float $computed_amount_paid = 0;
    public float $computed_amount_due  = 0;

    // ── Payment recording form ─────────────────────────────────────────
    public bool    $showPaymentForm    = false;
    public string  $pay_amount        = '';
    public string  $pay_date          = '';
    public string  $pay_method        = 'virement';
    public string  $pay_reference     = '';

    // ── Existing payments (loaded in render) ──────────────────────────
    public array $existingPayments = [];

    // ── Client / project / quote pickers (search on demand) ───────────
    public bool    $showClientPicker  = false;
    public string  $clientSearch      = '';
    public array   $clientResults      = [];
    public ?string $clientLabel       = null;

    public bool    $showProjectPicker = false;
    public string  $projectSearch     = '';
    public array   $projectResults    = [];
    public ?string $projectLabel      = null;

    public bool    $showQuotePicker   = false;
    public string  $quoteSearch       = '';
    public array   $quoteResults      = [];
    public ?string $quoteLabel         = null;

    public function mount(?Invoice $invoice = null): void
    {
        $this->tenantAuthorize('facturation.view');

        $this->issue_date = now()->format('Y-m-d');
        $this->pay_date   = now()->format('Y-m-d');

        // Defaults from tenant settings (set by ApplyTenantSettings middleware)
        $tenant = app(TenantManager::class)->tenant();
        $this->currency      = config('inovcom.currency', 'XOF');
        $this->tax_rate      = (string) ($tenant?->getSetting('tax_rate', '0') ?? '0');
        $this->payment_terms = (string) ($tenant?->getSetting('payment_terms', '30') ?? '30');

        if ($invoice && $invoice->exists) {
            $this->invoiceId       = $invoice->id;
            $this->code            = $invoice->code;
            $this->client_id       = $invoice->client_id;
            $this->project_id      = $invoice->project_id;
            $this->quote_id        = $invoice->quote_id;
            $this->title           = $invoice->title ?? '';
            $this->status          = $invoice->status;
            $this->invoice_type    = $invoice->invoice_type ?? 'invoice';
            $this->issue_date      = $invoice->issue_date?->format('Y-m-d');
            $this->due_date        = $invoice->due_date?->format('Y-m-d');
            $this->payment_terms   = (string) ($invoice->payment_terms ?? $tenant?->getSetting('payment_terms', '30') ?? 30);
            $this->notes           = $invoice->notes ?? '';
            $this->internal_notes  = $invoice->internal_notes ?? '';
            $this->currency        = $invoice->currency ?? config('inovcom.currency', 'XOF');
            $this->tax_rate        = (string) ($invoice->tax_rate ?? $tenant?->getSetting('tax_rate', '0') ?? 0);
            $this->discount_percent = (string) ($invoice->discount_percent ?? 0);
            $this->computed_amount_paid = (float) ($invoice->amount_paid ?? 0);

            foreach ($invoice->lines as $line) {
                $this->lines[] = [
                    'description' => $line->description,
                    'quantity'    => (string) $line->quantity,
                    'unit_price'  => (string) $line->unit_price,
                    'amount'      => (string) $line->amount,
                ];
            }

            $invoice->loadMissing('payments');
            $invoice->payments->each(fn (InvoicePayment $p) => $p->setRelation('invoice', $invoice));

            $this->existingPayments = $invoice->payments
                ->map(fn (InvoicePayment $p) => $this->mapPaymentForView($p))
                ->toArray();
        }

        if (empty($this->lines)) {
            $this->addLine();
        }
        $this->syncClientLabel();
        $this->syncProjectLabel();
        $this->syncQuoteLabel();
        $this->recalculate();
        $this->computeDueDate();

        if (!$this->invoiceId && $this->code === '') {
            $this->syncCodeForType();
        }
    }

    public function updatedInvoiceType(): void
    {
        $this->syncCodeForType();
    }

    protected function syncCodeForType(): void
    {
        if ($this->isFormLocked() || $this->status !== 'draft') {
            return;
        }

        $generator = app(InvoiceCodeGenerator::class);

        if ($this->invoiceId) {
            $invoice = Invoice::on('tenant')->find($this->invoiceId);
            if ($invoice && $generator->codeMatchesType($invoice->code, $this->invoice_type)) {
                $this->code = $invoice->code;

                return;
            }
        }

        $this->code = $generator->nextCode($this->invoice_type);
    }

    protected function isFormLocked(): bool
    {
        return in_array($this->status, ['paid', 'cancelled'], true);
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

    protected function syncProjectLabel(): void
    {
        if (!$this->project_id) {
            $this->projectLabel = null;
            return;
        }

        $project = Project::on('tenant')->find($this->project_id, ['id', 'code', 'title']);
        $this->projectLabel = $project
            ? $project->code . ' – ' . \Illuminate\Support\Str::limit($project->title, 50)
            : null;
    }

    protected function syncQuoteLabel(): void
    {
        if (!$this->quote_id) {
            $this->quoteLabel = null;
            return;
        }

        $quote = Quote::on('tenant')->find($this->quote_id, ['id', 'code', 'title']);
        $this->quoteLabel = $quote
            ? $quote->code . ' – ' . \Illuminate\Support\Str::limit($quote->title, 50)
            : null;
    }

    public function openClientPicker(): void
    {
        if ($this->isFormLocked()) {
            return;
        }
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
        if ($this->isFormLocked()) {
            return;
        }
        $this->client_id = 0;
        $this->clientLabel = null;
    }

    public function openProjectPicker(): void
    {
        if ($this->isFormLocked()) {
            return;
        }
        $this->showProjectPicker = true;
        $this->projectSearch = '';
        $this->projectResults = [];
    }

    public function closeProjectPicker(): void
    {
        $this->showProjectPicker = false;
        $this->projectSearch = '';
        $this->projectResults = [];
    }

    public function updatedProjectSearch(): void
    {
        $term = trim($this->projectSearch);
        if ($term === '') {
            $this->projectResults = [];
            return;
        }

        $this->projectResults = Project::on('tenant')
            ->with('client:id,name')
            ->when($this->client_id > 0, fn ($q) => $q->where('client_id', $this->client_id))
            ->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhere('title', 'ilike', "%{$term}%");
            })
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'code', 'title', 'status', 'client_id', 'quote_id'])
            ->map(fn (Project $project) => [
                'id'          => $project->id,
                'code'        => $project->code,
                'title'       => $project->title,
                'status'      => $project->status,
                'client_id'   => $project->client_id,
                'quote_id'    => $project->quote_id,
                'client_name' => $project->client?->name,
            ])
            ->toArray();
    }

    public function selectProject(int $id): void
    {
        $project = Project::on('tenant')->find($id);
        if (!$project) {
            return;
        }

        $this->project_id = $project->id;
        $this->projectLabel = $project->code . ' – ' . \Illuminate\Support\Str::limit($project->title, 50);

        if ($project->client_id) {
            $this->client_id = $project->client_id;
            $this->syncClientLabel();
        }

        if ($project->quote_id) {
            $this->quote_id = $project->quote_id;
            $this->syncQuoteLabel();
        }

        if (trim($this->title) === '') {
            $this->title = $project->title;
        }

        $this->closeProjectPicker();
    }

    public function clearProject(): void
    {
        if ($this->isFormLocked()) {
            return;
        }
        $this->project_id = null;
        $this->projectLabel = null;
    }

    public function openQuotePicker(): void
    {
        if ($this->isFormLocked()) {
            return;
        }
        $this->showQuotePicker = true;
        $this->quoteSearch = '';
        $this->quoteResults = [];
    }

    public function closeQuotePicker(): void
    {
        $this->showQuotePicker = false;
        $this->quoteSearch = '';
        $this->quoteResults = [];
    }

    public function updatedQuoteSearch(): void
    {
        $term = trim($this->quoteSearch);
        if ($term === '') {
            $this->quoteResults = [];
            return;
        }

        $this->quoteResults = Quote::on('tenant')
            ->with('client:id,name')
            ->where('status', 'accepted')
            ->when($this->client_id > 0, fn ($q) => $q->where('client_id', $this->client_id))
            ->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhere('title', 'ilike', "%{$term}%");
            })
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'code', 'title', 'client_id', 'total_ttc', 'currency'])
            ->map(fn (Quote $quote) => [
                'id'          => $quote->id,
                'code'        => $quote->code,
                'title'       => $quote->title,
                'client_id'   => $quote->client_id,
                'client_name' => $quote->client?->name,
                'total_ttc'   => (float) $quote->total_ttc,
                'currency'    => $quote->currency,
            ])
            ->toArray();
    }

    public function selectQuote(int $id): void
    {
        $quote = Quote::on('tenant')->find($id);
        if (!$quote) {
            return;
        }

        $this->quote_id = $quote->id;
        $this->quoteLabel = $quote->code . ' – ' . \Illuminate\Support\Str::limit($quote->title, 50);

        if ($quote->client_id) {
            $this->client_id = $quote->client_id;
            $this->syncClientLabel();
        }

        $project = Project::on('tenant')->where('quote_id', $quote->id)->first();
        if ($project) {
            $this->project_id = $project->id;
            $this->syncProjectLabel();
        }

        if (trim($this->title) === '') {
            $this->title = $quote->title;
        }

        if ($quote->currency) {
            $this->currency = $quote->currency;
        }

        $this->closeQuotePicker();
    }

    public function clearQuote(): void
    {
        if ($this->isFormLocked()) {
            return;
        }
        $this->quote_id = null;
        $this->quoteLabel = null;
    }

    // ── Validation ────────────────────────────────────────────────────
    public function rules(): array
    {
        $rules = [
            'client_id'        => ['required', 'integer', 'min:1'],
            'project_id'       => ['nullable', 'integer'],
            'quote_id'         => ['nullable', 'integer'],
            'title'            => ['nullable', 'string', 'max:255'],
            'status'           => ['required', 'in:draft,sent,paid,overdue,cancelled'],
            'invoice_type'     => ['required', 'in:invoice,credit_note,proforma'],
            'issue_date'       => ['nullable', 'date'],
            'due_date'         => ['nullable', 'date'],
            'payment_terms'    => ['nullable', 'integer', 'min:0'],
            'notes'            => ['nullable', 'string'],
            'internal_notes'   => ['nullable', 'string'],
            'currency'         => ['required', 'string', 'size:3'],
            'tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
        if ($this->invoiceId) {
            $rules['code'] = ['required', 'string', 'max:50',
                Rule::unique(Invoice::class, 'code')->ignore($this->invoiceId)];
        }
        return $rules;
    }

    // ── Line management ───────────────────────────────────────────────
    public function addLine(): void
    {
        $this->lines[] = [
            'description' => '',
            'quantity'    => '1',
            'unit_price'  => '0',
            'amount'      => '0',
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
        if (preg_match('/^(\d+)\.(quantity|unit_price)$/', $key, $m)) {
            $idx = (int) $m[1];
            if (!isset($this->lines[$idx])) {
                return;
            }
            $qty   = (float) ($this->lines[$idx]['quantity'] ?? 0);
            $price = (float) ($this->lines[$idx]['unit_price'] ?? 0);
            $this->lines[$idx]['amount'] = (string) round($qty * $price, 2);
        }
        $this->recalculate();
    }

    public function updatedTaxRate(): void         { $this->recalculate(); }
    public function updatedDiscountPercent(): void { $this->recalculate(); }
    public function updatedPaymentTerms(): void    { $this->computeDueDate(); }
    public function updatedIssueDate(): void       { $this->computeDueDate(); }

    protected function recalculate(): void
    {
        $totalHt = 0;
        foreach ($this->lines as $line) {
            $totalHt += (float) ($line['amount'] ?? 0);
        }
        $discount = round($totalHt * ((float) $this->discount_percent / 100), 2);
        $netHt    = round($totalHt - $discount, 2);
        $tax      = round($netHt * ((float) $this->tax_rate / 100), 2);
        $totalTtc = round($netHt + $tax, 2);

        $this->computed_total_ht  = $totalHt;
        $this->computed_discount  = $discount;
        $this->computed_net_ht    = $netHt;
        $this->computed_tax       = $tax;
        $this->computed_total_ttc = $totalTtc;
        $this->computed_amount_due = round($totalTtc - $this->computed_amount_paid, 2);
    }

    protected function computeDueDate(): void
    {
        if ($this->issue_date && $this->payment_terms !== '') {
            $days = (int) $this->payment_terms;
            $this->due_date = \Carbon\Carbon::parse($this->issue_date)->addDays($days)->format('Y-m-d');
        }
    }

    // ── Workflow actions ──────────────────────────────────────────────

    /** Mark as sent. */
    public function sendToClient(): void
    {
        $this->tenantAuthorize('facturation.send');
        $invoice = Invoice::on('tenant')->findOrFail($this->invoiceId);

        try {
            $invoice->transitionTo('sent', auth('tenant')->id());
            $this->status = 'sent';
            notify()->success(__('Facture marquée comme envoyée.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    /** Cancel the invoice. */
    public function cancelInvoice(): void
    {
        $this->tenantAuthorize('facturation.edit');
        $invoice = Invoice::on('tenant')->findOrFail($this->invoiceId);

        try {
            $invoice->transitionTo('cancelled', auth('tenant')->id());
            $this->status = 'cancelled';
            notify()->success(__('Facture annulée.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    /** Open invoice PDF in a new browser tab. */
    public function downloadPdf(): void
    {
        $this->tenantAuthorize('facturation.view');
        $url = route('tenant.facturation.pdf', [
            'tenant'  => $this->tenantCode(),
            'invoice' => $this->invoiceId,
        ]);
        $this->js('window.open(' . json_encode($url) . ", '_blank')");
    }

    /** Open payment receipt PDF (accusé de réception de paiement). */
    public function printPaymentReceipt(int $paymentId): void
    {
        $this->tenantAuthorize('facturation.view');

        if (!$this->invoiceId) {
            return;
        }

        $exists = InvoicePayment::on('tenant')
            ->where('invoice_id', $this->invoiceId)
            ->where('id', $paymentId)
            ->exists();

        if (!$exists) {
            notify()->error(__('Paiement introuvable.'));
            return;
        }

        $url = route('tenant.facturation.payment.receipt', [
            'tenant'  => $this->tenantCode(),
            'invoice' => $this->invoiceId,
            'payment' => $paymentId,
        ]);
        $this->js('window.open(' . json_encode($url) . ", '_blank')");
    }

    protected function mapPaymentForView(InvoicePayment $payment): array
    {
        return [
            'id'             => $payment->id,
            'receipt_code'   => $payment->receiptCode(),
            'amount'         => (float) $payment->amount,
            'payment_date'   => $payment->payment_date,
            'payment_method' => $payment->payment_method,
            'method_label'   => $payment->paymentMethodLabel(),
            'reference'      => $payment->reference,
        ];
    }

    protected function tenantCode(): string
    {
        return session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
    }

    // ── Payment recording ─────────────────────────────────────────────
    public function togglePaymentForm(): void
    {
        $this->showPaymentForm = !$this->showPaymentForm;
    }

    public function recordPayment(): void
    {
        $this->tenantAuthorize('facturation.edit');

        $this->validate([
            'pay_amount'    => ['required', 'numeric', 'min:0.01'],
            'pay_date'      => ['required', 'date'],
            'pay_method'    => ['required', 'string'],
            'pay_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice = Invoice::on('tenant')->findOrFail($this->invoiceId);
        $payment = $invoice->recordPayment(
            amount:    (float) $this->pay_amount,
            date:      $this->pay_date,
            method:    $this->pay_method,
            reference: $this->pay_reference ?: null,
            userId:    auth('tenant')->id(),
        );

        // Refresh state.
        $invoice->refresh();
        $invoice->load('payments');
        $invoice->payments->each(fn (InvoicePayment $p) => $p->setRelation('invoice', $invoice));
        $this->status              = $invoice->status;
        $this->computed_amount_paid = (float) $invoice->amount_paid;
        $this->computed_amount_due  = (float) $invoice->amount_due;

        $this->existingPayments = $invoice->payments
            ->map(fn (InvoicePayment $p) => $this->mapPaymentForView($p))
            ->toArray();

        $this->pay_amount    = '';
        $this->pay_reference = '';
        $this->showPaymentForm = false;

        notify()->success(__('Paiement enregistré. Le reçu de paiement s\'ouvre pour impression.'));
        $this->printPaymentReceipt($payment->id);
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->invoiceId ? 'facturation.edit' : 'facturation.create');
        $this->validate();
        $this->recalculate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $validLines = [];
        foreach ($this->lines as $row) {
            $desc = trim($row['description'] ?? '');
            if ($desc === '') {
                continue;
            }
            $qty   = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $validLines[] = [
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'amount'      => round($qty * $price, 2),
            ];
        }

        $data = [
            'client_id'        => $this->client_id,
            'project_id'       => $this->project_id ?: null,
            'quote_id'         => $this->quote_id ?: null,
            'title'            => $this->title ?: null,
            'invoice_type'     => $this->invoice_type,
            'issue_date'       => $this->issue_date ?: null,
            'due_date'         => $this->due_date ?: null,
            'payment_terms'    => $this->payment_terms !== '' ? (int) $this->payment_terms : null,
            'notes'            => $this->notes ?: null,
            'internal_notes'   => $this->internal_notes ?: null,
            'currency'         => $this->currency,
            'tax_rate'         => (float) $this->tax_rate,
            'discount_percent' => (float) $this->discount_percent,
            // Computed totals
            'total_ht'         => $this->computed_total_ht,
            'discount_amount'  => $this->computed_discount,
            'net_ht'           => $this->computed_net_ht,
            'tax_amount'       => $this->computed_tax,
            'total_ttc'        => $this->computed_total_ttc,
            'amount_due'       => $this->computed_amount_due,
        ];

        if ($this->invoiceId) {
            $invoice = Invoice::on('tenant')->findOrFail($this->invoiceId);

            if ($invoice->status !== $this->status) {
                try {
                    $invoice->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }

            if ($this->status === 'draft') {
                $generator = app(InvoiceCodeGenerator::class);
                if (!$generator->codeMatchesType($this->code, $this->invoice_type)) {
                    $this->code = $generator->nextCode($this->invoice_type);
                }
                $data['code'] = $this->code;
            }

            $invoice->update($data);
            $invoice->lines()->delete();
        } else {
            $generator = app(InvoiceCodeGenerator::class);
            $code      = $this->code !== '' && $generator->codeMatchesType($this->code, $this->invoice_type)
                ? $this->code
                : $generator->nextCode($this->invoice_type);
            $this->code = $code;

            $invoice = Invoice::create(array_merge($data, [
                'code'   => $code,
                'status' => $this->status,
            ]));
        }

        foreach ($validLines as $pos => $row) {
            InvoiceLine::create([
                'invoice_id'  => $invoice->id,
                'position'    => $pos,
                'description' => $row['description'],
                'quantity'    => $row['quantity'],
                'unit_price'  => $row['unit_price'],
                'amount'      => $row['amount'],
            ]);
        }

        notify()->success($this->invoiceId ? __('Facture mise à jour.') : __('Facture créée.'));
        $this->redirect(
            route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $invoice->id]),
            navigate: true
        );
    }

    // ── Render ────────────────────────────────────────────────────────
    public function render()
    {
        $allowedTransitions = [];
        if ($this->invoiceId) {
            $invoice = Invoice::on('tenant')->find($this->invoiceId);
            $allowedTransitions = $invoice?->allowedTransitions()[$this->status] ?? [];
        }

        return view('inovcom-facturation::livewire.invoices.form', [
            'allowedTransitions' => $allowedTransitions,
            'isLocked'           => $this->isFormLocked(),
        ])->layout('layouts.app', [
            'title'    => $this->invoiceId ? __('Modifier la facture') : __('Nouvelle facture'),
            'subtitle' => $this->code ?: '',
        ]);
    }
}
