<?php

namespace InovCom\Reporting\Exports;

class ReportingExcelExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|float|int|null>>  $rows
     */
    public static function download(string $filename, array $headers, array $rows, string $title = ''): \Symfony\Component\HttpFoundation\StreamedResponse
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
}
