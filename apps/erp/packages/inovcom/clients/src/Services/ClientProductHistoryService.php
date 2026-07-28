<?php

namespace InovCom\Clients\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;

class ClientProductHistoryService
{
    /**
     * @return array<int, array{
     *     type: string,
     *     type_label: string,
     *     document_id: int,
     *     document_number: string,
     *     document_date: string,
     *     item_sku: string|null,
     *     item_name: string,
     *     quantity: float,
     *     unit_price: float,
     *     line_total: float,
     *     link: string|null
     * }>
     */
    public function search(Client $client, string $sku, ?string $dateFrom, ?string $dateTo): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $rows = [];
        $tenantCode = $this->tenantCode();

        if (Schema::connection('tenant')->hasTable('quotation_lines')) {
            $rows = array_merge($rows, $this->searchQuotations($client->id, $sku, $dateFrom, $dateTo, $tenantCode));
        }

        if (Schema::connection('tenant')->hasTable('invoice_lines')) {
            $rows = array_merge($rows, $this->searchInvoices($client->id, $sku, $dateFrom, $dateTo, $tenantCode));
        }

        if (Schema::connection('tenant')->hasTable('delivery_note_lines')) {
            $rows = array_merge($rows, $this->searchDeliveryNotes($client->id, $sku, $dateFrom, $dateTo, $tenantCode));
        }

        if (Schema::connection('tenant')->hasTable('sale_lines')) {
            $rows = array_merge($rows, $this->searchSales($client->id, $sku, $dateFrom, $dateTo, $tenantCode));
        }

