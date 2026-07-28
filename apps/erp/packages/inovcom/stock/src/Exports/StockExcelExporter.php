<?php

namespace InovCom\Stock\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockExcelExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|float|int|null>>  $rows
     */
    public static function download(string $filename, array $headers, array $rows, string $title = ''): StreamedResponse
    {
        $html = self::buildHtmlTable($title, $headers, $rows);

        return response()->streamDownload(function () use ($html) {
            echo "\xEF\xBB\xBF";
            echo $html;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|float|int|null>>  $rows
     */
    public static function buildHtmlTable(string $title, array $headers, array $rows): string
    {
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        $html = '<html><head><meta charset="UTF-8"></head><body>';
        if ($title !== '') {
            $html .= '<h3>' . $escape($title) . '</h3>';
        }
        $html .= '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $escape($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $escape($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        return $html;
    }

    /**
     * @param  Collection<int, object>  $items
     * @return list<list<string>>
     */
    public static function stockRows(Collection $items, bool $withLocations = false): array
    {
        $rows = [];
        foreach ($items as $item) {
            $available = (float) ($item->available_quantity ?? 0);
            $total = (float) ($item->quantity ?? 0);
            $reorder = $item->reorder_point;
            $status = $available <= 0
                ? 'Rupture'
                : ($reorder !== null && $available <= (float) $reorder ? 'Stock faible' : 'En stock');

            $row = [
                $item->sku ?? '',
                $item->name ?? '',
                fmt_num($available),
                fmt_num($total),
                $reorder !== null ? fmt_num((float) $reorder) : '',
                $status,
            ];

            if ($withLocations) {
                $row[] = $item->location_code ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $movements
     * @return list<list<string>>
     */
    public static function movementRows(Collection $movements, bool $showItem = true): array
    {
        $rows = [];
        foreach ($movements as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $in = $qty >= 0;
            $line = [
                optional($row['created_at'])->format('d/m/Y H:i') ?? '',
            ];

            if ($showItem) {
                $line[] = $row['item_sku'] ?? '';
                $line[] = $row['item_name'] ?? '';
            }

            $line = array_merge($line, [
                $row['direction_label'] ?? (((float) ($row['quantity'] ?? 0) >= 0) ? 'Entrée' : 'Sortie'),
                $row['type_label'] ?? '',
                (((float) ($row['quantity'] ?? 0) >= 0) ? '+' : '−') . fmt_num(abs((float) ($row['quantity'] ?? 0))),
                fmt_num((float) ($row['quantity_before'] ?? 0)),
                fmt_num((float) ($row['quantity_after'] ?? 0)),
                $row['story'] ?? '',
                $row['reference_label'] ?? '',
                $row['user_name'] ?? '',
                $row['reason'] ?? '',
            ]);

            $rows[] = $line;
        }

        return $rows;
    }
}
