<?php

namespace App\Livewire\Admin;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use Livewire\Component;
use Livewire\WithPagination;

class ActivitiesIndex extends Component
{
    use WithPagination;

    public bool $embedded = false;

    public string $search = '';
    public string $type = '';
    public string $from = '';
    public string $to = '';

    protected $paginationTheme = 'cc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public ?int $log_prospect_id = null;
    public string $log_type = 'note';
    public string $log_subject = '';
    public string $log_body = '';
    public string $log_follow_up_at = '';
    public bool $showLog = false;

    public function openLog(?int $prospectId = null): void
    {
        $this->showLog = true;
        $this->log_prospect_id = $prospectId;
        $this->log_type = 'note';
        $this->log_subject = '';
        $this->log_body = '';
        $this->log_follow_up_at = '';
    }

    public function saveLog(): void
    {
        $this->validate([
            'log_prospect_id' => 'required|exists:platform_prospects,id',
            'log_type' => 'required|string|in:note,call,email,meeting,follow_up',
            'log_subject' => 'nullable|string|max:255',
            'log_body' => 'required|string|max:5000',
            'log_follow_up_at' => 'nullable|date',
        ]);

        PlatformProspectActivity::create([
            'platform_prospect_id' => $this->log_prospect_id,
            'user_id' => auth()->id(),
            'type' => $this->log_type,
            'subject' => $this->log_subject !== '' ? $this->log_subject : (PlatformProspectActivity::types()[$this->log_type] ?? 'Activité'),
            'body' => $this->log_body,
        ]);

        $prospect = PlatformProspect::find($this->log_prospect_id);
        if ($prospect) {
            $updates = [];
            if ($this->log_follow_up_at !== '') {
                $updates['next_follow_up_at'] = $this->log_follow_up_at;
            } elseif ($this->log_type === 'follow_up' && ! $prospect->next_follow_up_at) {
                $updates['next_follow_up_at'] = now()->addDay()->toDateString();
            }
            if ($updates !== []) {
                $prospect->update($updates);
            } else {
                $prospect->touch();
            }
        }

        notify()->success('Enregistré.');
        $this->showLog = false;
        $this->log_body = '';
        $this->log_subject = '';
        $this->log_follow_up_at = '';
    }

    public function render()
    {
        $query = PlatformProspectActivity::query()
            ->with(['prospect.convertedTenant', 'user'])
            ->orderByDesc('created_at');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(subject, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(body, \'\')) LIKE ?', [$term])
                    ->orWhereHas('prospect', function ($pq) use ($term) {
                        $pq->whereRaw('LOWER(company_name) LIKE ?', [$term]);
                    });
            });
        }
        if ($this->type !== '') {
            $query->where('type', $this->type);
        }
        if ($this->from !== '') {
            $query->whereDate('created_at', '>=', $this->from);
        }
        if ($this->to !== '') {
            $query->whereDate('created_at', '<=', $this->to);
        }

        $activities = $query->paginate(25);

        $openProspects = PlatformProspect::query()
            ->whereNull('converted_tenant_id')
            ->whereNotIn('stage', [PlatformProspect::STAGE_LOST])
            ->orderBy('company_name')
            ->limit(200)
            ->get(['id', 'company_name', 'stage']);

        $followUpsDue = PlatformProspect::query()
            ->whereNull('converted_tenant_id')
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '<=', now()->toDateString())
            ->whereNotIn('stage', [PlatformProspect::STAGE_LOST])
            ->count();

        $view = view('livewire.admin.activities-index', [
            'activities' => $activities,
            'openProspects' => $openProspects,
            'typeLabels' => PlatformProspectActivity::types(),
            'kpis' => [
                'week' => PlatformProspectActivity::where('created_at', '>=', now()->startOfWeek())->count(),
                'follow_ups' => $followUpsDue,
            ],
            'embedded' => $this->embedded,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'Activités',
            'subtitle' => 'Appels, notes, suivis',
        ]);
    }
}
