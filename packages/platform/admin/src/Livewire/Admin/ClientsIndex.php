<?php

namespace App\Livewire\Admin;

use Livewire\Component;

/**
 * @deprecated Clients = Companies. Canonical screen is Tenants (Organisation → Clients).
 */
class ClientsIndex extends Component
{
    public function mount(): void
    {
        $this->redirect(route('system.tenants'), navigate: true);
    }

    public function render()
    {
        return '<div></div>';
    }
}
