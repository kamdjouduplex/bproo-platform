<?php

namespace InovCom\Items\Services;

use InovCom\Items\Models\Item;
use InovCom\Kernel\Contracts\ItemsApi;
use Illuminate\Support\Collection;

class ItemsApiService implements ItemsApi
{
    public function findItem(string $sku): ?object
    {
        return Item::on('tenant')
            ->where('sku', $sku)
            ->first();
    }

    public function findItemById(int $id): ?object
    {
        return Item::on('tenant')->find($id);
    }

    public function getItemPrice(string $sku, ?int $unitId = null): ?float
    {
        $item = $this->findItem($sku);
        if (!$item) {
            return null;
        }
        if ($unitId) {
            $up = $item->unitPrices()->where('unit_id', $unitId)->first();
            return $up ? (float) $up->price : (float) $item->price;
        }
        return (float) $item->price;
    }

    public function isItemActive(string $sku): bool
    {
        $item = $this->findItem($sku);
        return $item && $item->is_active;
    }

    public function getItemCost(string $sku): ?float
    {
        $item = $this->findItem($sku);
        return $item?->cost;
    }

    public function getActiveItems(): Collection
    {
        return Item::on('tenant')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function itemExists(string $sku): bool
    {
        return Item::on('tenant')
            ->where('sku', $sku)
            ->exists();
    }
}
