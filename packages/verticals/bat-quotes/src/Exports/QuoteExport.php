<?php

namespace InovCom\Devis\Exports;

use InovCom\Devis\Models\Quote;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuoteExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @var list<int> Rows that are section/lot headers (1-based). */
    private array $sectionRows = [];

    private int $tableHeaderRow = 0;
    private int $totalsStartRow = 0;

    public function __construct(
        private readonly Quote $quote,
    ) {
        $this->quote->loadMissing(['lines', 'client']);
    }

    public function title(): string
    {
        return $this->quote->code;
    }

    public function array(): array
    {
        $rows = [];
        $client = $this->quote->client;

        $rows[] = [__('DEVIS'), $this->quote->code];
        $rows[] = [__('Client'), $client?->name ?? ''];
        $rows[] = [__('Objet'), $this->quote->title];
        $rows[] = [__('Statut'), $this->statusLabel()];
        if ($this->quote->valid_until) {
            $rows[] = [__('Valable jusqu\'au'), $this->quote->valid_until->format('d/m/Y')];
        }
        $rows[] = [];

        $this->tableHeaderRow = count($rows) + 1;
        $rows[] = [
            __('Désignation'),
            __('Type'),
            __('Qté'),
            __('Unité'),
            __('P.U. HT'),
            __('Remise %'),
            __('Coût'),
            __('Montant HT'),
        ];

        foreach ($this->quote->lines as $line) {
            $isSection = ($line->line_type ?? '') === 'section';
            $rowNum = count($rows) + 1;

            if ($isSection) {
                $this->sectionRows[] = $rowNum;
            }

            $rows[] = [
                $line->description,
                $isSection ? '' : $this->lineTypeLabel($line->line_type ?? 'service'),
                $isSection ? '' : (float) $line->quantity,
                $isSection ? '' : ($line->unit ?? ''),
                $isSection ? '' : (float) $line->unit_price,
                $isSection ? '' : (float) ($line->discount_percent ?? 0),
                $isSection ? '' : (float) ($line->cost ?? 0),
                $isSection ? '' : (float) $line->amount,
            ];
        }

        $rows[] = [];
        $this->totalsStartRow = count($rows) + 1;

        $rows[] = ['', '', '', '', '', '', __('Total HT'), (float) $this->quote->total_ht];
        if ((float) ($this->quote->discount_percent ?? 0) > 0) {
            $rows[] = ['', '', '', '', '', '', __('Remise') . ' (' . $this->quote->discount_percent . '%)', -(float) $this->quote->discount_amount];
            $rows[] = ['', '', '', '', '', '', __('Net HT'), (float) $this->quote->net_ht];
        }
        if ((float) ($this->quote->tax_rate ?? 0) > 0) {
            $rows[] = ['', '', '', '', '', '', __('TVA') . ' (' . $this->quote->tax_rate . '%)', (float) $this->quote->tax_amount];
        }
        $rows[] = ['', '', '', '', '', '', __('TOTAL TTC'), (float) $this->quote->total_ttc];

        if ($this->quote->notes) {
            $rows[] = [];
            $rows[] = [__('Notes'), $this->quote->notes];
        }

        if ($this->quote->terms) {
            $rows[] = [];
            $rows[] = [__('Conditions'), $this->quote->terms];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        $styles[1] = ['font' => ['bold' => true, 'size' => 14]];
        $styles[2] = ['font' => ['bold' => true]];

        if ($this->tableHeaderRow > 0) {
            $styles[$this->tableHeaderRow] = [
                'font' => ['bold' => true, 'color' => ['rgb' => '334155']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '94A3B8']],
                ],
            ];
        }

        foreach ($this->sectionRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'color' => ['rgb' => '3730A3']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EEF2FF'],
                ],
            ];
            $sheet->mergeCells("A{$row}:H{$row}");
            $styles[$row]['alignment'] = ['horizontal' => Alignment::HORIZONTAL_LEFT];
        }

        if ($this->totalsStartRow > 0) {
            for ($r = $this->totalsStartRow; $r <= $this->totalsStartRow + 4; $r++) {
                $styles[$r] = ['font' => ['bold' => true]];
            }
            $lastTotal = $this->totalsStartRow + (($this->quote->discount_percent ?? 0) > 0 ? 2 : 0) + (($this->quote->tax_rate ?? 0) > 0 ? 1 : 0);
            $styles[$lastTotal] = [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
            ];
        }

        return $styles;
    }

    private function statusLabel(): string
    {
        return match ($this->quote->status) {
            'sent'     => __('Envoyé'),
            'accepted' => __('Accepté'),
            'refused'  => __('Refusé'),
            default    => __('Brouillon'),
        };
    }

    private function lineTypeLabel(string $type): string
    {
        return match ($type) {
            'product'  => __('Produit'),
            'work'     => __('Travaux'),
            'subtotal' => __('Sous-total'),
            'section'  => __('Titre / Lot'),
            default    => __('Service'),
        };
    }
}
