<?php

namespace InovCom\Debts\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use InovCom\Debts\Models\Debt;
use InovCom\Debts\Services\DebtsService;
use InovCom\Clients\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DebtsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $clientFilter = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 20;

    public string $validationFilter = 'all';

    public function mount(): void
    {
        $client = request()->query('client');
        if ($client !== null && $client !== '') {
            $this->clientFilter = (int) $client;
        }

        if (request()->query('validation') === 'pending') {
            $this->validationFilter = 'pending';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->clientFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function delete(int $debtId): void
    {
        if (! $this->can('debts.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $debt = Debt::findOrFail($debtId);

        if ((float) $debt->balance > 0) {
            session()->flash('error', 'Seules les dettes soldées peuvent être supprimées.');

            return;
        }

        $debt->delete();
        $this->resetPage();
    }

    public function validateDebt(int $debtId): void
    {
        if (! $this->can('debts.validate')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        if (! Debt::supportsValidationWorkflow()) {
            session()->flash('error', 'Validation indisponible: exécutez les migrations tenant du module Dettes.');

            return;
        }

        $debt = Debt::findOrFail($debtId);
        if ((bool) $debt->is_validated) {
            session()->flash('success', 'Cette dette est déjà validée.');

            return;
        }

        $debt->is_validated = true;
        $debt->validated_by = auth('tenant')->id();
        $debt->validated_at = now();
        $debt->save();

        session()->flash('success', 'Dette validée avec succès.');
    }

    public function exportPdf()
    {
        if (! $this->can('debts.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $debts = $this->baseQuery()
                ->with(['client'])
                ->orderByDesc('opened_at')
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get();

            $statusLabels = [
                'open' => 'Ouvert',
                'partial' => 'Partiel',
                'paid' => 'Soldé',
                'overdue' => 'En retard',
            ];

            $rows = $debts->map(function (Debt $debt) use ($statusLabels) {
                $statusLabel = $statusLabels[$debt->status] ?? (string) $debt->status;
                if (Debt::supportsValidationWorkflow() && ! $debt->is_validated) {
                    $statusLabel = 'En attente de validation';
                }

                return [
                    'reference' => (string) $debt->reference,
                    'client_name' => (string) ($debt->client?->name ?? '—'),
                    'client_code' => (string) ($debt->client?->code ?? ''),
                    'opened_at' => $debt->opened_at?->format('d/m/Y') ?? '—',
                    'due_date' => $debt->due_date?->format('d/m/Y') ?? '—',
                    'total_amount' => (float) $debt->total_amount,
                    'balance' => (float) $debt->balance,
                    'status' => (string) $debt->status,
                    'status_label' => $statusLabel,
                ];
            })->all();

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'dettes-' . now()->format('Ymd_His') . '.pdf';

            $pdf = Pdf::loadView('inovcom-debts::pdf.debts-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Dettes clients',
                'filterLabel' => $this->filterLabel(),
                'totalBalance' => collect($rows)->sum('balance'),
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
            session()->flash('error', 'Export PDF impossible. Affinez les filtres puis réessayez.');

            return null;
        }
    }

    public function render()
    {
        $debts = $this->baseQuery()
            ->with(['client', 'creator', 'validator'])
            ->orderByDesc('opened_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $clients = Client::where('is_active', true)->orderBy('name')->get();

        $service = app(DebtsService::class);
        $totalOutstanding = $service->getTotalOutstanding(
            $this->clientFilter ?: null
        );

        return view('inovcom-debts::livewire.debts.index')
            ->layout('layouts.app', [
                'title' => 'Dettes',
                'subtitle' => 'Gestion des dettes clients',
            ])
            ->with([
                'debts' => $debts,
                'clients' => $clients,
                'totalOutstanding' => $totalOutstanding,
                'validationWorkflowReady' => Debt::supportsValidationWorkflow(),
                'canValidate' => $this->can('debts.validate'),
                'canDelete' => $this->can('debts.delete'),
                'canReceivePayment' => $this->can('debts.receive_payment'),
                'canCreate' => $this->can('debts.create'),
                'canExport' => $this->can('debts.view'),
            ]);
    }

    private function baseQuery(): Builder
    {
        return Debt::query()
            ->when(
                Debt::supportsValidationWorkflow() && $this->validationFilter === 'pending',
                fn ($q) => $q->where('is_validated', false)->whereIn('status', ['open', 'partial'])
            )
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('code', 'like', '%' . $this->search . '%'));
                });
            }, function ($q) {
                $q->when($this->statusFilter !== 'all', fn ($q2) => $q2->where('status', $this->statusFilter))
                    ->when($this->clientFilter, fn ($q2) => $q2->where('client_id', $this->clientFilter))
                    ->when($this->dateFrom, fn ($q2) => $q2->where('opened_at', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q2) => $q2->where('opened_at', '<=', $this->dateTo));
            });
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter !== 'all') {
            $parts[] = 'Statut : '.$this->statusFilter;
        }
        if ($this->clientFilter) {
            $client = Client::find($this->clientFilter);
            $parts[] = 'Client : '.($client?->name ?? '#'.$this->clientFilter);
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $parts[] = 'Période : '.($this->dateFrom ?: '…').' → '.($this->dateTo ?: '…');
        }
        if ($this->validationFilter === 'pending') {
            $parts[] = 'Validation en attente';
        }

        return $parts === [] ? 'Tous les enregistrements' : implode(' · ', $parts);
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
