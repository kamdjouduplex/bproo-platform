<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Support\SchoolSettings;
use School\Support\StudentCodeGenerator;

class SchoolIdSettingsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;

    public string $prefix = 'SCH';

    public string $yearFormat = 'yyyy';

    public string $separator = '-';

    public string $seqPadding = '4';

    public string $ministrySchoolCode = '';

    public function mount(): void
    {
        if (! $this->canSchool('school_settings.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->prefix = SchoolSettings::get(SchoolSettings::KEY_ID_PREFIX, 'SCH');
        $this->yearFormat = SchoolSettings::get(SchoolSettings::KEY_ID_YEAR_FORMAT, 'yyyy');
        $this->separator = SchoolSettings::get(SchoolSettings::KEY_ID_SEPARATOR, '-');
        $this->seqPadding = SchoolSettings::get(SchoolSettings::KEY_ID_SEQ_PADDING, '4');
        $this->ministrySchoolCode = SchoolSettings::get(SchoolSettings::KEY_MINISTRY_SCHOOL_CODE);
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_settings.manage')) {
            return;
        }

        $this->validate([
            'prefix' => ['nullable', 'string', 'max:20'],
            'yearFormat' => ['required', 'in:yyyy,yy,none'],
            'separator' => ['required', 'string', 'max:2'],
            'seqPadding' => ['required', 'integer', 'min:3', 'max:8'],
            'ministrySchoolCode' => ['nullable', 'string', 'max:80'],
        ]);

        SchoolSettings::set(SchoolSettings::KEY_ID_PREFIX, strtoupper(trim($this->prefix)));
        SchoolSettings::set(SchoolSettings::KEY_ID_YEAR_FORMAT, $this->yearFormat);
        SchoolSettings::set(SchoolSettings::KEY_ID_SEPARATOR, $this->separator);
        SchoolSettings::set(SchoolSettings::KEY_ID_SEQ_PADDING, (string) $this->seqPadding);
        SchoolSettings::set(SchoolSettings::KEY_MINISTRY_SCHOOL_CODE, strtoupper(trim($this->ministrySchoolCode)));

        notify()->success('Identifiants enregistrés. Les nouveaux matricules et l’export ministère les utiliseront.');
    }

    public function render()
    {
        $preview = StudentCodeGenerator::format(1);

        return view('school::livewire.school.settings.id-pattern', [
            'preview' => $preview,
            'next' => StudentCodeGenerator::next(),
            'canManage' => $this->canSchool('school_settings.manage'),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Identifiants',
            'subtitle' => 'Matricule interne, NISU et code établissement du ministère.',
        ]);
    }
}
