<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use Livewire\Component;

class Modules extends Component
{
    public array $modules = [];

    public function mount(): void
    {
        $this->refreshModules();
    }

    public function delete(int $moduleId): void
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return;
        }

        $module->delete();
        $this->refreshModules();
    }

    private function refreshModules(): void
    {
        $this->modules = Module::orderBy('label')->get()->toArray();
    }

    public function render()
    {
        return view('livewire.admin.modules')
            ->layout('layouts.app', [
                'title' => 'Modules',
                'subtitle' => 'Registre des modules',
            ]);
    }
}
