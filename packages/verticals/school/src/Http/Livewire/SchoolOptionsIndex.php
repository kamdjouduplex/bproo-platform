<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolOption;
use School\Support\SchoolOptionCatalog;
use School\Support\SchoolSettings;
use School\Support\SchoolTimetable;

class SchoolOptionsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public const TAB_HOURS = 'hours';

    public string $activeGroup = SchoolOptionCatalog::GROUP_SECTION;

    public string $search = '';

    public string $filterActive = '';

    public string $value = '';

    public string $label = '';

    public int $sortOrder = 100;

    public bool $isActive = true;

    public string $periodStart = '07:30';

    public string $periodEnd = '08:20';

    public string $dayStart = '07:30';

    public string $dayEnd = '15:40';

    public string $lessonMinutes = '50';

    public string $break1Minutes = '20';

    public string $break2Minutes = '45';

    public string $break1After = '3';

    public string $break2After = '5';

    public function mount(): void
    {
        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
        }

        $group = (string) request()->query('group', '');
        if ($group === self::TAB_HOURS || array_key_exists($group, SchoolOptionCatalog::groups())) {
            $this->activeGroup = $group;
        }
        if ($this->isHoursTab()) {
            $this->loadHours();
        }
    }

    public function setGroup(string $group): void
    {
        if ($group !== self::TAB_HOURS && ! array_key_exists($group, SchoolOptionCatalog::groups())) {
            return;
        }

        $this->activeGroup = $group;
        $this->resetPage();
        $this->cancel();
        if ($this->isHoursTab()) {
            $this->loadHours();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterActive(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->openCreateForm();
        if ($this->isPeriodGroup()) {
            $this->suggestNextPeriod();
        }
    }

    public function edit(int $id): void
    {
        $option = SchoolOption::query()->where('group_key', $this->activeGroup)->findOrFail($id);
        $this->value = $option->value;
        $this->label = $option->label;
        $this->sortOrder = (int) $option->sort_order;
        $this->isActive = (bool) $option->is_active;
        $parsed = SchoolTimetable::parsePeriodValue($option->value);
        $this->periodStart = $parsed['start'] ?? '07:30';
        $this->periodEnd = $parsed['end'] ?? '08:20';
        $this->openEditForm($id);
    }

    public function delete(int $id): void
    {
        SchoolOption::query()->where('group_key', $this->activeGroup)->where('id', $id)->delete();
        notify()->success($this->isPeriodGroup() ? 'Tranche retirée.' : 'Option retirée.');
    }

    protected function resetFormFields(): void
    {
        $this->value = '';
        $this->label = '';
        $this->sortOrder = 100;
        $this->isActive = true;
        $this->periodStart = '07:30';
        $this->periodEnd = '08:20';
    }

    public function save(): void
    {
        if ($this->isPeriodGroup()) {
            $this->savePeriod();

            return;
        }

        $this->validate([
            'activeGroup' => ['required', Rule::in(array_keys(SchoolOptionCatalog::groups()))],
            'value' => [
                'required',
                'string',
                'max:120',
                Rule::unique(SchoolOption::class, 'value')
                    ->where('group_key', $this->activeGroup)
                    ->ignore($this->editingId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'group_key' => $this->activeGroup,
            'value' => trim($this->value),
            'label' => trim($this->label),
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            SchoolOption::query()->findOrFail($this->editingId)->update($payload);
            notify()->success('Option mise à jour.');
        } else {
            SchoolOption::query()->create($payload);
            notify()->success('Option ajoutée.');
        }

        $this->cancel();
    }

    protected function savePeriod(): void
    {
        $this->periodStart = SchoolTimetable::formatTime($this->periodStart);
        $this->periodEnd = SchoolTimetable::formatTime($this->periodEnd);

        $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'periodStart' => ['required', 'date_format:H:i'],
            'periodEnd' => ['required', 'date_format:H:i', 'after:periodStart'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'isActive' => ['boolean'],
        ]);

        $value = SchoolTimetable::periodValue($this->periodStart, $this->periodEnd);
        $query = SchoolOption::query()
            ->where('group_key', SchoolOptionCatalog::GROUP_COURSE_PERIOD)
            ->where('value', $value);
        if ($this->editingId) {
            $query->where('id', '!=', $this->editingId);
        }
        if ($query->exists()) {
            notify()->error('Cette tranche horaire existe déjà.');

            return;
        }

        foreach (SchoolOption::forGroup(SchoolOptionCatalog::GROUP_COURSE_PERIOD, false) as $row) {
            if ($this->editingId && (int) $row->id === (int) $this->editingId) {
                continue;
            }
            $parsed = SchoolTimetable::parsePeriodValue((string) $row->value);
            if (! $parsed) {
                continue;
            }
            if (SchoolTimetable::timesOverlap($this->periodStart, $this->periodEnd, $parsed['start'], $parsed['end'])) {
                notify()->error('Cette tranche chevauche « '.$row->label.' » ('.$parsed['start'].'–'.$parsed['end'].').');

                return;
            }
        }

        $payload = [
            'group_key' => SchoolOptionCatalog::GROUP_COURSE_PERIOD,
            'value' => $value,
            'label' => trim($this->label),
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            SchoolOption::query()->findOrFail($this->editingId)->update($payload);
            notify()->success('Tranche mise à jour. L’emploi du temps utilise maintenant ces heures.');
        } else {
            SchoolOption::query()->create($payload);
            notify()->success('Tranche ajoutée. Elle apparaît dans la grille de l’emploi du temps.');
        }

        $this->cancel();
    }

    public function isPeriodGroup(): bool
    {
        return $this->activeGroup === SchoolOptionCatalog::GROUP_COURSE_PERIOD;
    }

    public function isHoursTab(): bool
    {
        return $this->activeGroup === self::TAB_HOURS;
    }

    public function loadHours(): void
    {
        $s = SchoolTimetable::daySchedule();
        $this->dayStart = $s['start'];
        $this->dayEnd = $s['end'];
        $this->lessonMinutes = (string) $s['lesson'];
        $this->break1Minutes = (string) $s['break1'];
        $this->break2Minutes = (string) $s['break2'];
        $this->break1After = (string) $s['break1After'];
        $this->break2After = (string) $s['break2After'];
    }

    public function saveHours(): void
    {
        if ($this->persistHours()) {
            notify()->success('Horaires enregistrés. Ils apparaissent dans l’emploi du temps.');
        }
    }

    public function generatePeriodsFromHours(): void
    {
        if (! $this->persistHours()) {
            return;
        }

        $periods = SchoolTimetable::generatePeriodsFromSchedule();
        if ($periods === []) {
            notify()->error('Impossible de générer les tranches : vérifiez le début, la fin et la durée d’une séance.');

            return;
        }

        SchoolOption::query()->where('group_key', SchoolOptionCatalog::GROUP_COURSE_PERIOD)->delete();
        $order = 10;
        foreach ($periods as $i => $period) {
            SchoolOption::query()->create([
                'group_key' => SchoolOptionCatalog::GROUP_COURSE_PERIOD,
                'value' => SchoolTimetable::periodValue($period['start'], $period['end']),
                'label' => $period['label'] ?: SchoolTimetable::hourLabel($i + 1),
                'sort_order' => $order,
                'is_active' => true,
            ]);
            $order += 10;
        }

        notify()->success(count($periods).' tranche(s) générée(s) à partir des horaires.');
        $this->activeGroup = SchoolOptionCatalog::GROUP_COURSE_PERIOD;
        $this->resetPage();
    }

    protected function persistHours(): bool
    {
        if (! $this->authorizeSchool('school_settings.manage')) {
            return false;
        }

        $this->dayStart = SchoolTimetable::formatTime($this->dayStart);
        $this->dayEnd = SchoolTimetable::formatTime($this->dayEnd);

        $this->validate([
            'dayStart' => ['required', 'date_format:H:i'],
            'dayEnd' => ['required', 'date_format:H:i', 'after:dayStart'],
            'lessonMinutes' => ['required', 'integer', 'min:20', 'max:180'],
            'break1Minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'break2Minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'break1After' => ['required', 'integer', 'min:1', 'max:12'],
            'break2After' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if ((int) $this->break1After === (int) $this->break2After) {
            notify()->error('Les deux pauses ne peuvent pas tomber après la même heure de cours.');

            return false;
        }

        SchoolSettings::set(SchoolSettings::KEY_DAY_START, $this->dayStart);
        SchoolSettings::set(SchoolSettings::KEY_DAY_END, $this->dayEnd);
        SchoolSettings::set(SchoolSettings::KEY_LESSON_MINUTES, (string) (int) $this->lessonMinutes);
        SchoolSettings::set(SchoolSettings::KEY_BREAK1_MINUTES, (string) (int) $this->break1Minutes);
        SchoolSettings::set(SchoolSettings::KEY_BREAK2_MINUTES, (string) (int) $this->break2Minutes);
        SchoolSettings::set(SchoolSettings::KEY_BREAK1_AFTER, (string) (int) $this->break1After);
        SchoolSettings::set(SchoolSettings::KEY_BREAK2_AFTER, (string) (int) $this->break2After);

        return true;
    }

    protected function suggestNextPeriod(): void
    {
        $existing = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_COURSE_PERIOD, false);
        $count = $existing->count();
        $this->label = SchoolTimetable::hourLabel($count + 1);
        $this->sortOrder = (($existing->max('sort_order') ?: 0) + 10);

        $last = $existing->sortBy(function ($row) {
            $parsed = SchoolTimetable::parsePeriodValue((string) $row->value);

            return $parsed['start'] ?? '99:99';
        })->last();
        $parsed = $last ? SchoolTimetable::parsePeriodValue((string) $last->value) : null;
        if ($parsed) {
            $this->periodStart = SchoolTimetable::addMinutes($parsed['end'], 5);
            $this->periodEnd = SchoolTimetable::addMinutes($this->periodStart, 50);
        } else {
            $this->periodStart = '07:30';
            $this->periodEnd = '08:20';
        }
    }

    public function seedDefaults(): void
    {
        SchoolOptionCatalog::seedDefaults();
        notify()->success('Valeurs par défaut ajoutées (sans écraser l’existant).');
    }

    public function render()
    {
        $groups = SchoolOptionCatalog::groups();
        $tabs = [];
        foreach ($groups as $key => $meta) {
            if ($key === SchoolOptionCatalog::GROUP_COURSE_PERIOD) {
                $tabs[self::TAB_HOURS] = [
                    'label' => 'Horaires',
                    'hint' => 'Début, fermeture et pauses.',
                ];
            }
            $tabs[$key] = $meta;
        }

        $term = trim($this->search);

        $options = $this->isHoursTab()
            ? SchoolOption::query()->whereRaw('1 = 0')->paginate(20)
            : SchoolOption::query()
                ->where('group_key', $this->activeGroup)
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.mb_strtolower($term).'%';
                    $q->where(function ($inner) use ($like) {
                        $inner->whereRaw('LOWER(value) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(label) LIKE ?', [$like]);
                    });
                })
                ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
                ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
                ->orderBy('sort_order')
                ->orderBy('label')
                ->paginate(20);

        $subtitle = 'Sections, genres, statuts, horaires, tranches de cours et types de pièces.';
        if ($this->isHoursTab()) {
            $subtitle = 'Début et fermeture des cours, durées des 1ère et 2e pauses.';
        } elseif ($this->isPeriodGroup()) {
            $subtitle = 'Définissez les heures (tranches) utilisées dans l’emploi du temps.';
        } elseif ($this->activeGroup === SchoolOptionCatalog::GROUP_DOCUMENT_TYPE) {
            $subtitle = 'Types de pièces du dossier élève (acte de naissance, etc.).';
        }

        return view('school::livewire.school.options.index', [
            'groups' => $tabs,
            'options' => $options,
            'isPeriodGroup' => $this->isPeriodGroup(),
            'isHoursTab' => $this->isHoursTab(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Paramétrage',
            'subtitle' => $subtitle,
        ]);
    }
}
