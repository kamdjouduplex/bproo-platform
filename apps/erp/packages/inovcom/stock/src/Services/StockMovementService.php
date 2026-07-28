<?php

namespace InovCom\Stock\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InovCom\Inventory\Models\Adjustment as InventoryAdjustment;
use InovCom\Items\Models\Item;
use InovCom\Losses\Models\LossRecord;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Models\ReceiptNote;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleReturn;
use InovCom\Stock\Models\StockMovement;
use InovCom\Users\Models\User;

class StockMovementService
{
    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('stock_movements');
    }

    public function queryMovements(array $filters = [])
    {
        $storeId = $filters['store_id'] ?? $this->resolveStoreId();

        return StockMovement::query()
            ->with(['item:id,name,sku', 'creator:id,name'])
            ->when(
                Schema::connection('tenant')->hasColumn('stock_movements', 'store_id') && $storeId,
                fn ($q) => $q->where('store_id', $storeId)
            )
            ->when(!empty($filters['item_id']), fn ($q) => $q->where('item_id', (int) $filters['item_id']))
            ->when(!empty($filters['reference_type']), function ($q) use ($filters) {
                $q->where('reference_type', $filters['reference_type']);
                if (!empty($filters['reference_id'])) {
                    $q->where('reference_id', (int) $filters['reference_id']);
                }
            })
            ->when(!empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(($filters['direction'] ?? '') === 'in', function ($q) {
                $q->where('quantity', '>', 0)->where('type', '!=', 'reserve');
            })
            ->when(($filters['direction'] ?? '') === 'out', function ($q) {
                $q->where('quantity', '<', 0)->where('type', '!=', 'release');
            })
            ->when(($filters['direction'] ?? '') === 'reserve', function ($q) {
                $q->whereIn('type', ['reserve', 'release']);
            })
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . strtolower(trim($filters['search'])) . '%';
                $q->whereHas('item', function ($iq) use ($term) {
                    $iq->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->queryMovements($filters)->paginate($perPage);
    }

    /**
     * Totaux pour le jeu de filtres courant (sans pagination).
     *
     * @return array{count:int,total_in:float,total_out:float,net:float,total_reserved:float}
     */
    public function summarize(array $filters = []): array
    {
        $agg = $this->queryMovements($filters)
            ->reorder()
            ->selectRaw('COUNT(*) as movement_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity > 0 AND type <> \'reserve\' THEN quantity ELSE 0 END), 0) as total_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity < 0 AND type <> \'release\' THEN ABS(quantity) ELSE 0 END), 0) as total_out')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = \'reserve\' THEN ABS(quantity) ELSE 0 END), 0) as total_reserved')
            ->first();

        $totalIn = (float) ($agg->total_in ?? 0);
        $totalOut = (float) ($agg->total_out ?? 0);

        return [
            'count' => (int) ($agg->movement_count ?? 0),
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'net' => $totalIn - $totalOut,
            'total_reserved' => (float) ($agg->total_reserved ?? 0),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForItem(int $itemId, int $limit = 50, ?int $storeId = null): Collection
    {
        $movements = $this->queryMovements([
            'item_id' => $itemId,
            'store_id' => $storeId,
        ])->limit($limit)->get();

        return $this->enrich($movements);
    }

    /**
     * Mouvements liés à une vente (vente + retours associés).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForSale(int $saleId, int $limit = 100): Collection
    {
        $returnIds = [];
        if (Schema::connection('tenant')->hasTable('sale_returns')) {
            $returnIds = SaleReturn::query()
                ->where('sale_id', $saleId)
                ->pluck('id')
                ->all();
        }

        $storeId = $this->resolveStoreId();

        $movements = StockMovement::query()
            ->with(['item:id,name,sku', 'creator:id,name'])
            ->when(
                Schema::connection('tenant')->hasColumn('stock_movements', 'store_id') && $storeId,
                fn ($q) => $q->where('store_id', $storeId)
            )
            ->where(function ($q) use ($saleId, $returnIds) {
                $q->where(function ($sub) use ($saleId) {
                    $sub->where('reference_type', 'sale')->where('reference_id', $saleId);
                });
                if ($returnIds !== []) {
                    $q->orWhere(function ($sub) use ($returnIds) {
                        $sub->where('reference_type', 'sale_return')->whereIn('reference_id', $returnIds);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->enrich($movements);
    }

    /**
     * @param Collection<int, StockMovement> $movements
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $movements): Collection
    {
        if ($movements->isEmpty()) {
            return collect();
        }

        $refs = $this->loadReferenceLabels($movements);
        $tenantCode = $this->tenantCode();

        return $movements->map(function (StockMovement $m) use ($refs, $tenantCode) {
            $refKey = strtolower((string) $m->reference_type) . ':' . (int) $m->reference_id;
            $ref = $refs[$refKey] ?? null;

            $type = strtolower((string) $m->type);
            $isReserveFlow = in_array($type, ['reserve', 'release'], true);
            $qty = $this->resolveMovementQuantity($m);
            $before = (float) $m->quantity_before;
            $after = (float) $m->quantity_after;
            $typeLabel = $this->movementTypeLabel($m);
            $refLabel = $ref['label'] ?? $this->fallbackReferenceLabel($m);

            if ($type === 'reserve') {
                $direction = 'reserve';
                $directionLabel = 'Réservation';
            } elseif ($type === 'release') {
                $direction = 'release';
                $directionLabel = 'Libération';
            } else {
                $direction = $qty >= 0 ? 'in' : 'out';
                $directionLabel = $qty >= 0 ? 'Entrée' : 'Sortie';
            }

            return [
                'id' => $m->id,
                'created_at' => $m->created_at,
                'item_id' => $m->item_id,
                'item_name' => $m->item?->name ?? '—',
                'item_sku' => $m->item?->sku,
                'type' => $m->type,
                'type_label' => $typeLabel,
                'direction' => $direction,
                'direction_label' => $directionLabel,
                'is_reserve_flow' => $isReserveFlow,
                'quantity' => $qty,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $m->reason,
                'user_name' => $m->creator?->name,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'reference_label' => $refLabel,
                'reference_url' => $ref['url'] ?? null,
                'story' => $this->buildStory($typeLabel, $qty, $before, $after, $refLabel, $type),
                'item_movements_url' => $tenantCode
                    ? route('tenant.stock.movements.item', ['itemId' => $m->item_id, 'tenant' => $tenantCode])
                    : null,
            ];
        });
    }

    /**
     * Quantité affichée : pour d’anciennes réservations enregistrées à 0, on récupère la vraie qté.
     */
    private function resolveMovementQuantity(StockMovement $m): float
    {
        $qty = (float) $m->quantity;
        $type = strtolower((string) $m->type);

        if (abs($qty) > 1e-9 || ! in_array($type, ['reserve', 'release'], true)) {
            return $qty;
        }

        if (preg_match('/([+-]?\d+(?:[.,]\d+)?)/', (string) ($m->reason ?? ''), $match)) {
            $parsed = (float) str_replace(',', '.', $match[1]);
            if (abs($parsed) > 1e-9) {
                return $type === 'release' ? -abs($parsed) : abs($parsed);
            }
        }

        $refType = strtolower((string) $m->reference_type);
        if ($refType === 'reservation' && $m->reference_id && Schema::connection('tenant')->hasTable('reservation_lines')) {
            $lineQty = (float) \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('reservation_lines')
                ->where('reservation_id', (int) $m->reference_id)
                ->where('item_id', (int) $m->item_id)
                ->sum(\Illuminate\Support\Facades\DB::raw('quantity - COALESCE(quantity_cancelled, 0)'));

            if ($lineQty > 1e-9) {
                return $type === 'release' ? -$lineQty : $lineQty;
            }
        }

        return 0.0;
    }

    /**
     * Phrase lisible selon le type de mouvement.
     */
    public function buildStory(
        string $typeLabel,
        float $qty,
        float $before,
        float $after,
        ?string $refLabel = null,
        string $type = ''
    ): string {
        $type = strtolower($type);

        if ($type === 'reserve') {
            if (abs($before - $after) < 1e-9) {
                $story = sprintf(
                    '%s — %s unité(s) bloquée(s) · stock physique inchangé (%s)',
                    $typeLabel,
                    fmt_num(abs($qty)),
                    fmt_num($before)
                );
            } else {
                $story = sprintf(
                    '%s — %s unité(s) bloquée(s) · dispo %s → %s',
                    $typeLabel,
                    fmt_num(abs($qty)),
                    fmt_num($before),
                    fmt_num($after)
                );
            }
        } elseif ($type === 'release') {
            if (abs($before - $after) < 1e-9) {
                $story = sprintf(
                    '%s — %s unité(s) libérée(s) · stock physique inchangé (%s)',
                    $typeLabel,
                    fmt_num(abs($qty)),
                    fmt_num($before)
                );
            } else {
                $story = sprintf(
                    '%s — %s unité(s) libérée(s) · dispo %s → %s',
                    $typeLabel,
                    fmt_num(abs($qty)),
                    fmt_num($before),
                    fmt_num($after)
                );
            }
        } else {
            $in = $qty >= 0;
            $story = sprintf(
                '%s — %s %s · stock %s → %s',
                $typeLabel,
                $in ? 'entrée de' : 'sortie de',
                fmt_num(abs($qty)),
                fmt_num($before),
                fmt_num($after)
            );
        }

        if ($refLabel) {
            $story .= ' (' . $refLabel . ')';
        }

        return $story;
    }

    public function movementTypeLabel(StockMovement $m): string
    {
        $ref = strtolower((string) $m->reference_type);
        $type = strtolower((string) $m->type);
        $reason = strtolower((string) ($m->reason ?? ''));

        if ($reason === 'batch_consume') {
            return 'Sortie (lot)';
        }
        if ($reason === 'batch_receipt') {
            return 'Entrée (lot)';
        }

        return match (true) {
            $ref === 'sale' => 'Vente',
            $ref === 'sale_return' => 'Retour vente',
            $ref === 'purchase' => 'Réception achat',
            $ref === 'purchasecancel' => 'Annulation achat',
            $ref === 'foreignpurchase' => 'Achat import',
            $ref === 'loss' => 'Perte',
            $ref === 'store_transfer' => 'Transfert magasin',
            $ref === 'adjustment' && $type === 'adjustment' => 'Inventaire',
            $ref === 'invoice_cancellation' => 'Annulation facture (retour)',
            $ref === 'invoice_replacement' => 'Facture remplacement',
            $ref === 'invoice_return', $type === 'return' => 'Retour article',
            $ref === 'invoice_return' => 'Retour article',
            $ref === 'delivery_note' => 'Livraison',
            $type === 'delivery' => 'Livraison',
            $ref === 'reservation' => 'Réservation',
            $ref === 'quotation' && $type === 'reserve' => 'Réservation devis',
            $type === 'reserve' => 'Réservation',
            $type === 'release' => 'Libération réservation',
            $type === 'adjustment' => 'Ajustement manuel',
            $type === 'transfer' => 'Transfert magasin',
            (float) $m->quantity >= 0 => 'Entrée',
            default => 'Sortie',
        };
    }

    /**
     * @param Collection<int, StockMovement> $movements
     * @return array<string, array{label: string, url: ?string}>
     */
    private function loadReferenceLabels(Collection $movements): array
    {
        $byType = [];
        foreach ($movements as $m) {
            if (!$m->reference_type || !$m->reference_id) {
                continue;
            }
            $key = strtolower($m->reference_type);
            $byType[$key][] = (int) $m->reference_id;
        }

        $out = [];
        $tenant = $this->tenantCode();

        foreach ($byType as $type => $ids) {
            $ids = array_values(array_unique($ids));
            foreach ($this->resolveReferences($type, $ids, $tenant) as $id => $data) {
                $out[$type . ':' . $id] = $data;
            }
        }

        return $out;
    }

    /**
     * @param array<int> $ids
     * @return array<int, array{label: string, url: ?string}>
     */
    private function resolveReferences(string $type, array $ids, ?string $tenant): array
    {
        $result = [];

        switch ($type) {
            case 'sale':
                if (Schema::connection('tenant')->hasTable('sales')) {
                    foreach (Sale::query()->whereIn('id', $ids)->get(['id', 'sale_number']) as $sale) {
                        $result[$sale->id] = [
                            'label' => 'Vente ' . $sale->sale_number,
                            'url' => $tenant && Route::has('tenant.sales.show')
                                ? route('tenant.sales.show', [$sale->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'sale_return':
                if (Schema::connection('tenant')->hasTable('sale_returns')) {
                    foreach (SaleReturn::query()->whereIn('id', $ids)->get(['id', 'return_number', 'sale_id']) as $ret) {
                        $result[$ret->id] = [
                            'label' => 'Retour ' . $ret->return_number,
                            'url' => $tenant && Route::has('tenant.sales.returns.show')
                                ? route('tenant.sales.returns.show', ['saleReturn' => $ret->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'purchase':
                if (Schema::connection('tenant')->hasTable('receipt_notes')) {
                    foreach (ReceiptNote::query()->whereIn('id', $ids)->with('purchaseOrder:id,order_number')->get() as $receipt) {
                        $orderNum = $receipt->purchaseOrder?->order_number ?? $receipt->receipt_number;
                        $result[$receipt->id] = [
                            'label' => 'Réception ' . ($receipt->receipt_number ?? $orderNum),
                            'url' => $tenant && Route::has('tenant.purchases.show') && $receipt->purchase_order_id
                                ? route('tenant.purchases.show', [$receipt->purchase_order_id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'purchasecancel':
                if (Schema::connection('tenant')->hasTable('purchase_orders')) {
                    foreach (PurchaseOrder::query()->whereIn('id', $ids)->get(['id', 'order_number']) as $order) {
                        $result[$order->id] = [
                            'label' => 'Commande ' . $order->order_number,
                            'url' => $tenant && Route::has('tenant.purchases.show')
                                ? route('tenant.purchases.show', [$order->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'loss':
                if (Schema::connection('tenant')->hasTable('loss_records')) {
                    foreach (LossRecord::query()->whereIn('id', $ids)->get(['id', 'reference']) as $loss) {
                        $result[$loss->id] = [
                            'label' => 'Perte ' . ($loss->reference ?? '#' . $loss->id),
                            'url' => null,
                        ];
                    }
                }
                break;

            case 'adjustment':
                if (Schema::connection('tenant')->hasTable('adjustments')) {
                    foreach (InventoryAdjustment::query()->whereIn('id', $ids)->get(['id', 'reference']) as $adj) {
                        $result[$adj->id] = [
                            'label' => 'Inventaire ' . ($adj->reference ?? '#' . $adj->id),
                            'url' => $tenant && Route::has('tenant.inventory.index')
                                ? route('tenant.inventory.index', ['tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'invoice_return':
                if (Schema::connection('tenant')->hasTable('invoice_returns')) {
                    $returns = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('invoice_returns')
                        ->whereIn('id', $ids)
                        ->get(['id', 'return_number']);
                    foreach ($returns as $ret) {
                        $result[$ret->id] = [
                            'label' => 'Avoir ' . $ret->return_number,
                            'url' => null,
                        ];
                    }
                }
                break;

            case 'delivery_note':
                if (Schema::connection('tenant')->hasTable('delivery_notes')) {
                    $notes = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('delivery_notes')
                        ->whereIn('id', $ids)
                        ->get(['id', 'delivery_number', 'invoice_id']);
                    foreach ($notes as $note) {
                        $result[$note->id] = [
                            'label' => 'BL ' . $note->delivery_number,
                            'url' => $tenant && Route::has('tenant.invoicing.deliveries.show')
                                ? route('tenant.invoicing.deliveries.show', ['deliveryNote' => $note->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'reservation':
                if (Schema::connection('tenant')->hasTable('reservations')) {
                    $reservations = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('reservations')
                        ->whereIn('id', $ids)
                        ->get(['id', 'reference']);
                    foreach ($reservations as $reservation) {
                        $result[$reservation->id] = [
                            'label' => 'Réservation ' . ($reservation->reference ?? '#' . $reservation->id),
                            'url' => $tenant && Route::has('tenant.reservations.show')
                                ? route('tenant.reservations.show', ['reservation' => $reservation->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'foreignpurchase':
                if (Schema::connection('tenant')->hasTable('foreign_purchase_orders')) {
                    $purchases = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('foreign_purchase_orders')
                        ->whereIn('id', $ids)
                        ->get(['id', 'order_number']);
                    foreach ($purchases as $purchase) {
                        $result[$purchase->id] = [
                            'label' => 'Achat import ' . ($purchase->order_number ?? '#' . $purchase->id),
                            'url' => $tenant && Route::has('tenant.foreign_purchases.show')
                                ? route('tenant.foreign_purchases.show', ['foreignPurchase' => $purchase->id, 'tenant' => $tenant])
                                : null,
                        ];
                    }
                }
                break;

            case 'store_transfer':
                foreach ($ids as $id) {
                    $result[$id] = [
                        'label' => 'Transfert #' . $id,
                        'url' => null,
                    ];
                }
                break;
        }

        return $result;
    }

    private function fallbackReferenceLabel(StockMovement $m): ?string
    {
        if (!$m->reference_type && !$m->reason) {
            return null;
        }

        if ($m->reference_type && $m->reference_id) {
            return ucfirst($m->reference_type) . ' #' . $m->reference_id;
        }

        return $m->reason;
    }

    private function resolveStoreId(): ?int
    {
        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }

    private function tenantCode(): ?string
    {
        $tenant = app(TenantManager::class)->tenant()
            ?? request()->attributes->get('tenant');

        return $tenant?->code
            ?? request()->query('tenant')
            ?? session('tenant_code');
    }

    public static function referenceTypeOptions(): array
    {
        return [
            '' => 'Toutes origines',
            'sale' => 'Vente',
            'sale_return' => 'Retour vente',
            'Purchase' => 'Réception achat',
            'PurchaseCancel' => 'Annulation achat',
            'ForeignPurchase' => 'Achat import',
            'Loss' => 'Perte',
            'Adjustment' => 'Inventaire / ajustement',
            'reservation' => 'Réservation',
            'invoice_return' => 'Retour article',
            'invoice_cancellation' => 'Annulation facture',
            'invoice_replacement' => 'Facture remplacement',
            'delivery_note' => 'Bon de livraison',
            'store_transfer' => 'Transfert magasin',
        ];
    }
}
