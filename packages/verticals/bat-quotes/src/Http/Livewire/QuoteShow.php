<?php

namespace InovCom\Devis\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use InovCom\Devis\Models\Quote;
use InovCom\Devis\Services\CreateInvoiceFromQuoteService;
use InovCom\Devis\Services\QuoteAcceptanceService;
use InovCom\Devis\Services\QuoteBillingService;
use InovCom\Devis\Services\QuoteDuplicationService;
use InovCom\Devis\Services\QuoteMailtoService;
use InovCom\Devis\Services\QuoteSourceArchiveService;
use InovCom\Devis\Support\QuoteRefuseReasons;
use InovCom\Facturation\Models\Invoice;
use Illuminate\Validation\Rule;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Projets\Models\Project;
use Livewire\Component;

class QuoteShow extends Component
{
    use AuthorizesWithTenant;

    public Quote $quote;

    public bool $showRefuseModal = false;
    public string $refuse_category = '';
    public string $refuse_comment = '';
    public bool $emailComposerOpened = false;

    public bool $showAdvanceModal = false;
    public int $advancePercent = 30;

    public function mount(Quote $quote): void
    {
        $this->tenantAuthorize('devis.view');
        $this->refreshQuote($quote);
        $this->syncExpiryIfNeeded();
    }

    protected function refreshQuote(?Quote $quote = null): void
    {
        $quote ??= $this->quote;
        $this->quote = $quote->loadMissing(['client', 'offer', 'lines']);
    }

    protected function syncExpiryIfNeeded(): void
    {
        if (!$this->quote->isExpiredByDate() || !$this->quote->canTransitionTo('expired')) {
            return;
        }

        try {
            $this->quote->transitionTo('expired', null, __('Expiration automatique (validité dépassée)'));
            $this->refreshQuote();
        } catch (InvalidWorkflowTransitionException) {
        }
    }

