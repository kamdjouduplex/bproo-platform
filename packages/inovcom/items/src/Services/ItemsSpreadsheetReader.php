<?php

namespace InovCom\Items\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads Excel/CSV into a normalized 2D array for catalogue import.
 */
class ItemsSpreadsheetReader
{
    private const MAX_ROWS = 5000;

    /**
     * @return array<int, array<int, mixed>>
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsv($path);
        }

        if (! class_exists(IOFactory::class)) {
            throw new \RuntimeException(
                'Lecture Excel indisponible. Installez phpoffice/phpspreadsheet ou utilisez un fichier CSV.'
            );
        }

        return $this->readExcel($path);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = min((int) $sheet->getHighestRow(), self::MAX_ROWS);
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $line = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = $cell->getValue();

                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $line[] = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    continue;
                }

                $line[] = $this->normalizeCell($value);
            }
            $rows[] = $line;
        }

        return $this->trimEmptyTrailingRows($rows);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de lire le fichier CSV.');
        }

        $sample = fread($handle, 4096) ?: '';
        rewind($handle);
        $delimiter = $this->detectCsvDelimiter($sample);

        // Strip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        $rowCount = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($v) => $this->normalizeCell($v), $data);
            $rowCount++;
            if ($rowCount >= self::MAX_ROWS) {
                break;
            }
        }
        fclose($handle);

        return $this->trimEmptyTrailingRows($rows);
    }

    private function detectCsvDelimiter(string $sample): string
    {
        $candidates = [';', ',', "\t", '|'];
        $best = ';';
        $bestScore = -1;

        foreach ($candidates as $delimiter) {
            $score = substr_count($sample, $delimiter);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $delimiter;
            }
        }

        return $best;
    }

    private function normalizeCell(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            $value = trim($value);
            // Numbers with spaces (e.g. "3 500")
            if (preg_match('/^-?[\d\s]+([.,]\d+)?$/', $value)) {
                $normalized = str_replace([' ', ','], ['', '.'], $value);
                if (is_numeric($normalized)) {
                    return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
                }
            }

            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function trimEmptyTrailingRows(array $rows): array
    {
        while ($rows !== []) {
            $last = end($rows);
            $empty = true;
            foreach ($last as $cell) {
                if ($cell !== '' && $cell !== null) {
                    $empty = false;
                    break;
                }
            }
            if (! $empty) {
                break;
            }
            array_pop($rows);
        }

        return $rows;
    }
}
