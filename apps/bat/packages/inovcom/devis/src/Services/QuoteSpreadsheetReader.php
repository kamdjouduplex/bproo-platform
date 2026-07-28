<?php

namespace InovCom\Devis\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads spreadsheet files into a normalized 2D array of scalar values.
 */
class QuoteSpreadsheetReader
{
    private const MAX_ROWS = 2000;

  /**
     * @return array<int, array<int, mixed>>
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === 'csv') {
            return $this->readCsv($path);
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
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $line = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = $cell->getValue();

                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $line[] = ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
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
            throw new \RuntimeException(__('Impossible de lire le fichier CSV.'));
        }

        $sample = fread($handle, 4096) ?: '';
        rewind($handle);
        $delimiter = $this->detectCsvDelimiter($sample);

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
            return trim($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function trimEmptyTrailingRows(array $rows): array
    {
        while (!empty($rows)) {
            $last = end($rows);
            if ($this->rowIsEmpty($last)) {
                array_pop($rows);
                continue;
            }
            break;
        }

        return array_values($rows);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    public function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== '' && $cell !== null) {
                return false;
            }
        }

        return true;
    }
}
