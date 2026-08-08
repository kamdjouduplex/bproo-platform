<?php

namespace App\Livewire\System;

use Livewire\Component;

class ComingSoon extends Component
{
    public string $heading = 'Bientôt disponible';

    public string $description = 'Ce module arrive dans le Control Center.';

    public function mount(): void
    {
        $map = [
            'system.users' => [
                'Utilisateurs',
                'Gestion des opérateurs Control Center — en construction.',
            ],
            'system.roles' => [
                'Rôles & permissions',
                'Contrôle d’accès granulaire pour l’équipe ops — en construction.',
            ],
        ];

        $name = request()->route()?->getName() ?? '';
        if (isset($map[$name])) {
            [$this->heading, $this->description] = $map[$name];
        }
    }

    public function render()
    {
        return view('livewire.system.coming-soon')
            ->layout('layouts.app', [
                'title' => $this->heading,
                'subtitle' => 'Control Center · roadmap',
            ]);
    }
}