        usort($rows, function (array $a, array $b): int {
            $dateCompare = strcmp($b['document_date'], $a['document_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return $b['document_id'] <=> $a['document_id'];
        });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchQuotations(int $clientId, string $sku, ?string $dateFrom, ?string $dateTo, ?string $tenantCode): array
    {
        $query = DB::connection('tenant')->table('quotation_lines')
            ->join('quotations', 'quotation_lines.quotation_id', '=', 'quotations.id')
            ->where('quotations.client_id', $clientId)
            ->where($this->skuMatchColumn('quotation_lines.item_sku'), $this->skuMatchOperator(), $this->skuMatchValue($sku))
            ->select([
                'quotations.id as document_id',
                'quotations.number as document_number',
                'quotations.quote_date as document_date',
                'quotation_lines.item_sku',
                'quotation_lines.item_name',
                'quotation_lines.quantity',
                'quotation_lines.unit_price',
                'quotation_lines.line_total',
            ]);

        $this->applyDateFilter($query, 'quotations.quote_date', $dateFrom, $dateTo);

        return $query->get()->map(fn ($row) => $this->mapRow(
            'quotation',
            'Devis',
            (int) $row->document_id,
            (string) $row->document_number,
            (string) $row->document_date,
            $row->item_sku,
            (string) $row->item_name,
            (float) $row->quantity,
            (float) $row->unit_price,
            (float) $row->line_total,
            $this->documentLink('tenant.quotations.edit', ['quotation' => (int) $row->document_id], $tenantCode)
        ))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchInvoices(int $clientId, string $sku, ?string $dateFrom, ?string $dateTo, ?string $tenantCode): array
    {
        $query = DB::connection('tenant')->table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.client_id', $clientId)
            ->where($this->skuMatchColumn('invoice_lines.item_sku'), $this->skuMatchOperator(), $this->skuMatchValue($sku))
            ->select([
                'invoices.id as document_id',
                'invoices.invoice_number as document_number',
                'invoices.invoice_date as document_date',
                'invoice_lines.item_sku',
                'invoice_lines.item_name',
                'invoice_lines.quantity',
                'invoice_lines.unit_price',
                'invoice_lines.line_total',
            ]);

        $this->applyDateFilter($query, 'invoices.invoice_date', $dateFrom, $dateTo);

        return $query->get()->map(fn ($row) => $this->mapRow(
            'invoice',
            'Facture',
            (int) $row->document_id,
            (string) $row->document_number,
            (string) $row->document_date,
            $row->item_sku,
            (string) $row->item_name,
            (float) $row->quantity,
            (float) $row->unit_price,
            (float) $row->line_total,
            $this->documentLink('tenant.invoicing.edit', ['invoice' => (int) $row->document_id], $tenantCode)
        ))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchDeliveryNotes(int $clientId, string $sku, ?string $dateFrom, ?string $dateTo, ?string $tenantCode): array
    {
        $query = DB::connection('tenant')->table('delivery_note_lines')
            ->join('delivery_notes', 'delivery_note_lines.delivery_note_id', '=', 'delivery_notes.id')
            ->leftJoin('invoices', 'delivery_notes.invoice_id', '=', 'invoices.id')
            ->leftJoin('quotations', 'delivery_notes.quotation_id', '=', 'quotations.id')
            ->leftJoin('invoice_lines as source_invoice_lines', 'delivery_note_lines.invoice_line_id', '=', 'source_invoice_lines.id')
            ->leftJoin('quotation_lines as source_quotation_lines', 'delivery_note_lines.quotation_line_id', '=', 'source_quotation_lines.id')
            ->where($this->skuMatchColumn('delivery_note_lines.item_sku'), $this->skuMatchOperator(), $this->skuMatchValue($sku))
            ->where(function ($q) use ($clientId) {
                if (Schema::connection('tenant')->hasColumn('delivery_notes', 'client_id')) {
                    $q->where('delivery_notes.client_id', $clientId);
                }
                $q->orWhere('invoices.client_id', $clientId)
                    ->orWhere('quotations.client_id', $clientId);
            })
            ->select([
                'delivery_notes.id as document_id',
                'delivery_notes.delivery_number as document_number',
                'delivery_notes.delivery_date as document_date',
                'delivery_note_lines.item_sku',
                'delivery_note_lines.item_name',
                'delivery_note_lines.quantity',
                DB::raw('COALESCE(source_invoice_lines.unit_price, source_quotation_lines.unit_price, 0) as unit_price'),
            ]);

        $this->applyDateFilter($query, 'delivery_notes.delivery_date', $dateFrom, $dateTo);

        return $query->get()->map(function ($row) use ($tenantCode) {
            $quantity = (float) $row->quantity;
            $unitPrice = (float) $row->unit_price;

            return $this->mapRow(
                'delivery_note',
                'Bon de livraison',
                (int) $row->document_id,
                (string) $row->document_number,
                (string) $row->document_date,
                $row->item_sku,
                (string) $row->item_name,
                $quantity,
                $unitPrice,
                round($quantity * $unitPrice, 2),
                $this->documentLink('tenant.invoicing.deliveries.show', ['deliveryNote' => (int) $row->document_id], $tenantCode)
            );
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSales(int $clientId, string $sku, ?string $dateFrom, ?string $dateTo, ?string $tenantCode): array
    {
        $query = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->where('sales.client_id', $clientId)
            ->where($this->skuMatchColumn('sale_lines.item_sku'), $this->skuMatchOperator(), $this->skuMatchValue($sku))
            ->select([
                'sales.id as document_id',
                'sales.sale_number as document_number',
                'sales.sale_date as document_date',
                'sale_lines.item_sku',
                'sale_lines.item_name',
                'sale_lines.quantity',
                'sale_lines.unit_price',
                'sale_lines.line_total',
            ]);

        $this->applyDateFilter($query, 'sales.sale_date', $dateFrom, $dateTo);

        return $query->get()->map(function ($row) use ($tenantCode) {
            $documentNumber = (string) ($row->document_number ?: ('#' . $row->document_id));

            return $this->mapRow(
                'sale',
                'Vente caisse',
                (int) $row->document_id,
                $documentNumber,
                (string) $row->document_date,
                $row->item_sku,
                (string) $row->item_name,
                (float) $row->quantity,
                (float) $row->unit_price,
                (float) $row->line_total,
                $this->documentLink('tenant.sales.show', [(int) $row->document_id], $tenantCode)
            );
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(
        string $type,
        string $typeLabel,
        int $documentId,
        string $documentNumber,
        string $documentDate,
        ?string $itemSku,
        string $itemName,
        float $quantity,
        float $unitPrice,
        float $lineTotal,
        ?string $link
    ): array {
        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'document_id' => $documentId,
            'document_number' => $documentNumber,
            'document_date' => Carbon::parse($documentDate)->toDateString(),
            'item_sku' => $itemSku,
            'item_name' => $itemName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'link' => $link,
        ];
    }

    private function applyDateFilter($query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->whereDate($column, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate($column, '<=', $dateTo);
        }
    }

    private function skuMatchColumn(string $column): \Illuminate\Database\Query\Expression
    {
        return DB::raw('LOWER(' . $column . ')');
    }

    private function skuMatchOperator(): string
    {
        return '=';
    }

    private function skuMatchValue(string $sku): string
    {
        return strtolower($sku);
    }

    private function documentLink(string $routeName, array $parameters, ?string $tenantCode): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        $params = $parameters;
        if ($tenantCode) {
            $params['tenant'] = $tenantCode;
        }

        return route($routeName, $params);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
