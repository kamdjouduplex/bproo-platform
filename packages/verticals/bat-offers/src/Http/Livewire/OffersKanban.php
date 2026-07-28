<?php

namespace InovCom\Offres\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Offres\Models\Offer;
use Livewire\Component;

class OffersKanban extends Component
{
    use AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('offres.view');
    }

    public function moveOffer(int $offerId, string $newStatus): void
    {
        $this->tenantAuthorize('offres.edit');

        $allowed = ['draft', 'submitted', 'accepted', 'refused', 'archived'];
        if (!in_array($newStatus, $allowed, true)) {
            return;
        }

        $offer = Offer::on('tenant')->findOrFail($offerId);

        // Use state machine if possible, else force (for admin convenience)
        if ($offer->canTransitionTo($newStatus)) {
            $offer->transitionTo($newStatus, auth('tenant')->id());
        } else {
            $offer->update(['status' => $newStatus]);
        }

        // Fire notification if accepted
        if ($newStatus === 'accepted' && $offer->assigned_to) {
            $assignee = \InovCom\Users\Models\User::on('tenant')->find($offer->assigned_to);
            if ($assignee) {
                $assignee->notify(new \App\Notifications\OfferAccepted($offer));
            }
        }
    }

    public function render()
    {
        $columns = [
            'draft'     => ['label' => __('Brouillon'),  'color' => '#94a3b8'],
            'submitted' => ['label' => __('Envoyée'),    'color' => '#6366f1'],
            'accepted'  => ['label' => __('Acceptée'),   'color' => '#22c55e'],
            'refused'   => ['label' => __('Refusée'),    'color' => '#ef4444'],
            'archived'  => ['label' => __('Archivée'),   'color' => '#64748b'],
        ];

        $offers = Offer::on('tenant')
            ->with(['client', 'assignedUser'])
            ->whereIn('status', array_keys($columns))
            ->orderBy('received_at', 'desc')
            ->get()
            ->groupBy('status');

        return view('inovcom-offres::livewire.offers.kanban', [
            'columns' => $columns,
            'offers'  => $offers,
        ])->layout('layouts.app', [
            'title'    => __('Offres — Kanban'),
            'subtitle' => __('Vue pipeline par statut'),
        ]);
    }
}
