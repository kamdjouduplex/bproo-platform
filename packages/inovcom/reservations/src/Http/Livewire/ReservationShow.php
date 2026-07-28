<?php

namespace InovCom\Reservations\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Items\Models\Item;
use InovCom\Reservations\Concerns\AuthorizesReservationActions;
use InovCom\Reservations\Models\Reservation;
use InovCom\Reservations\Models\ReservationLine;
use InovCom\Reservations\Services\ReservationService;
use InovCom\Stock\Services\StockService;
use Livewire\Component;

class ReservationShow extends Component
{
    use AuthorizesReservationActions;

    public Reservation $reservation;

    public string $itemSearch = '';
    public array $searchResults = [];

    /** @var array<int, string> */
    public array $cancelQty = [];

    public function mount(Reservation $reservation): void
    {
        $this->authorizeReservationAction('reservations.view');
        $this->reservation = $reservation->load(['lines.item', 'client', 'creator', 'canceller', 'quotation']);
    }

    public function updatedItemSearch(): void
    {
        if (! $this->reservation->isActive() || ! $this->can('reservations.update')) {
            $this->searchResults = [];

            return;
        }

        $term = trim($this->itemSearch);
        if (strlen($term) < 1) {
            $this->searchResults = [];

            return;
        }

        $like = '%' . mb_strtolower($term) . '%';
        $stockEnabled = \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('stock_levels');
        $stock = $stockEnabled ? app(StockService::class) : null;
        $storeId = app(\App\Services\StoreContextService::class)->currentStoreId();

        $this->searchResults = Item::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function (Item $item) use ($stock, $storeId) {
                $available = null;
                if ($stock) {
                    try {
                        $available = $stock->getAvailableQuantity($item->id, $storeId);
                    } catch (\Throwable) {
                        $available = null;
                    }
                }

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'price' => (float) ($item->price ?? 0),
                    'available_qty' => $available,
                ];
            })
            ->all();
    }

    public function addLine(int $itemId): void
    {
        $this->authorizeReservationAction('reservations.update');

        try {
            app(ReservationService::class)->addLine($this->reservation, [
                'item_id' => $itemId,
                'quantity' => 1,
            ], Auth::guard('tenant')->id());

            $this->refreshReservation();
            $this->itemSearch = '';
            $this->searchResults = [];
            session()->flash('success', 'Article ajouté à la réservation.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelLine(int $lineId): void
    {
        $this->authorizeReservationAction('reservations.cancel');

        $qty = (float) ($this->cancelQty[$lineId] ?? 0);
        if ($qty <= 0) {
            session()->flash('error', 'Indiquez la quantité à libérer.');
            return;
        }

        try {
            $line = ReservationLine::where('reservation_id', $this->reservation->id)->findOrFail($lineId);
            app(ReservationService::class)->cancelLineQuantity($line, $qty, Auth::guard('tenant')->id());
            $this->refreshReservation();
            unset($this->cancelQty[$lineId]);
            session()->flash('success', 'Quantité libérée — stock restauré.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelAll(): void
    {
        $this->authorizeReservationAction('reservations.cancel');

        try {
            app(ReservationService::class)->cancelReservation($this->reservation, Auth::guard('tenant')->id());
            $this->refreshReservation();
            session()->flash('success', 'Réservation annulée — stock entièrement restauré.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function convertToQuotation(): void
    {
        $this->authorizeReservationAction('reservations.convert');

        try {
            $quotation = app(ReservationService::class)->convertToQuotation(
                $this->reservation,
                Auth::guard('tenant')->id()
            );
            $this->refreshReservation();
            session()->flash('success', 'Devis ' . $quotation->number . ' créé à partir de la réservation.');

            if (Route::has('tenant.quotations.edit')) {
                $this->redirect(route('tenant.quotations.edit', [
                    'quotation' => $quotation->id,
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function refreshReservation(): void
    {
        $this->reservation = $this->reservation->fresh(['lines.item', 'client', 'creator', 'canceller', 'quotation']);
    }

    public function render()
    {
        return view('inovcom-reservations::livewire.reservations.show')
            ->layout('layouts.app', [
                'title' => 'Réservation ' . $this->reservation->reference,
                'subtitle' => $this->reservation->client?->name ?? 'Client',
            ])
            ->with([
                'tenantCode' => $this->tenantCode(),
                'canUpdate' => $this->can('reservations.update'),
                'canCancel' => $this->can('reservations.cancel'),
                'canConvert' => $this->can('reservations.convert') && Route::has('tenant.quotations.edit'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
