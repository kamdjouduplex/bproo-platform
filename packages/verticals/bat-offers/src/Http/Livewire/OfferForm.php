<?php

namespace InovCom\Offres\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Notifications\OfferAssigned;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Offres\Models\Offer;
use InovCom\Users\Models\User;
use Livewire\Component;

class OfferForm extends Component
{
    use AuthorizesWithTenant;

    // ── Core fields ───────────────────────────────────────────────────
    public ?int   $offerId        = null;
    public string $code           = '';
    public string $title          = '';
    public string $category       = 'project';
    public ?int   $client_id      = null;
    public ?int   $assigned_to    = null;
    public string $source         = '';
    public string $channel        = '';
    public string $channel_detail = '';
    public string $received_at    = '';
    public string $notes          = '';

    // Status is read-only in the form — transitions happen via Process workflow
    public string $currentStatus = 'draft';

    // ── Client picker state ───────────────────────────────────────────
    public bool    $showClientPicker = false;
    public string  $clientSearch     = '';
    public array   $clientResults    = [];
    public ?string $clientLabel      = null;

    // ── User picker state ─────────────────────────────────────────────
    public bool    $showUserPicker = false;
    public string  $userSearch     = '';
    public array   $userResults    = [];
    public ?string $userLabel      = null;

    // ── Close modal state ─────────────────────────────────────────────
    public bool   $showCloseModal = false;
    public string $closeReason    = '';
    public string $closeNote      = '';

    // ── Read-only: stored close reason (loaded from DB) ───────────────
    public string $storedCloseReason = '';
    public string $storedCloseNote   = '';

    // ── Read-only: linked quote (loaded from DB) ──────────────────────
    public ?int   $linkedQuoteId   = null;
    public string $linkedQuoteCode = '';

    // ── Mount ─────────────────────────────────────────────────────────
    public function mount(?Offer $offer = null): void
    {
        $this->tenantAuthorize('offres.view');

        if ($offer && $offer->exists) {
            $this->offerId        = $offer->id;
            $this->code           = $offer->code;
            $this->title          = $offer->title;
            $this->category       = $offer->category;
            $this->client_id      = $offer->client_id;
            $this->assigned_to    = $offer->assigned_to;
            $this->source         = $offer->source         ?? '';
            $this->channel        = $offer->channel        ?? '';
            $this->channel_detail = $offer->channel_detail ?? '';
            $this->currentStatus  = $offer->status;
            $this->received_at    = $offer->received_at?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->notes          = $offer->notes ?? '';

            // Pre-populate picker display labels
            if ($offer->client_id && $offer->client) {
                $this->clientLabel = $offer->client->name . ' (' . $offer->client->code . ')';
            }
            if ($offer->assigned_to && $offer->assignedUser) {
                $this->userLabel = $offer->assignedUser->name;
            }

            // Load stored close reason (read-only display)
            $this->storedCloseReason = $offer->close_reason ?? '';
            $this->storedCloseNote   = $offer->close_note   ?? '';

            // Load linked quote (read-only display)
            if ($offer->quote_id) {
                $this->linkedQuoteId   = $offer->quote_id;
                $this->linkedQuoteCode = $offer->quote?->code ?? '';
            }
        } else {
            // Default received date to today on create
            $this->received_at = now()->format('Y-m-d');
        }
    }

    // ── Picker open/close (explicit methods — Livewire 4 $set magic is unreliable) ──
    public function openClientPicker(): void  { $this->showClientPicker = true;  $this->clientSearch = ''; $this->clientResults = []; }
    public function closeClientPicker(): void { $this->showClientPicker = false; $this->clientSearch = ''; $this->clientResults = []; }
    public function openUserPicker(): void    { $this->showUserPicker   = true;  $this->userSearch   = ''; $this->userResults   = []; }
    public function closeUserPicker(): void   { $this->showUserPicker   = false; $this->userSearch   = ''; $this->userResults   = []; }

    // ── Client picker ─────────────────────────────────────────────────
    public function updatedClientSearch(): void
    {
        if (strlen($this->clientSearch) < 1) {
            $this->clientResults = [];
            return;
        }

        $this->clientResults = Client::on('tenant')
            ->where(fn ($q) => $q
                ->where('name',  'like', "%{$this->clientSearch}%")
                ->orWhere('code', 'like', "%{$this->clientSearch}%")
                ->orWhere('email', 'like', "%{$this->clientSearch}%")
            )
            ->limit(10)
            ->get(['id', 'name', 'code', 'type'])
            ->toArray();
    }

    public function selectClient(int $id): void
    {
        $client = Client::on('tenant')->find($id, ['id', 'name', 'code']);
        if (!$client) return;

        $this->client_id        = $client->id;
        $this->clientLabel      = $client->name . ' (' . $client->code . ')';
        $this->clientSearch     = '';
        $this->clientResults    = [];
        $this->showClientPicker = false;
    }

    public function clearClient(): void
    {
        $this->client_id   = null;
        $this->clientLabel = null;
    }

    // ── User picker ───────────────────────────────────────────────────
    public function updatedUserSearch(): void
    {
        if (strlen($this->userSearch) < 1) {
            $this->userResults = [];
            return;
        }

        $this->userResults = User::on('tenant')
            ->where('name', 'like', "%{$this->userSearch}%")
            ->limit(10)
            ->get(['id', 'name'])
            ->toArray();
    }

