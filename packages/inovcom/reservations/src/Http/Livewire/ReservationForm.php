<?php

namespace InovCom\Reservations\Http\Livewire;

use App\Services\StoreContextService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Reservations\Concerns\AuthorizesReservationActions;
use InovCom\Reservations\Services\ReservationService;
use InovCom\Stock\Services\StockService;
use Livewire\Component;

class ReservationForm extends Component
{
    use AuthorizesReservationActions;

    public ?int $client_id = null;

    public string $reservation_date = '';

    public string $expected_date = '';

    public ?string $notes = null;

    public string $clientSearch = '';

    public array $clientResults = [];

    public ?array $clientPicker = null;

    public array $cart = [];

    public string $itemSearch = '';

    public array $searchResults = [];

    public function mount(): void
    {
        $this->authorizeReservationAction('reservations.create');
        $this->reservation_date = now()->format('Y-m-d');
        $this->expected_date = now()->addDays(2)->format('Y-m-d');
    }

    public function updatedClientSearch(): void
    {
        if ($this->clientPicker !== null) {
            return;
        }

        $term = trim($this->clientSearch);
        if (strlen($term) < 1) {
            $this->clientResults = [];

            return;
        }

        $like = '%' . mb_strtolower($term) . '%';
        $this->clientResults = Client::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'code', 'type', 'phone'])
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'phone' => $c->phone,
                'type_label' => $c->type === 'individual' ? 'Particulier' : 'Entreprise',
            ])
            ->all();
    }

    public function pickClient(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $this->client_id = $client->id;
        $this->clientPicker = [
            'id' => $client->id,
            'name' => $client->name,
            'code' => $client->code,
            'type_label' => $client->type === 'individual' ? 'Particulier' : 'Entreprise',
        ];
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    public function clearClient(): void
    {
        $this->client_id = null;
        $this->clientPicker = null;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    public function updatedItemSearch(): void
    {
        $term = trim($this->itemSearch);
        if (strlen($term) < 1) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->searchItems($term);
    }

    public function addItemToCart(int $itemId): void
    {
        $item = Item::query()->where('is_active', true)->find($itemId);
        if (! $item) {
            return;
        }

        $available = $this->availableQtyFor((int) $item->id);
        if ($available !== null && $available <= 0) {
            session()->flash('error', 'Stock disponible insuffisant pour cet article.');

            return;
        }

        foreach ($this->cart as $idx => $row) {
            if ((int) $row['item_id'] === (int) $item->id) {
                $newQty = (float) $row['quantity'] + 1;
                if ($available !== null && $newQty > $available) {
                    session()->flash('error', 'Stock disponible insuffisant.');

                    return;
                }
                $this->cart[$idx]['quantity'] = (string) $newQty;
                $this->cart[$idx]['line_total'] = (string) round($newQty * (float) $row['unit_price'], 2);
                $this->itemSearch = '';
                $this->searchResults = [];

                return;
            }
        }

        $price = (float) ($item->price ?? 0);
        $this->cart[] = [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_sku' => $item->sku,
            'quantity' => '1',
            'unit_price' => (string) $price,
            'line_total' => (string) $price,
            'available_qty' => $available,
        ];
        $this->itemSearch = '';
        $this->searchResults = [];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updatedCart($value, string $key): void
    {
        if (! preg_match('/^(\d+)\.(quantity|unit_price)$/', $key, $m)) {
            return;
        }

        $index = (int) $m[1];
        if (! isset($this->cart[$index])) {
            return;
        }

        $qty = max(0, (float) ($this->cart[$index]['quantity'] ?? 0));
        $price = max(0, (float) ($this->cart[$index]['unit_price'] ?? 0));
        $this->cart[$index]['quantity'] = (string) $qty;
        $this->cart[$index]['unit_price'] = (string) $price;
        $this->cart[$index]['line_total'] = (string) round($qty * $price, 2);
    }

    public function save(): void
    {
        $this->authorizeReservationAction('reservations.create');

        $this->validate([
            'client_id' => 'required|integer|exists:tenant.clients,id',
            'reservation_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:reservation_date',
            'notes' => 'nullable|string|max:2000',
            'cart' => 'required|array|min:1',
        ]);

        foreach ($this->cart as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                session()->flash('error', 'Chaque ligne doit avoir une quantité supérieure à 0.');

                return;
            }
            $available = $this->availableQtyFor((int) $row['item_id']);
            if ($available !== null && $qty > $available) {
                session()->flash(
                    'error',
                    'Stock insuffisant pour « ' . ($row['item_name'] ?? 'article') . ' » (dispo : ' . fmt_num($available) . ').'
                );

                return;
            }
        }

        try {
            $lines = [];
            foreach ($this->cart as $row) {
                $lines[] = [
                    'item_id' => (int) $row['item_id'],
                    'quantity' => (float) $row['quantity'],
                    'unit_price' => (float) $row['unit_price'],
                ];
            }

            $reservation = app(ReservationService::class)->create([
                'client_id' => $this->client_id,
                'reservation_date' => $this->reservation_date,
                'expected_date' => $this->expected_date ?: null,
                'notes' => $this->notes,
            ], $lines, Auth::guard('tenant')->id());

            session()->flash('success', 'Réservation ' . $reservation->reference . ' créée. Stock réservé.');

            $this->redirect(route('tenant.reservations.show', [
                'reservation' => $reservation->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('inovcom-reservations::livewire.reservations.form')
            ->layout('layouts.app', [
                'title' => 'Nouvelle réservation',
                'subtitle' => 'Réserver des produits pour un client',
            ])
            ->with(['tenantCode' => $this->tenantCode()]);
    }

    /**
     * @return list<array{id:int,name:string,sku:?string,barcode:?string,price:float,available_qty:?float}>
     */
    private function searchItems(string $term): array
    {
        $like = '%' . mb_strtolower($term) . '%';

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'price' => (float) ($item->price ?? 0),
            'available_qty' => $this->availableQtyFor((int) $item->id),
        ])->all();
    }

    private function availableQtyFor(int $itemId): ?float
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels')) {
            return null;
        }

        try {
            $storeId = app(StoreContextService::class)->currentStoreId();

            return app(StockService::class)->getAvailableQuantity($itemId, $storeId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
