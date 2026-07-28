<?php

namespace InovCom\Reservations\Services;

use App\Services\ModuleRegistry;
use App\Services\StoreContextService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Quotations\Services\QuotationsService;
use InovCom\Reservations\Models\Reservation;
use InovCom\Reservations\Models\ReservationLine;
use InovCom\Stock\Services\StockService;

class ReservationService
{
    public function __construct(
        private StockService $stock
    ) {
    }

    public function create(array $header, array $lines, ?int $userId = null): Reservation
    {
        if (count($lines) === 0) {
            throw new \RuntimeException('Ajoutez au moins un article à réserver.');
        }

        return DB::connection('tenant')->transaction(function () use ($header, $lines, $userId) {
            $reservation = new Reservation();
            $reservation->reference = $this->generateReference();
            $reservation->client_id = (int) $header['client_id'];
            $reservation->status = Reservation::STATUS_ACTIVE;
            $reservation->reservation_date = $header['reservation_date'];
            $reservation->expected_date = $header['expected_date'] ?? null;
            $reservation->notes = $header['notes'] ?? null;
            $reservation->created_by = $userId ?? auth('tenant')->id();

            if (Schema::connection('tenant')->hasColumn('reservations', 'store_id')) {
                $reservation->store_id = app(StoreContextService::class)->currentStoreId();
            }

            $reservation->save();

            foreach ($lines as $row) {
                $this->addLine($reservation, $row, $userId);
            }

            return $reservation->fresh(['lines', 'client', 'creator']);
        });
    }

    /**
     * @param array{item_id:int, quantity:float, unit_price?:float} $row
     */
    public function addLine(Reservation $reservation, array $row, ?int $userId = null): ReservationLine
    {
        if (! $reservation->isActive()) {
            throw new \RuntimeException('Seules les réservations actives peuvent être modifiées.');
        }

        $item = Item::findOrFail((int) $row['item_id']);
        $qty = (float) ($row['quantity'] ?? 0);
        if ($qty <= 0) {
            throw new \RuntimeException('Quantité invalide.');
        }

        $price = (float) ($row['unit_price'] ?? $item->price ?? 0);

        $run = function () use ($reservation, $item, $qty, $price, $userId) {
            $existing = ReservationLine::query()
                ->where('reservation_id', $reservation->id)
                ->where('item_id', $item->id)
                ->get()
                ->first(fn (ReservationLine $line) => $line->active_quantity > 0);

            $this->stock->reserveStock(
                $item->id,
                $qty,
                'reservation',
                $reservation->id,
                'Réservation ' . $reservation->reference,
                $userId
            );

            if ($existing) {
                $existing->quantity = (float) $existing->quantity + $qty;
                $existing->unit_price = $price;
                $existing->line_total = round($existing->active_quantity * $price, 2);
                $existing->save();

                return $existing->fresh();
            }

            return ReservationLine::create([
                'reservation_id' => $reservation->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_sku' => $item->sku,
                'quantity' => $qty,
                'quantity_cancelled' => 0,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2),
            ]);
        };

        if (DB::connection('tenant')->transactionLevel() > 0) {
            return $run();
        }