    public function selectUser(int $id): void
    {
        $user = User::on('tenant')->find($id, ['id', 'name']);
        if (!$user) return;

        $this->assigned_to    = $user->id;
        $this->userLabel      = $user->name;
        $this->userSearch     = '';
        $this->userResults    = [];
        $this->showUserPicker = false;
    }

    public function clearUser(): void
    {
        $this->assigned_to = null;
        $this->userLabel   = null;
    }

    // ── Validation ────────────────────────────────────────────────────
    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'category'       => ['required', 'in:project,maintenance,service'],
            'client_id'      => ['nullable', 'integer'],
            'assigned_to'    => ['nullable', 'integer'],
            'source'         => ['nullable', 'string', 'max:100'],
            'channel'        => ['nullable', 'string', 'max:100'],
            'channel_detail' => ['nullable', 'string', 'max:500'],
            'received_at'    => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }

    // ── Code generation ───────────────────────────────────────────────
    protected function generateNextOfferCode(): string
    {
        $max = Offer::where('code', 'like', 'OFF%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'OFF' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->offerId ? 'offres.edit' : 'offres.create');
        $this->validate();

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        $data = [
            'title'          => $this->title,
            'category'       => $this->category,
            'client_id'      => $this->client_id      ?: null,
            'assigned_to'    => $this->assigned_to     ?: null,
            'source'         => $this->source          ?: null,
            'channel'        => $this->channel         ?: null,
            'channel_detail' => $this->channel_detail  ?: null,
            'received_at'    => $this->received_at     ?: now()->toDateString(),
            'notes'          => $this->notes           ?: null,
        ];

        if ($this->offerId) {
            // Detect assignment change before overwriting
            $offer         = Offer::find($this->offerId);
            $oldAssignedTo = $offer?->assigned_to;

            $offer->update($data);

            // Notify newly assigned user (skip if same person re-saved without change)
            $newAssignedTo = $data['assigned_to'];
            if ($newAssignedTo && $newAssignedTo !== $oldAssignedTo) {
                $assignee = User::on('tenant')->find($newAssignedTo);
                if ($assignee) {
                    try { $assignee->notify(new OfferAssigned($offer->fresh())); } catch (\Throwable) {}
                }
            }

            notify()->success(__('Offre mise à jour.'));
            $this->redirect(
                route('tenant.offres.edit', ['tenant' => $tenantCode, 'offer' => $this->offerId]),
                navigate: true
            );
        } else {
            $data['code']   = $this->generateNextOfferCode();
            $data['status'] = Offer::STATUS_DRAFT;
            $offer = Offer::create($data);

            // Notify assignee if set at creation
            if ($data['assigned_to']) {
                $assignee = User::on('tenant')->find($data['assigned_to']);
                if ($assignee) {
                    try { $assignee->notify(new OfferAssigned($offer)); } catch (\Throwable) {}
                }
            }

            notify()->success(__('Offre créée avec succès.'));
            $this->redirect(
                route('tenant.offres.index', ['tenant' => $tenantCode]),
                navigate: true
            );
        }
    }

    // ── Close modal open/cancel ───────────────────────────────────────
    public function openCloseModal(): void
    {
        $this->tenantAuthorize('offres.close');
        $this->showCloseModal = true;
        $this->closeReason    = '';
        $this->closeNote      = '';
        $this->resetValidation(['closeReason', 'closeNote']);
    }

    public function cancelCloseModal(): void
    {
        $this->showCloseModal = false;
        $this->closeReason    = '';
        $this->closeNote      = '';
        $this->resetValidation(['closeReason', 'closeNote']);
    }

    // ── Transition: In Review → Closed ───────────────────────────────
    public function closeOffer(): void
    {
        $this->tenantAuthorize('offres.close');

        if (!$this->offerId) return;

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

        $offer = Offer::find($this->offerId);
        if (!$offer || !$offer->canClose()) {
            notify()->warning(__("Cette offre ne peut plus être clôturée."));
            $this->cancelCloseModal();
            return;
        }

        $offer->update([
            'close_reason' => $this->closeReason,
            'close_note'   => $this->closeNote,
        ]);

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        try {
            $offer->transitionTo(Offer::STATUS_CLOSED, auth('tenant')->id());
        } catch (\Throwable) {
            $offer->update(['status' => Offer::STATUS_CLOSED]);
        }

        notify()->success(__('Offre clôturée.'));
        $this->redirect(
            route('tenant.offres.index', ['tenant' => $tenantCode]),
            navigate: true
        );
    }

    // ── Render ────────────────────────────────────────────────────────
    public function render()
    {
        $channels      = Offer::CHANNELS;
        $canCloseOffer = (bool) (auth('tenant')->user()?->can('offres.close') ?? false);
        $closeReasons  = Offer::CLOSE_REASONS;

        return view('inovcom-offres::livewire.offers.form', compact('channels', 'canCloseOffer', 'closeReasons'))
            ->layout('layouts.app', [
                'title'    => $this->offerId ? __('Modifier l\'offre') : __('Nouvelle offre'),
                'subtitle' => $this->offerId
                    ? ($this->code . ' — ' . $this->title)
                    : __('Renseignez les informations de l\'offre reçue'),
            ]);
    }
}
