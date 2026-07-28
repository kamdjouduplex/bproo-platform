<?php

namespace InovCom\Prescriptions\Http\Livewire;

use InovCom\Prescriptions\Models\Prescription;
use Livewire\Component;
use Livewire\WithPagination;

class PrescriptionsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Prescription::query()->with('client')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', function ($q3) {
                            $q3->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('phone', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderByDesc('created_at');

        $prescriptions = $query->paginate(15);

        return view('inovcom-prescriptions::livewire.prescriptions.index')
            ->layout('layouts.app', [
                'title' => 'Ordonnances',
                'subtitle' => 'Pharmacie',
            ])
            ->with('prescriptions', $prescriptions);
    }
}