        return DB::connection('tenant')->transaction($run);
    }

    public function cancelLineQuantity(ReservationLine $line, float $quantity, ?int $userId = null, ?string $reason = null): ReservationLine
    {
        $reservation = $line->reservation()->firstOrFail();

        if (!$reservation->isActive()) {
            throw new \RuntimeException('Cette réservation ne peut plus être modifiée.');
        }

        if ($quantity <= 0) {
            throw new \RuntimeException('Indiquez une quantité à annuler.');
        }

        $active = $line->active_quantity;
        if ($quantity - $active > 1e-9) {
            throw new \RuntimeException('Quantité à annuler supérieure au reste réservé.');
        }

        return DB::connection('tenant')->transaction(function () use ($line, $reservation, $quantity, $userId, $reason) {
            $this->stock->releaseStock(
                $line->item_id,
                $quantity,
                'reservation',
                $reservation->id,
                $reason ?: ('Annulation partielle ' . $reservation->reference),
                $userId
            );

            $line->quantity_cancelled = (float) $line->quantity_cancelled + $quantity;
            $line->line_total = round($line->active_quantity * (float) $line->unit_price, 2);
            $line->save();

            $this->refreshStatusAfterLineChange($reservation);

            return $line->fresh();
        });
    }

    public function cancelReservation(Reservation $reservation, ?int $userId = null, ?string $reason = null): Reservation
    {
        if (!$reservation->isActive()) {
            throw new \RuntimeException('Cette réservation est déjà clôturée.');
        }

        return DB::connection('tenant')->transaction(function () use ($reservation, $userId, $reason) {
            $reservation->load('lines');

            foreach ($reservation->lines as $line) {
                $active = $line->active_quantity;
                if ($active <= 0) {
                    continue;
                }

                $this->stock->releaseStock(
                    $line->item_id,
                    $active,
                    'reservation',
                    $reservation->id,
                    $reason ?: ('Annulation ' . $reservation->reference),
                    $userId
                );

                $line->quantity_cancelled = (float) $line->quantity;
                $line->line_total = 0;
                $line->save();
            }

            $reservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_by' => $userId ?? auth('tenant')->id(),
                'cancelled_at' => now(),
            ]);

            return $reservation->fresh(['lines', 'client', 'creator']);
        });
    }

    public function convertToQuotation(Reservation $reservation, ?int $userId = null): object
    {
        if (!$reservation->isActive()) {
            throw new \RuntimeException('Seule une réservation active peut être convertie.');
        }

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant || !app(ModuleRegistry::class)->isEnabled('quotations', $tenant)) {
            throw new \RuntimeException('Le module Devis doit être activé pour convertir une réservation.');
        }

        if (!class_exists(QuotationsService::class)) {
            throw new \RuntimeException('Service devis indisponible.');
        }

        $reservation->load('lines');

        $quoteLines = [];
        foreach ($reservation->lines as $line) {
            $qty = $line->active_quantity;
            if ($qty <= 0) {
                continue;
            }

            $quoteLines[] = [
                'item_id' => $line->item_id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => $qty,
                'unit_price' => (float) $line->unit_price,
                'line_discount' => 0,
                'line_total' => round($qty * (float) $line->unit_price, 2),
            ];
        }

        if (count($quoteLines) === 0) {
            throw new \RuntimeException('Aucune quantité active à convertir.');
        }

        return DB::connection('tenant')->transaction(function () use ($reservation, $quoteLines, $userId) {
            $quotation = app(QuotationsService::class)->create([
                'client_id' => $reservation->client_id,
                'quote_date' => now()->toDateString(),
                'valid_until' => $reservation->expected_date?->toDateString() ?? now()->addDays(7)->toDateString(),
                'notes' => trim(($reservation->notes ?? '') . "\n\nIssu de la réservation " . $reservation->reference),
                'discount_percent' => 0,
                'apply_tax' => false,
            ], $quoteLines, $userId);

            foreach ($reservation->lines as $line) {
                $qty = $line->active_quantity;
                if ($qty <= 0) {
                    continue;
                }

                $this->stock->transferReservation(
                    $line->item_id,
                    $qty,
                    'reservation',
                    $reservation->id,
                    'quotation',
                    $quotation->id,
                    'Conversion réservation ' . $reservation->reference . ' → devis ' . $quotation->number,
                    $userId
                );
            }

            $reservation->update([
                'status' => Reservation::STATUS_CONVERTED,
                'quotation_id' => $quotation->id,
                'converted_at' => now(),
            ]);

            return $quotation;
        });
    }

    private function refreshStatusAfterLineChange(Reservation $reservation): void
    {
        $reservation->load('lines');
        $activeTotal = $reservation->activeQuantityTotal();

        if ($activeTotal <= 1e-9) {
            $reservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_by' => auth('tenant')->id(),
                'cancelled_at' => now(),
            ]);
        }
    }

    private function generateReference(): string
    {
        $prefix = 'RES-' . now()->format('Ym') . '-';
        $last = Reservation::query()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
