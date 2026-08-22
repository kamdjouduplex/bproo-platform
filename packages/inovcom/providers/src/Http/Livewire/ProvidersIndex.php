<?php

namespace InovCom\Providers\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InovCom\Providers\Models\Provider;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProvidersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function delete(int $providerId): void
    {
        if (! $this->can('providers.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        Provider::whereKey($providerId)->delete();
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->can('providers.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $headers = [
            'Code',
            'Nom',
            'Téléphone',
            'Email',
            'Adresse',
            'Ville',
            'Pays',
            'Type',
            'Devise',
            'Paiement',
            'Statut',
        ];
        $title = 'Fournisseurs — '.$this->filterLabel();
        $filename = 'fournisseurs-'.now()->format('Ymd_His').'.xls';
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
                ->orderBy('id')
                ->chunkById(300, function ($chunk) use (&$exported, $escape) {
                    foreach ($chunk as $provider) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $row = $this->mapProviderRow($provider);
                        echo '<tr>';
                        echo '<td>'.$escape($row['code']).'</td>';
                        echo '<td>'.$escape($row['name']).'</td>';
                        echo '<td>'.$escape($row['phone']).'</td>';
                        echo '<td>'.$escape($row['email']).'</td>';
                        echo '<td>'.$escape($row['address']).'</td>';
                        echo '<td>'.$escape($row['city']).'</td>';
                        echo '<td>'.$escape($row['country']).'</td>';
                        echo '<td>'.$escape($row['type']).'</td>';
                        echo '<td>'.$escape($row['currency']).'</td>';
                        echo '<td>'.$escape($row['payment_method']).'</td>';
                        echo '<td>'.$escape($row['status']).'</td>';
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'providers.id', 'id');

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        if (! $this->can('providers.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $providers = $this->baseQuery()
                ->orderBy('name')
                ->limit(5000)
                ->get();

            $rows = $providers->map(fn (Provider $provider) => $this->mapProviderRow($provider))->all();

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'fournisseurs-'.now()->format('Ymd_His').'.pdf';

            $pdf = Pdf::loadView('inovcom-providers::pdf.providers-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Fournisseurs',
                'filterLabel' => $this->filterLabel(),
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
            session()->flash('error', 'Export PDF impossible. Affinez la recherche puis réessayez.');

            return null;
        }
    }

    public function render()
    {
        $providers = $this->baseQuery()
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-providers::livewire.providers.index')
            ->layout('layouts.app', [
                'title' => 'Fournisseurs',
                'subtitle' => 'Gestion des fournisseurs',
            ])
            ->with([
                'providers' => $providers,
                'canExport' => $this->can('providers.view'),
                'canCreate' => $this->can('providers.create'),
                'canDelete' => $this->can('providers.delete'),
            ]);
    }

    private function baseQuery(): Builder
    {
        return Provider::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });
    }

    private function mapProviderRow(Provider $provider): array
    {
        $type = $provider->is_foreign ? 'Étranger' : 'Local';
        if ($provider->is_foreign && $provider->default_currency) {
            $type .= ' ('.$provider->default_currency.')';
        }

        return [
            'code' => (string) ($provider->code ?: '—'),
            'name' => (string) ($provider->name ?: '—'),
            'phone' => (string) ($provider->phone ?: '—'),
            'email' => (string) ($provider->email ?: '—'),
            'address' => (string) ($provider->address ?: ''),
            'city' => (string) ($provider->city ?: '—'),
            'country' => (string) ($provider->country ?: '—'),
            'type' => $type,
            'currency' => (string) ($provider->default_currency ?: '—'),
            'payment_method' => Provider::paymentMethodLabel($provider->payment_method),
            'status' => $provider->is_active ? 'Actif' : 'Inactif',
        ];
    }

    private function filterLabel(): string
    {
        if ($this->search !== '') {
            return 'Recherche : '.$this->search;
        }

        return 'Tous les fournisseurs';
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
