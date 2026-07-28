<?php

namespace InovCom\Clients\Exports;

use InovCom\Clients\Models\Client;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientProductHistoryExporter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function download(string $filename, Client $client, string $sku, array $rows, ?string $dateFrom, ?string $dateTo): StreamedResponse
    {
        $period = self::periodLabel($dateFrom, $dateTo);
        $title = sprintf(
            'Historique produit — %s (%s) — Réf. %s%s',
            $client->name,
            $client->code,
            $sku,
            $period !== '' ? ' — ' . $period : ''
        );

        return ClientsExporter::download(
            $filename,
            self::headers(),
            self::rows($rows),
            $title
        );
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Type',
            'N° document',
            'Date',
            'Réf. produit',
            'Désignation',
            'Quantité',
            'P.U.',
            'Montant ligne',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<list<string>>
     */
    public static function rows(array $rows): array
    {
        $exportRows = [];

        foreach ($rows as $row) {
            $exportRows[] = [
                $row['type_label'] ?? $row['type'] ?? '',
                $row['document_number'] ?? '',
                isset($row['document_date'])
                    ? \Carbon\Carbon::parse($row['document_date'])->format('d/m/Y')
                    : '',
                $row['item_sku'] ?? '',
                $row['item_name'] ?? '',
                fmt_num((float) ($row['quantity'] ?? 0)),
                fmt_money((float) ($row['unit_price'] ?? 0)),
                fmt_money((float) ($row['line_total'] ?? 0)),
            ];
        }

        return $exportRows;
    }

    private static function periodLabel(?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom && $dateTo) {
            return 'Du ' . \Carbon\Carbon::parse($dateFrom)->format('d/m/Y')
                . ' au ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
        }

        if ($dateFrom) {
            return 'À partir du ' . \Carbon\Carbon::parse($dateFrom)->format('d/m/Y');
        }

        if ($dateTo) {
            return 'Jusqu\'au ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
        }

        return '';
    }
}
