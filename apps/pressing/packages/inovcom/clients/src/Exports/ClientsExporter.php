<?php

namespace InovCom\Clients\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|float|int|null>>  $rows
     */
    public static function download(string $filename, array $headers, array $rows, string $title = ''): StreamedResponse
    {
        $html = self::buildHtmlTable($title, $headers, $rows);

        return response()->streamDownload(function () use ($html) {
            echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
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
     * @param  Collection<int, \InovCom\Clients\Models\Client>  $clients
     * @param  array<int, array<string, mixed>>  $debtSummaries
     * @return list<list<string>>
     */
    public static function rows(Collection $clients, array $debtSummaries = []): array
    {
        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                $client->code,
                $client->name,
                $client->type === 'company' ? 'Entreprise' : 'Particulier',
                $client->phone ?? '',
                $client->email ?? '',
                $client->niu ?? '',
                $client->rccm ?? '',
                optional($client->segment)->name ?? '',
                optional($client->category)->name ?? '',
                optional($client->zone)->name ?? '',
                $client->priceTierLabel(),
                rtrim(rtrim(number_format((float) $client->discount_rate, 2), '0'), '.') . ' %',
                fmt_money((float) $client->credit_limit),
                fmt_money((float) ($debtSummaries[$client->id]['outstanding'] ?? 0)),
                $client->is_blocked ? 'Bloqué' : ($client->is_active ? 'Actif' : 'Inactif'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Code', 'Nom', 'Type', 'Téléphone', 'Email', 'NIU', 'RCCM',
            'Segment', 'Catégorie', 'Zone', 'Palier', 'Remise',
            'Limite crédit', 'Encours', 'Statut',
        ];
    }
}
