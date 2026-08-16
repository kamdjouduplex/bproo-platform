<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Support\SchoolLocaleCatalog;

class SchoolLanguagesIndex extends Component
{
    use ResolvesTenantCode;

    /** @var array<int, string> */
    public array $enabled = ['fr'];

    public string $defaultLocale = 'fr';

    public function mount(): void
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $this->enabled = array_keys(SchoolLocaleCatalog::enabled($tenant));
        if (! in_array('fr', $this->enabled, true)) {
            $this->enabled[] = 'fr';
        }
        $this->defaultLocale = (string) ($tenant?->getSetting('locale', config('inovcom.default_locale', 'fr')) ?? 'fr');
        if (! in_array($this->defaultLocale, SchoolLocaleCatalog::keys(), true)) {
            $this->defaultLocale = 'fr';
        }
    }

    public function updatedEnabled(): void
    {
        if (! in_array('fr', $this->enabled, true)) {
            $this->enabled[] = 'fr';
        }
    }

    public function save(): void
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (! $tenant || ! method_exists($tenant, 'setSetting')) {
            notify()->error('Tenant indisponible.');

            return;
        }

        $this->validate([
            'enabled' => ['required', 'array', 'min:1'],
            'enabled.*' => ['string', 'in:'.implode(',', SchoolLocaleCatalog::keys())],
            'defaultLocale' => ['required', 'string', 'in:'.implode(',', SchoolLocaleCatalog::keys())],
        ], [
            'enabled.required' => 'Activez au moins une langue.',
            'enabled.min' => 'Activez au moins une langue.',
        ]);

        if (! in_array($this->defaultLocale, $this->enabled, true)) {
            $this->enabled[] = $this->defaultLocale;
        }

        SchoolLocaleCatalog::saveEnabled($tenant, $this->enabled);
        $tenant->setSetting('locale', $this->defaultLocale);

        notify()->success('Langues enregistrées.');
    }

    public function render()
    {
        return view('school::livewire.school.languages.index', [
            'all' => SchoolLocaleCatalog::all(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Langues',
            'subtitle' => 'Activer / désactiver les langues de l’interface.',
        ]);
    }
}
