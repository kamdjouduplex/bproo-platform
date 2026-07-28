<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use Livewire\Component;

class ModuleForm extends Component
{
    public ?int $moduleId = null;
    public string $key = '';
    public string $label = '';
    public ?string $description = null;
    public ?string $route_name = null;
    public ?string $lifecycle_handler = null;
    public bool $enabled_by_default = false;

    public function mount(?Module $module = null): void
    {
        if (!$module) {
            return;
        }

        $this->moduleId = $module->id;
        $this->key = $module->key;
        $this->label = $module->label;
        $this->description = $module->description;
        $this->route_name = $module->route_name;
        $this->lifecycle_handler = $module->lifecycle_handler;
        $this->enabled_by_default = $module->enabled_by_default;
    }

    public function save(): void
    {
        $data = $this->validate([
            'key' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'lifecycle_handler' => 'nullable|string|max:255',
            'enabled_by_default' => 'boolean',
        ]);

        $rules = [
            'key' => 'unique:modules,key',
        ];

        if ($this->moduleId) {
            $rules['key'] .= ',' . $this->moduleId;
        }

        $this->validate($rules);

        $module = $this->moduleId ? Module::find($this->moduleId) : new Module();
        if (!$module) {
            return;
        }

        $module->fill($data);
        $module->save();

        redirect()->route('system.modules');
    }

    public function render()
    {
        return view('livewire.admin.module-form')
            ->layout('layouts.app', [
                'title' => $this->moduleId ? 'Modifier module' : 'Créer module',
                'subtitle' => 'Registre des modules',
            ]);
    }
}
