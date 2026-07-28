<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Enums\CreditNoteStatus;
use InovCom\Returns\Models\CreditNote;
use Livewire\Component;
use Livewire\WithPagination;

class CreditNotesIndex extends Component
{
    use WithPagination;
    use AuthorizesReturnActions;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function render()
    {
        $creditNotes = CreditNote::query()
            ->with('client')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('credit_note_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('inovcom-returns::livewire.credit-notes.index')
            ->layout('layouts.app', [
                'title' => 'Avoirs',
                'subtitle' => 'Avoirs clients issus des retours',
            ])
            ->with([
                'creditNotes' => $creditNotes,
                'statuses' => CreditNoteStatus::options(),
            ]);
    }
}
