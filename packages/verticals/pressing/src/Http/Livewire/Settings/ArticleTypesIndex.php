<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\ArticlePrice;
use Pressing\Models\ArticleType;

class ArticleTypesIndex extends Component
{
    use AuthorizesPressingActions;

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public ?string $code = null;
    public string $sort_order = '0';
    public string $default_price = '0';
    public bool $is_active = true;

    public function create(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        $this->resetForm();
        $this->sort_order = (string) ((int) ArticleType::max('sort_order') + 1);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        $type = ArticleType::with(['prices' => fn ($q) => $q->whereNull('agence_id')])->findOrFail($id);
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->code = $type->code;
        $this->sort_order = (string) $type->sort_order;
        $this->default_price = (string) ($type->prices->first()?->amount ?? 0);
        $this->is_active = $type->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $type = ArticleType::findOrFail($this->editingId);
            $type->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'sort_order' => (int) $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);
        } else {
            $type = ArticleType::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'sort_order' => (int) $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);
        }

        ArticlePrice::updateOrCreate(
            ['article_type_id' => $type->id, 'agence_id' => null],
            ['amount' => (float) $data['default_price'], 'currency' => 'XAF', 'is_active' => true]
        );

        session()->flash('success', 'Type d’article enregistré.');
        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        ArticleType::findOrFail($id)->delete();
        session()->flash('success', 'Type d’article supprimé.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = null;
        $this->sort_order = '0';
        $this->default_price = '0';
        $this->is_active = true;
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_settings.view');

        $types = ArticleType::query()
            ->with(['prices' => fn ($q) => $q->whereNull('agence_id')])
            ->orderBy('sort_order')
            ->get();

        return view('pressing::livewire.settings.article-types', [
            'types' => $types,
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', ['title' => 'Types de vêtements', 'subtitle' => 'Paramétrage']);
    }
}
