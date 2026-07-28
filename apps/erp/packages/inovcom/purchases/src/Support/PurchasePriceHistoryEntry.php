<?php

namespace InovCom\Purchases\Support;

use Carbon\Carbon;
use InovCom\Providers\Models\Provider;

readonly class PurchasePriceHistoryEntry
{
    public function __construct(
        public string $type,
        public Carbon $recorded_at,
        public float $quantity,
        public ?Provider $provider,
        public float $primary_amount,
        public string $primary_currency,
        public ?float $indicative_fcfa,
        public ?int $order_id,
        public ?string $order_number,
        public string $order_route,
        public int $sort_id,
        public ?int $source_line_id = null,
    ) {}

    public function isLocal(): bool
    {
        return $this->type === 'local';
    }

    public function isForeign(): bool
    {
        return $this->type === 'foreign';
    }
}
