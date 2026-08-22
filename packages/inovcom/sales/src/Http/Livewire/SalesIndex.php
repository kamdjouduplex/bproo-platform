<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SuspendedSale;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 20;

    public function mount(): void
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $now = now();
        switch ($period) {
            case 'day':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
        }
        $this->resetPage();
    }

    public function deleteSuspended(int $id): void
    {
        if (! Schema::connection('tenant')->hasTable('suspended_sales')) {
            return;
        }
        $suspended = SuspendedSale::find($id);
        if ($suspended) {
            $suspended->delete();
            session()->flash('success', 'Vente suspendue supprimée.');
        }
    }

    public function exportExcel(): StreamedResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $headers = [
            'N° Vente',
            'Date',
            'Client',
            'Type',
            'Sous-total',
            'Remise',
            'Total',
            'Devise',
            'Paiement',
            'Statut',
            'Vendeur',
        ];

        $title = 'Ventes — '.$this->filterLabel();
        $filename = 'ventes-'.now()->format('Ymd_His').'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use ($headers, $title, $escape) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            $exported = 0;
            $this->baseQuery()
                ->with(['client', 'creator', 'payments'])
                ->orderBy('sale_date')
                ->orderBy('id')
                ->chunkById(300, function ($chunk) use (&$exported, $escape) {
                    foreach ($chunk as $sale) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $row = $this->mapSaleRow($sale);
                        echo '<tr>';
                        echo '<td>'.$escape($row['sale_number']).'</td>';
                        echo '<td>'.$escape($row['sale_date']).'</td>';
                        echo '<td>'.$escape($row['client_name']).'</td>';
                        echo '<td>'.$escape($row['price_tier_label']).'</td>';
                        echo '<td>'.$escape(fmt_money($row['subtotal'])).'</td>';
                        echo '<td>'.$escape(fmt_money($row['discount_amount'])).'</td>';
                        echo '<td>'.$escape(fmt_money($row['total'])).'</td>';
                        echo '<td>'.$escape($row['currency_label']).'</td>';
                        echo '<td>'.$escape($row['payment_label']).'</td>';
                        echo '<td>'.$escape($row['status_label']).'</td>';
                        echo '<td>'.$escape($row['seller_name']).'</td>';
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'sales.id', 'id');

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
            $sales = $this->baseQuery()
                ->with(['client', 'creator', 'payments'])
                ->orderBy('sale_date')
                ->orderBy('id')
                ->limit(5000)
                ->get();

            $rows = $sales->map(fn (Sale $sale) => $this->mapSaleRow($sale))->all();
            $totalAmount = collect($rows)->sum('total');

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'ventes-'.now()->format('Ymd_His').'.pdf';

            $pdf = Pdf::loadView('inovcom-sales::pdf.sales-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Ventes',
                'filterLabel' => $this->filterLabel(),
                'totalAmount' => $totalAmount,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

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
            session()->flash('error', 'Export PDF impossible. Affinez la période puis réessayez.');

            return null;
        }
    }

    public function render()
    {
        $suspendedSales = collect([]);
        if (Schema::connection('tenant')->hasTable('suspended_sales')) {
            $suspendedSales = SuspendedSale::with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        $sales = $this->baseQuery()
            ->with(['client', 'creator', 'payments'])
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        return view('inovcom-sales::livewire.sales.index')
            ->layout('layouts.app', [
                'title' => 'Ventes',
                'subtitle' => 'Historique des ventes',
            ])
            ->with([
                'sales' => $sales,
                'suspendedSales' => $suspendedSales,
            ]);
    }

    private function baseQuery(): Builder
    {
        return Sale::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(sale_number) LIKE ?', [$term])
                        ->orWhereHas('client', function ($q2) use ($term) {
                            $q2->whereRaw('LOWER(name) LIKE ?', [$term]);
                        });
                });
            }, function ($query) {
                $query->when($this->dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $this->dateTo));
            });
    }

    /**
     * @return array{
     *     sale_number: string,
     *     sale_date: string,
     *     client_name: string,
     *     price_tier_label: string,
     *     subtotal: float,
     *     discount_amount: float,
     *     total: float,
     *     currency_label: string,
     *     payment_label: string,
     *     status_label: string,
     *     seller_name: string
     * }
     */
    private function mapSaleRow(Sale $sale): array
    {
        $paidCash = (float) $sale->payments->where('method', '!=', 'credit')->sum('amount');
        $credit = (float) $sale->payments->where('method', 'credit')->sum('amount');
        $balance = max(0, (float) $sale->total - $paidCash);

        if ($balance <= 0.01 && $credit <= 0.01) {
            $paymentLabel = 'Payé';
        } elseif ($credit > 0.01 && $paidCash > 0.01) {
            $paymentLabel = 'Partiel / Crédit';
        } elseif ($credit > 0.01) {
            $paymentLabel = 'À crédit';
        } else {
            $paymentLabel = 'Impayé';
        }

        $statusLabel = $sale->payments->isEmpty()
            ? 'Non payé'
            : $sale->payments
                ->map(fn ($payment) => trim($payment->method_label.' '.fmt_money($payment->amount)))
                ->implode(' · ');

        $tier = match ($sale->price_tier ?? 'retail') {
            'semi_wholesale' => 'Semi-gros',
            'wholesale' => 'Gros',
            default => 'Détail',
        };

        return [
            'sale_number' => (string) $sale->sale_number,
            'sale_date' => $sale->sale_date?->format('d/m/Y') ?? '—',
            'client_name' => (string) ($sale->client?->name ?? 'Client occasionnel'),
            'price_tier_label' => $tier,
            'subtotal' => (float) $sale->subtotal,
            'discount_amount' => (float) $sale->discount_amount,
            'total' => (float) $sale->total,
            'currency_label' => TenantCurrencyService::displayLabel($sale->currency_code),
            'payment_label' => $paymentLabel,
            'status_label' => $statusLabel,
            'seller_name' => (string) ($sale->creator?->name ?? '—'),
        ];
    }

    private function filterLabel(): string
    {
        if ($this->search !== '') {
            return 'Recherche : '.$this->search;
        }

        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            return 'Période : '.($this->dateFrom ?: '…').' → '.($this->dateTo ?: '…');
        }

        return 'Toutes les ventes';
    }
}
