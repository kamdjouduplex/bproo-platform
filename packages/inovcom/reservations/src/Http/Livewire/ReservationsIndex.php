<?php

namespace InovCom\Reservations\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Reservations\Concerns\AuthorizesReservationActions;
use InovCom\Reservations\Models\Reservation;
use InovCom\Reservations\Services\ReservationService;
use Livewire\Component;
use Livewire\WithPagination;

class ReservationsIndex extends Component
{
    use AuthorizesReservationActions;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public int $perPage = 20;

    public function mount(): void
    {
        $this->authorizeReservationAction('reservations.view');
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Reservation::query()
            ->with(['client', 'creator'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('reference', 'like', $term)
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->orderByDesc('reservation_date')
            ->orderByDesc('id');

        return view('inovcom-reservations::livewire.reservations.index')
            ->layout('layouts.app', [
                'title' => 'Réservations',
                'subtitle' => 'Produits réservés pour clients',
            ])
            ->with([
                'reservations' => $query->paginate($this->perPage),
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $this->can('reservations.create'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
