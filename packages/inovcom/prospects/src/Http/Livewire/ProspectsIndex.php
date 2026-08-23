<?php

namespace InovCom\Prospects\Http\Livewire;

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Prospects\Concerns\AuthorizesProspectActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProspectsIndex extends Component
{
    use AuthorizesProspectActions;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'source', except: 'all')]
    public string $sourceFilter = 'all';

    #[Url(as: 'owner', except: 'all')]
    public string $ownerFilter = 'all';

    #[Url(as: 'score', except: 'all')]
    public string $scoreFilter = 'all';

    public string $quickFilter = 'all';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->search = trim((string) request('search', $this->search));
        $status = request('status');
        if (is_string($status) && $status !== '') {
            $this->statusFilter = $status;
        }
        $source = request('source');
        if (is_string($source) && $source !== '') {
            $this->sourceFilter = $source;
        }
        $owner = request('owner');
        if (is_string($owner) && $owner !== '') {
            $this->ownerFilter = $owner;
        }
        $score = request('score');
        if (is_string($score) && $score !== '') {
            $this->scoreFilter = $score;
        }
    }

    public function setQuickFilter(string $filter): void
    {
        $this->quickFilter = $filter;
        if (in_array($filter, ['nouveau', 'a_qualifier', 'qualifie', 'non_qualifie'], true)) {
            $this->statusFilter = $filter;
        } else {
            $this->statusFilter = 'all';
        }
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->sourceFilter = 'all';
        $this->ownerFilter = 'all';
        $this->scoreFilter = 'all';
        $this->quickFilter = 'all';
        $this->resetPage();
    }

    public function delete(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.delete');

        try {
            $prospect = Prospect::findOrFail($prospectId);
            app(ProspectsService::class)->delete($prospect);
            session()->flash('success', 'Prospect supprimé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Envoie le prospect dans le pipeline d’opportunités (Nouveau → Contacté).
     */
    public function toOpportunity(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');

        try {
            $prospect = Prospect::findOrFail($prospectId);
            if ($prospect->isConverted() || $prospect->isLost()) {
                session()->flash('error', 'Ce prospect n’est plus dans le pipeline.');

                return;
            }

            if (in_array($prospect->status, [Prospect::STATUS_NOUVEAU, Prospect::STATUS_CONTACTE], true)) {
                app(ProspectsService::class)->initiateAsProspect(
                    $prospect,
                    Auth::guard('tenant')->id()
                );
            }

            if (Route::has('tenant.crm.opportunities')) {
                session()->flash('success', 'Prospect ouvert dans le pipeline d’opportunités.');
                $this->redirect(route('tenant.crm.opportunities', [
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);

                return;
            }

            session()->flash('success', 'Prospect mis à jour.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorizeProspectAction('prospects.view');

        $ownerId = $this->ownerFilter !== 'all' ? (int) $this->ownerFilter : null;

        $query = Prospect::query()
            ->with(['owner', 'convertedClient', 'nextPlannedActivity', 'lastCompletedActivity'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(reference) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\'))) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(whatsapp, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(need, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(job_title, \'\')) LIKE ?', [$term]);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== 'all', fn ($q) => $q->where('source', $this->sourceFilter))
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->when($this->scoreFilter === 'chaud', fn ($q) => $q->where('score', '>=', 60))
            ->when($this->scoreFilter === 'tiede', fn ($q) => $q->whereBetween('score', [30, 59]))
            ->when($this->scoreFilter === 'froid', fn ($q) => $q->where('score', '<', 30))
            ->when($this->quickFilter === 'chauds', fn ($q) => $q->where('score', '>=', 60))
            ->when($this->quickFilter === 'tiedes', fn ($q) => $q->whereBetween('score', [30, 59]))
            ->when($this->quickFilter === 'froids', fn ($q) => $q->where('score', '<', 30))
            ->when($this->quickFilter === 'sans_activite', function ($q) {
                $q->whereDoesntHave('activities', fn ($a) => $a->where('type', '!=', 'status')->where('created_at', '>=', now()->subDays(14)));
            })
            ->when($this->quickFilter === 'mine', function ($q) {
                $uid = Auth::guard('tenant')->id();
                if ($uid) {
                    $q->where('owner_id', $uid);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $stats = app(ProspectsService::class)->summarize(
            $this->statusFilter === 'all' ? null : $this->statusFilter,
            $this->sourceFilter === 'all' ? null : $this->sourceFilter,
            $ownerId
        );

        $kpi = app(ProspectsService::class)->summarize(
            null,
            $this->sourceFilter === 'all' ? null : $this->sourceFilter,
            $ownerId
        );

        $tenant = request()->attributes->get('tenant');
        $crmEnabled = class_exists(ModuleRegistry::class)
            && $tenant
            && app(ModuleRegistry::class)->isEnabled('crm', $tenant);

        return view('inovcom-prospects::livewire.prospects.index')
            ->layout('layouts.app', [
                'title' => '',
                'subtitle' => '',
                'hidePageHeader' => true,
            ])
            ->with([
                'prospects' => $query->paginate($this->perPage),
                'stats' => $kpi,
                'inactiveCount' => Prospect::query()
                    ->whereDoesntHave('activities', fn ($a) => $a->where('type', '!=', 'status')->where('created_at', '>=', now()->subDays(14)))
                    ->count(),
                'conversionBySource' => $stats['by_source'] ?? [],
                'owners' => User::query()->orderBy('name')->get(['id', 'name']),
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $this->canProspect('prospects.create'),
                'canDelete' => $this->canProspect('prospects.delete'),
                'canUpdate' => $this->canProspect('prospects.update'),
                'crmEnabled' => $crmEnabled,
            ]);
    }
}
