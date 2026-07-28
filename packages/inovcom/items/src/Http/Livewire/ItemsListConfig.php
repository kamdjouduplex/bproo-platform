<?php

namespace InovCom\Items\Http\Livewire;

use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\Services\ItemsListColumnService;
use Livewire\Component;

class ItemsListConfig extends Component
{
    use AuthorizesItemAccess;

    /** @var array<int, array{key: string, label: string, visible: bool, order: int}> */
    public array $columns = [];

    public function mount(): void
    {
        if (!$this->canItem('items.configure_list')) {
            abort(403, 'Permission refusée.');
        }

        $this->columns = app(ItemsListColumnService::class)->getColumns();
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $prev = $this->columns[$index - 1]['order'] ?? 0;
        $this->columns[$index - 1]['order'] = $this->columns[$index]['order'];
        $this->columns[$index]['order'] = $prev;
        $this->columns = $this->sorted();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->columns) - 1) {
            return;
        }
        $next = $this->columns[$index + 1]['order'] ?? 0;
        $this->columns[$index + 1]['order'] = $this->columns[$index]['order'];
        $this->columns[$index]['order'] = $next;
        $this->columns = $this->sorted();
    }

    public function save(): void
    {
        if (!$this->canItem('items.configure_list')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        app(ItemsListColumnService::class)->saveColumns($this->columns);
        session()->flash('success', 'Configuration de la liste enregistrée.');
        $this->redirect(route('tenant.items.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function resetDefaults(): void
    {
        $this->columns = ItemsListColumnService::defaultColumns();
    }

    public function render()
    {
        return view('inovcom-items::livewire.items.list-config')
            ->layout('layouts.app', [
                'title' => 'Configuration liste articles',
                'subtitle' => 'Colonnes visibles et ordre d\'affichage',
            ]);
    }

    private function sorted(): array
    {
        $cols = $this->columns;
        usort($cols, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        foreach ($cols as $i => &$col) {
            $col['order'] = ($i + 1) * 10;
        }

        return $cols;
    }
}