    public function sendToClient(): void
    {
        $this->tenantAuthorize('devis.send');

        try {
            $this->quote->transitionTo('sent', auth('tenant')->id());
            $this->refreshQuote();
            notify()->success(__('Devis marqué comme envoyé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function sendByEmail(): void
    {
        $this->tenantAuthorize('devis.send');

        if (!$this->quote->client?->email) {
            notify()->error(__('Ajoutez une adresse e-mail au client avant l\'envoi.'));
            return;
        }

        if ($this->quote->status !== 'draft') {
            notify()->error(__('L\'envoi par e-mail n\'est disponible que pour les brouillons.'));
            return;
        }

        try {
            $url = app(QuoteMailtoService::class)->buildUrl($this->quote, 'send');
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
            return;
        }

        $this->emailComposerOpened = true;
        $this->js('window.location.href = ' . json_encode($url));
    }

    public function remindClientByEmail(): void
    {
        $this->tenantAuthorize('devis.send');

        if (!$this->quote->client?->email) {
            notify()->error(__('Aucune adresse e-mail pour ce client.'));
            return;
        }

        if (!in_array($this->quote->status, ['sent', 'expired'], true)) {
            notify()->error(__('La relance est possible pour les devis envoyés ou expirés.'));
            return;
        }

        try {
            $url = app(QuoteMailtoService::class)->buildUrl($this->quote, 'reminder');
            $this->quote->update(['last_reminder_at' => now()]);
            $this->refreshQuote();
            $this->js('window.location.href = ' . json_encode($url));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function reopenToDraft(): void
    {
        $this->tenantAuthorize('devis.edit');

        try {
            $this->quote->transitionTo('draft', auth('tenant')->id(), __('Réouverture pour modification'));
            $this->refreshQuote();
            notify()->success(__('Devis réouvert en brouillon.'));
            $this->redirect(
                route('tenant.devis.edit', ['tenant' => $this->tenantCode(), 'quote' => $this->quote->id]),
                navigate: true
            );
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markExpired(): void
    {
        $this->tenantAuthorize('devis.send');

        try {
            $this->quote->transitionTo('expired', auth('tenant')->id(), __('Marqué expiré manuellement'));
            $this->refreshQuote();
            notify()->success(__('Devis marqué comme expiré.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function acceptQuote(): void
    {
        $this->tenantAuthorize('devis.accept');

        try {
            $this->quote->transitionTo('accepted', auth('tenant')->id());
            $this->refreshQuote();

            $acceptance = app(QuoteAcceptanceService::class);
            $category   = $acceptance->offerCategory($this->quote);

            if ($acceptance->isMaintenanceQuote($this->quote)) {
                $contract = class_exists(MaintenanceContract::class)
                    ? MaintenanceContract::on('tenant')->where('quote_id', $this->quote->id)->first()
                    : null;

                notify()->success($contract
                    ? ServiceCatalog::acceptSuccessWithExecution($category, $contract->code)
                    : ServiceCatalog::acceptSuccessPendingExecution($category));
            } else {
                $project = class_exists(Project::class)
                    ? Project::on('tenant')->where('quote_id', $this->quote->id)->first()
                    : null;

                notify()->success($project
                    ? ServiceCatalog::acceptSuccessWithExecution($category, $project->code)
                    : ServiceCatalog::acceptSuccessPendingExecution($category));
            }
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function createLinkedExecution(): void
    {
        $acceptance = app(QuoteAcceptanceService::class);

        if ($acceptance->isMaintenanceQuote($this->quote)) {
            $this->tenantAuthorize('maintenance.create');

            if (!class_exists(MaintenanceContract::class)) {
                notify()->error(__('Le module maintenance n\'est pas activé.'));
                return;
            }
        } else {
            $this->tenantAuthorize('projets.create');

            if (!class_exists(Project::class)) {
                notify()->error(__('Le module projets n\'est pas activé.'));
                return;
            }
        }

        try {
            $result = $acceptance->execute($this->quote);
            $category = $acceptance->offerCategory($this->quote);

            if ($result->contract) {
                notify()->success(ServiceCatalog::executionCreatedToast($category, $result->contract->code));
            } elseif ($result->project) {
                notify()->success(ServiceCatalog::executionCreatedToast($category, $result->project->code));
            }
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    /** @deprecated Use createLinkedExecution() */
    public function createLinkedProject(): void
    {
        $this->createLinkedExecution();
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

        $reason = QuoteRefuseReasons::compose($this->refuse_category, $this->refuse_comment);

        try {
            $this->quote->transitionTo('refused', auth('tenant')->id());
            $this->quote->update(['refuse_reason' => $reason]);
            $this->showRefuseModal = false;
            $this->refuse_category = '';
            $this->refuse_comment = '';
            $this->refreshQuote();
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

    public function reviseQuote(): void
    {
        $this->tenantAuthorize('devis.create');
        $newQuote = app(QuoteDuplicationService::class)->revise($this->quote);

        notify()->success(__('Révision v:version de :devis créée.', [
            'devis' => $newQuote->code,
            'version' => $newQuote->version,
        ]));

        $this->redirect(
            route('tenant.devis.edit', ['tenant' => $this->tenantCode(), 'quote' => $newQuote->id]),
            navigate: true
        );
    }

    public function copyQuote(): void
    {
        $this->tenantAuthorize('devis.create');
        $newQuote = app(QuoteDuplicationService::class)->copyAsNew($this->quote);

        notify()->success(__('Copie créée : :code', ['code' => $newQuote->code]));

        $this->redirect(
            route('tenant.devis.edit', ['tenant' => $this->tenantCode(), 'quote' => $newQuote->id]),
            navigate: true
        );
    }

    public function deleteQuote(): void
    {
        $this->tenantAuthorize('devis.delete');

        if ($this->quote->status !== 'draft') {
            notify()->error(__('Seuls les devis en brouillon peuvent être supprimés.'));
            return;
        }

        $this->quote->lines()->delete();
        $this->quote->delete();

        notify()->success(__('Devis supprimé.'));

        $this->redirect(
            route('tenant.devis.index', ['tenant' => $this->tenantCode()]),
            navigate: true
        );
    }

    public function openAdvanceModal(): void
    {
        $this->tenantAuthorize('facturation.create');

        $billing = app(QuoteBillingService::class)->summarize($this->quote);
        if (!$billing->canInvoiceMore()) {
            notify()->error(__('Ce devis est déjà entièrement facturé.'));
            return;
        }

        $this->advancePercent = 30;
        $this->showAdvanceModal = true;
    }

    public function closeAdvanceModal(): void
    {
        $this->showAdvanceModal = false;
    }

    public function setAdvancePercent(int $percent): void
    {
        $this->advancePercent = max(1, min(100, $percent));
    }

    public function confirmAdvanceInvoice(): void
    {
        $this->validate([
            'advancePercent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $billing = app(QuoteBillingService::class)->summarize($this->quote);
        $amount = $billing->advanceAmountForPercent((int) $this->advancePercent);
        if ($amount <= 0) {
            notify()->error(__('Montant d\'acompte invalide : rien ne reste à facturer.'));
            return;
        }

        $this->createInvoiceFromQuote('advance', (int) $this->advancePercent);
        $this->showAdvanceModal = false;
    }

    public function createInvoiceFromQuote(string $mode = 'full', ?int $advancePercent = null): void
    {
        $this->tenantAuthorize('facturation.create');

        if (!class_exists(Invoice::class)) {
            notify()->error(__('Le module facturation n\'est pas activé.'));
            return;
        }

        if (!$this->executionReady()) {
            $category = app(QuoteAcceptanceService::class)->offerCategory($this->quote);
            notify()->error(ServiceCatalog::invoicingBlockedMessage($category));
            return;
        }

        $billing = app(QuoteBillingService::class)->summarize($this->quote);
        if (!$billing->canInvoiceMore()) {
            notify()->error(__('Ce devis est déjà entièrement facturé.'));
            return;
        }

        if ($mode === 'full' && !$billing->canCreateFinalInvoice()) {
            notify()->error(__('Une facture de solde existe déjà pour ce devis.'));
            return;
        }

        $percent = $mode === 'advance'
            ? max(1, min(100, $advancePercent ?? $this->advancePercent))
            : 30;

        try {
            $invoice = app(CreateInvoiceFromQuoteService::class)->create(
                $this->quote,
                $mode === 'advance' ? 'advance' : 'full',
                $percent
            );

            notify()->success(__('Facture :code créée en brouillon.', ['code' => $invoice->code]));

            $this->redirect(
                route('tenant.facturation.edit', ['tenant' => $this->tenantCode(), 'invoice' => $invoice->id]),
                navigate: true
            );
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function goToLinkedContract(): void
    {
        $contract = MaintenanceContract::on('tenant')->where('quote_id', $this->quote->id)->first();
        if (!$contract) {
            notify()->error(__('Aucun contrat lié à ce devis.'));
            return;
        }

        $this->redirect(
            route('tenant.maintenance.contracts.edit', [
                'tenant' => $this->tenantCode(),
                'maintenance_contract' => $contract->id,
            ]),
            navigate: true
        );
    }

    public function goToLinkedProject(): void
    {
        $project = Project::on('tenant')->where('quote_id', $this->quote->id)->first();
        if (!$project) {
            notify()->error(__('Aucun projet lié à ce devis.'));
            return;
        }

        $this->redirect(
            route('tenant.projets.edit', ['tenant' => $this->tenantCode(), 'project' => $project->id]),
            navigate: true
        );
    }

    public function downloadPdf(): void
    {
        $this->tenantAuthorize('devis.view');
        $url = route('tenant.devis.pdf', [
            'tenant' => $this->tenantCode(),
            'quote'  => $this->quote->id,
        ]);
        $this->js('window.open(' . json_encode($url) . ", '_blank')");
    }

    public function downloadExcel(): void
    {
        $this->tenantAuthorize('devis.export');
        $url = route('tenant.devis.excel', [
            'tenant' => $this->tenantCode(),
            'quote'  => $this->quote->id,
        ]);
        $this->js('window.open(' . json_encode($url) . ", '_blank')");
    }

    protected function executionReady(): bool
    {
        if (class_exists(Project::class)
            && Project::on('tenant')->where('quote_id', $this->quote->id)->exists()) {
            return true;
        }

        return class_exists(MaintenanceContract::class)
            && MaintenanceContract::on('tenant')->where('quote_id', $this->quote->id)->exists();
    }

    protected function tenantCode(): string
    {
        return session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
    }

    public function render()
    {
        $versionFamily = $this->quote->versionFamily();
        $allowedTransitions = $this->quote->allowedTransitions()[$this->quote->status] ?? [];

        $linkedProject = null;
        $linkedContract = null;
        $billingSummary = null;

        $acceptance = app(QuoteAcceptanceService::class);
        $offerCategory = $acceptance->offerCategory($this->quote);
        $acceptanceCycle = ServiceCatalog::postAcceptanceCycle($offerCategory);
        $isMaintenanceQuote = $acceptance->isMaintenanceQuote($this->quote);

        if (class_exists(Project::class)) {
            $linkedProject = Project::on('tenant')->where('quote_id', $this->quote->id)->first();
        }
        if (class_exists(MaintenanceContract::class)) {
            $linkedContract = MaintenanceContract::on('tenant')->where('quote_id', $this->quote->id)->first();
        }
        if (class_exists(Invoice::class)) {
            $billingSummary = app(QuoteBillingService::class)->summarize($this->quote);
        }

        $executionReady = $linkedProject !== null || $linkedContract !== null;
        $canCreateExecution = $acceptanceCycle['kind'] === 'contract'
            ? ($this->tenantCan('maintenance.create') && class_exists(MaintenanceContract::class))
            : ($this->tenantCan('projets.create') && class_exists(Project::class));

        $tenant = app(TenantManager::class)->tenant();
        $dmsEnabled = class_exists(\InovCom\Dms\Models\Document::class)
            && $tenant
            && app(ModuleRegistry::class)->isEnabled('dms', $tenant);

        return view('inovcom-devis::livewire.quotes.show', [
            'tenantCode'         => $this->tenantCode(),
            'versionFamily'      => $versionFamily,
            'allowedTransitions' => $allowedTransitions,
            'linkedProject'      => $linkedProject,
            'linkedContract'     => $linkedContract,
            'billingSummary'     => $billingSummary,
            'advancePreviewAmount' => $billingSummary
                ? $billingSummary->advanceAmountForPercent($this->advancePercent)
                : 0,
            'offerCategory'      => $offerCategory,
            'offerCategoryLabel' => ServiceCatalog::offerCategoryLabel($offerCategory),
            'offerCategoryBadge' => ServiceCatalog::offerCategoryBadgeClass($offerCategory),
            'acceptanceCycle'    => $acceptanceCycle,
            'isMaintenanceQuote' => $isMaintenanceQuote,
            'executionReady'     => $executionReady,
            'canCreateExecution' => $canCreateExecution,
            'canEdit'            => $this->tenantCan('devis.edit') && $this->quote->isEditable(),
            'canCreate'          => $this->tenantCan('devis.create'),
            'canDelete'          => $this->tenantCan('devis.delete'),
            'canSend'            => $this->tenantCan('devis.send'),
            'canAccept'          => $this->tenantCan('devis.accept'),
            'canExport'          => $this->tenantCan('devis.export'),
            'canFacturation'     => $this->tenantCan('facturation.create') && class_exists(Invoice::class),
            'canProjets'         => $this->tenantCan('projets.create') && class_exists(Project::class),
            'canMaintenance'     => $this->tenantCan('maintenance.create') && class_exists(MaintenanceContract::class),
            'canViewDms'         => $dmsEnabled && $this->tenantCan('dms.view'),
            'sourceArchiveAvailable' => app(QuoteSourceArchiveService::class)->isAvailable(),
            'refuseCategories'       => QuoteRefuseReasons::options(),
        ])->layout('layouts.app', [
            'title'    => __('Devis'),
            'subtitle' => $versionFamily->count() > 1
                ? $this->quote->code . ' · v' . $this->quote->version
                : $this->quote->code,
        ]);
    }
}
