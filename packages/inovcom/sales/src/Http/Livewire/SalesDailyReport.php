<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;
use InovCom\Sales\Models\Sale;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesDailyReport extends Component
{
    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function setToday(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function exportExcel(): StreamedResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $data = $this->reportData();
        $dateLabel = \Illuminate\Support\Carbon::parse($this->date)->format('d/m/Y');
        $filename = 'rapport-ventes-'.$this->date.'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use ($data, $dateLabel, $escape) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape('Rapport ventes du '.$dateLabel).'</h3>';
            echo '<p>'.$escape($data['salesCount'].' vente(s) · '.$data['detailLines']->count().' ligne(s) article').'</p>';

            echo '<h4>Totaux encaissés (par devise)</h4>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            echo '<th>Devise</th><th>Total</th></tr></thead><tbody>';
            if ($data['totalsByCurrency'] === []) {
                echo '<tr><td colspan="2">Aucune vente</td></tr>';
            } else {
                foreach ($data['totalsByCurrency'] as $code => $amount) {
                    echo '<tr>';
                    echo '<td>'.$escape(TenantCurrencyService::label($code).' ('.$code.')').'</td>';
                    echo '<td>'.$escape(fmt_money($amount)).'</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            echo '<h4>1. Liste des ventes</h4>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            echo '<th>N° vente</th><th>Client</th><th>Vendeur</th><th>Devise</th><th>Total</th>';
            echo '</tr></thead><tbody>';
            if ($data['sales']->isEmpty()) {
                echo '<tr><td colspan="5">Aucune vente</td></tr>';
            } else {
                foreach ($data['sales'] as $sale) {
                    $code = strtoupper((string) ($sale->currency_code ?: $data['defaultCurrency']));
                    echo '<tr>';
                    echo '<td>'.$escape($sale->sale_number).'</td>';
                    echo '<td>'.$escape($sale->client?->name ?? 'Client occasionnel').'</td>';
                    echo '<td>'.$escape($sale->creator?->name ?? '—').'</td>';
                    echo '<td>'.$escape(TenantCurrencyService::label($code)).'</td>';
                    echo '<td>'.$escape(fmt_money($sale->total)).'</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            echo '<h4>2. Détail des articles</h4>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            echo '<th>N° vente</th><th>Article</th><th>Réf.</th><th>Qté</th><th>P.U.</th><th>Montant</th><th>Devise</th>';
            echo '</tr></thead><tbody>';
            if ($data['detailLines']->isEmpty()) {
                echo '<tr><td colspan="7">Aucun article</td></tr>';
            } else {
                foreach ($data['detailLines'] as $line) {
                    echo '<tr>';
                    echo '<td>'.$escape($line['sale_number']).'</td>';
                    echo '<td>'.$escape($line['item_name']).'</td>';
                    echo '<td>'.$escape($line['item_sku'] ?: '—').'</td>';
                    echo '<td>'.$escape(fmt_num($line['quantity'])).'</td>';
                    echo '<td>'.$escape(fmt_money($line['unit_price'])).'</td>';
                    echo '<td>'.$escape(fmt_money($line['line_total'])).'</td>';
                    echo '<td>'.$escape($line['currency_label']).'</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $data = $this->reportData();
            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'rapport-ventes-'.$this->date.'.pdf';

            $pdf = Pdf::loadView('inovcom-sales::pdf.daily-report', [
                'date' => $this->date,
                'sales' => $data['sales'],
                'detailLines' => $data['detailLines'],
                'totalsByCurrency' => $data['totalsByCurrency'],
                'defaultCurrency' => $data['defaultCurrency'],
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont('DejaVu Sans');
            if ($font) {
                $size = 8;
                $width = $fontMetrics->getTextWidth('00/00', $font, $size);
                $x = ($canvas->get_width() - $width) / 2;
                $y = $canvas->get_height() - 18;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.06, 0.46, 0.43]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Export PDF impossible. Réessayez.');

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function reportData(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $defaultCurrency = $tenant
            ? app(TenantCurrencyService::class)->defaultCode($tenant)
            : 'XOF';

        $sales = Sale::query()
            ->whereDate('sale_date', $this->date)
            ->with(['lines', 'client', 'creator'])
            ->orderBy('id')
            ->get();

        $totalsByCurrency = [];
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            $totalsByCurrency[$code] = ($totalsByCurrency[$code] ?? 0) + (float) $sale->total;
        }

        $detailLines = collect();
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            foreach ($sale->lines as $line) {
                $detailLines->push([
                    'sale_number' => $sale->sale_number,
                    'item_name' => $line->item_name,
                    'item_sku' => $line->item_sku,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                    'currency_code' => $code,
                    'currency_label' => TenantCurrencyService::label($code),
                ]);
            }
        }

        return [
            'sales' => $sales,
            'detailLines' => $detailLines,
            'totalsByCurrency' => $totalsByCurrency,
            'salesCount' => $sales->count(),
            'defaultCurrency' => $defaultCurrency,
            'hasCurrencyColumn' => Schema::connection('tenant')->hasColumn('sales', 'currency_code'),
        ];
    }

    public function render()
    {
        $data = $this->reportData();

        return view('inovcom-sales::livewire.sales.daily-report', $data)
            ->layout('layouts.app', [
                'title' => 'Rapport ventes du jour',
                'subtitle' => 'Liste des ventes et détail articles',
            ]);
    }
}
