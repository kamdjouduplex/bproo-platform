<?php

namespace InovCom\Planning\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Planning\Models\Appointment;
use InovCom\Users\Models\User;
use Livewire\Component;

class PlanningCalendar extends Component
{
    use AuthorizesWithTenant;

    public int    $year;
    public int    $month;
    public string $view       = 'list'; // month | list
    public string $filterUser = '';
    public string $filterType = '';

    public function mount(): void
    {
        $this->tenantAuthorize('planning.view');
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function updatedFilterUser(): void {}
    public function updatedFilterType(): void {}

    public function prevMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function nextMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function goToday(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('planning.delete');
        Appointment::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Rendez-vous supprimé.'));
    }

    public function render()
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // All appointments for this month
        $appointments = Appointment::on('tenant')
            ->with(['client', 'assignedUser'])
            ->whereBetween('start_at', [$start, $end->copy()->endOfDay()])
            ->when($this->filterUser !== '', fn($q) => $q->where('assigned_to', $this->filterUser))
            ->when($this->filterType !== '', fn($q) => $q->where('type', $this->filterType))
            ->orderBy('start_at')
            ->get();

        // Group by day key for calendar grid
        $byDay = $appointments->groupBy(fn($a) => Carbon::parse($a->start_at)->format('Y-m-d'));

        // Build calendar weeks (Monday-first grid)
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks  = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key    = $cursor->format('Y-m-d');
                $week[] = [
                    'date'           => $cursor->copy(),
                    'isCurrentMonth' => $cursor->month === $this->month,
                    'isToday'        => $cursor->isToday(),
                    'appointments'   => $byDay->get($key, collect()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // Upcoming list — next 30 days (for list view)
        $upcoming = Appointment::on('tenant')
            ->with(['client', 'assignedUser', 'participants'])
            ->where('start_at', '>=', now()->startOfDay())
            ->where('start_at', '<=', now()->addDays(30))
            ->when($this->filterUser !== '', fn($q) => $q->where('assigned_to', $this->filterUser))
            ->when($this->filterType !== '', fn($q) => $q->where('type', $this->filterType))
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('start_at')
            ->limit(50)
            ->get()
            ->groupBy(fn($a) => Carbon::parse($a->start_at)->format('Y-m-d'));

        $users = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $monthLabel = Carbon::create($this->year, $this->month)->locale('fr')->isoFormat('MMMM YYYY');

        return view('inovcom-planning::livewire.calendar', [
            'weeks'      => $weeks,
            'upcoming'   => $upcoming,
            'users'      => $users,
            'monthLabel' => ucfirst($monthLabel),
            'canCreate'  => $this->tenantCan('planning.create'),
            'canDelete'  => $this->tenantCan('planning.delete'),
        ])->layout('layouts.app', [
            'title'    => __('Planning'),
            'subtitle' => __('Calendrier des rendez-vous, visites terrain et jalons.'),
        ]);
    }
}
