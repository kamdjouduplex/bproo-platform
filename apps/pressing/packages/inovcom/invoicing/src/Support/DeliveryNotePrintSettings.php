<?php

namespace InovCom\Invoicing\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\DeliveryNote;

class DeliveryNotePrintSettings
{
    public static function columnsReady(): bool
    {
        return Schema::connection('tenant')->hasColumn('delivery_notes', 'show_prices');
    }

    public static function resolveShowPrices(DeliveryNote $note, ?Request $request = null): bool
    {
        if ($request?->has('show_prices')) {
            return $request->boolean('show_prices');
        }

        if (self::columnsReady()) {
            return (bool) $note->show_prices;
        }

        return false;
    }

    public static function resolveShowDiscounts(DeliveryNote $note, ?Request $request = null): bool
    {
        if ($request?->has('show_discounts')) {
            return $request->boolean('show_discounts');
        }

        if (self::columnsReady()) {
            return (bool) $note->show_discounts;
        }

        return false;
    }

    public static function resolvePurchaseOrder(DeliveryNote $note, ?Request $request = null): ?string
    {
        $fromRequest = trim((string) ($request?->query('purchase_order') ?? ''));
        if ($fromRequest !== '') {
            return $fromRequest;
        }

        $note->loadMissing(['quotation', 'invoice']);

        if (self::columnsReady()) {
            $po = trim((string) ($note->customer_purchase_order ?? ''));
            if ($po !== '') {
                return $po;
            }
        }

        $fromQuotation = trim((string) ($note->quotation?->customer_purchase_order ?? ''));
        if ($fromQuotation !== '') {
            return $fromQuotation;
        }

        $fromInvoice = trim((string) ($note->invoice?->customer_reference ?? ''));

        return $fromInvoice !== '' ? $fromInvoice : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function printRouteQuery(DeliveryNote $note): array
    {
        $note->loadMissing(['quotation', 'invoice']);

        $query = [];

        if (self::columnsReady() && $note->show_prices) {
            $query['show_prices'] = 1;
            if ($note->show_discounts) {
                $query['show_discounts'] = 1;
            }
        }

        $po = self::resolvePurchaseOrder($note);
        if ($po !== null) {
            $query['purchase_order'] = $po;
        }

        return $query;
    }
}
