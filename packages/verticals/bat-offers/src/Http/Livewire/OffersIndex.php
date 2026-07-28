<?php

namespace InovCom\Offres\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Offres\Models\Offer;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithPagination;

class OffersIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    // ── Filters ───────────────────────────────────────────────────────
    public string $search          = '';
    public string $filterStatus    = '';
    public string $filterCategory  = '';
    public int    $perPage         = 15;

    // ── Process modal ─────────────────────────────────────────────────
    public bool  $showProcessModal  = false;
    public ?int  $processingOfferId = null;

    // ── Close reason form (inline inside process modal) ───────────────
    public bool   $showCloseForm = false;
    public string $closeReason   = '';
    public string $closeNote     = '';

    public function mount(): void
    {
        $this->tenantAuthorize('offres.view');
    }

    public function updatedSearch():         void { $this->resetPage(); }
    public function updatedFilterStatus():   void { $this->resetPage(); }
    public function updatedFilterCategory(): void { $this->resetPage(); }

    // ── Delete ────────────────────────────────────────────────────────
    public function delete(int $id): void
    {
        $this->tenantAuthorize('offres.delete');
        $offer = Offer::findOrFail($id);

        if ($offer->status !== Offer::STATUS_DRAFT) {
            notify()->warning(__("Seules les offres en brouillon peuvent être supprimées."));
            return;
        }

        $offer->delete();
        notify()->success(__('Offre supprimée.'));
    }

    // ── Process modal ─────────────────────────────────────────────────
    public function openProcessModal(int $id): void
    {
        $this->processingOfferId = $id;
        $this->showProcessModal  = true;
        $this->showCloseForm     = false;
        $this->closeReason       = '';
        $this->closeNote         = '';
        $this->resetErrorBag();
    }

    public function closeProcessModal(): void
    {
        $this->showProcessModal  = false;
        $this->processingOfferId = null;
        $this->showCloseForm     = false;
        $this->closeReason       = '';
        $this->closeNote         = '';
        $this->resetErrorBag();
    }

    // ── Open/cancel the close reason form ─────────────────────────────
    public function openCloseForm(): void
    {
        $this->showCloseForm = true;
        $this->closeReason   = '';
        $this->closeNote     = '';
        $this->resetErrorBag();
    }

    public function cancelCloseForm(): void
    {
        $this->showCloseForm = false;
        $this->closeReason   = '';
        $this->closeNote     = '';
        $this->resetErrorBag();
    }

    // ── Transition: Draft → In Review ─────────────────────────────────
    public function moveToInReview(): void
    {
        $this->tenantAuthorize('offres.edit');

        $offer = Offer::find($this->processingOfferId);
        if (!$offer) { $this->closeProcessModal(); return; }

        if (!$offer->assigned_to) {
            $this->addError('process', __("L'offre doit d'abord être assignée à un collaborateur pour passer en révision."));
            return;
        }

        if ($offer->status !== Offer::STATUS_DRAFT) {
            $this->addError('process', __("Seules les offres en brouillon peuvent passer en révision."));
            return;
        }

        try {
            $offer->transitionTo(Offer::STATUS_IN_REVIEW, auth('tenant')->id());
        } catch (\Throwable) {
            $offer->update(['status' => Offer::STATUS_IN_REVIEW]);
        }

        $this->closeProcessModal();
        notify()->success(__('Offre passée en révision avec succès.'));
    }

    // ── Transition: In Review → Terminated (via Create Quote) ─────────
    public function createQuote(): void
    {
        $this->tenantAuthorize('offres.edit');

        $offer = Offer::find($this->processingOfferId);
        if (!$offer) { $this->closeProcessModal(); return; }

        if (!$offer->canCreateQuote()) {
            $this->addError('process', __("Seules les offres en révision peuvent générer un devis."));
            return;
        }

        try {
            $offer->transitionTo(Offer::STATUS_TERMINATED, auth('tenant')->id());
        } catch (\Throwable) {
            $offer->update(['status' => Offer::STATUS_TERMINATED]);
        }

        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        $this->closeProcessModal();

        if (Route::has('tenant.devis.create')) {
            $this->redirect(
                route('tenant.devis.create', ['tenant' => $tenantCode, 'from_offer' => $offer->id]),
                navigate: true
            );
        } else {
            notify()->success(__('Offre transmise au service Devis. Créez maintenant le devis correspondant.'));
        }
    }

    // ── Transition: In Review → Closed ────────────────────────────────
    public function closeOffer(): void
    {
        $this->tenantAuthorize('offres.close');

        // Validate close reason inputs
        $this->validate([
            'closeReason' => ['required', 'string', 'in:' . implode(',', array_keys(Offer::CLOSE_REASONS))],
            'closeNote'   => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'closeReason.required' => __('Veuillez sélectionner un motif de clôture.'),
            'closeReason.in'       => __('Motif de clôture invalide.'),
            'closeNote.required'   => __('Une note explicative est obligatoire.'),
            'closeNote.min'        => __('La note doit contenir au moins 10 caractères.'),
            'closeNote.max'        => __('La note ne peut pas dépasser 1000 caractères.'),
        ]);

        $offer = Offer::find($this->processingOfferId);
        if (!$offer) { $this->closeProcessModal(); return; }

        if (!$offer->canClose()) {
            $this->addError('process', __("Une offre déjà terminée ou clôturée ne peut plus être fermée."));
            return;
        }

        $offer->update([
            'close_reason' => $this->closeReason,
            'close_note'   => $this->closeNote,
        ]);

        try {
            $offer->transitionTo(Offer::STATUS_CLOSED, auth('tenant')->id());
        } catch (\Throwable) {
            $offer->update(['status' => Offer::STATUS_CLOSED]);
        }

        $this->closeProcessModal();
        notify()->success(__('Offre clôturée.'));
    }

    // ── Render ────────────────────────────────────────────────────────
    public function render()
    {
        $offers = Offer::query()
            ->with(['client', 'assignedUser'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('code',  'like', "%{$this->search}%")
            ))
            ->when($this->filterStatus,   fn ($q) => $q->status($this->filterStatus))
            ->when($this->filterCategory, fn ($q) => $q->category($this->filterCategory))
            ->ordered()
            ->paginate($this->perPage);

        // Fetch processing offer fresh (never store Eloquent model in Livewire property)
        $processingOffer = ($this->showProcessModal && $this->processingOfferId)
            ? Offer::with(['client', 'assignedUser', 'quote'])->find($this->processingOfferId)
            : null;

        // Whether the current user may close offers
        $canCloseOffer = (bool) (auth('tenant')->user()?->can('offres.close') ?? false);

        $closeReasons = Offer::CLOSE_REASONS;

        return view('inovcom-offres::livewire.offers.index', compact('offers', 'processingOffer', 'canCloseOffer', 'closeReasons'))
            ->layout('layouts.app', [
                'title'    => __('Offres'),
                'subtitle' => __('Réception et traitement des offres commerciales'),
            ]);
    }
}
