<?php

namespace InovCom\Prescriptions\Http\Livewire;

use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Prescriptions\Models\Prescription;
use InovCom\Prescriptions\Models\PrescriptionLine;
use Livewire\Component;

class PrescriptionForm extends Component
{
    public ?int $prescriptionId = null;

    public string $number = '';
    public ?int $client_id = null;
    public string $prescriber_name = '';
    public string $prescriber_contact = '';
    public ?string $valid_from = null;
    public ?string $valid_until = null;
    public string $status = 'active';
    public string $notes = '';

    /** @var array<int, array{item_id: int|null, item_name: string, quantity: string, instructions: string}> */
    public array $lines = [];

    public function mount($prescription = null): void
    {
        if ($prescription instanceof Prescription) {
            $prescription = $prescription->refresh();
        } elseif (is_numeric($prescription)) {
            $prescription = Prescription::find($prescription);
        } else {
            $prescription = request()->route('prescription');
            $prescription = $prescription instanceof Prescription ? $prescription : null;
        }
        if (!$prescription) {
            $this->number = $this->generateNumber();
            $this->lines = [['item_id' => null, 'item_name' => '', 'quantity' => '1', 'instructions' => '']];
            return;
        }
        $this->prescriptionId = $prescription->id;
        $this->number = $prescription->number;
        $this->client_id = $prescription->client_id;
        $this->prescriber_name = $prescription->prescriber_name ?? '';
        $this->prescriber_contact = $prescription->prescriber_contact ?? '';
        $this->valid_from = $prescription->valid_from?->format('Y-m-d');
        $this->valid_until = $prescription->valid_until?->format('Y-m-d');
        $this->status = $prescription->status;
        $this->notes = $prescription->notes ?? '';
        $this->lines = $prescription->lines()->with('item')->get()->map(fn ($l) => [
            'item_id' => $l->item_id,
            'item_name' => $l->item?->name ?? '',
            'quantity' => (string) $l->quantity,
            'instructions' => $l->instructions ?? '',
        ])->toArray();
        if (empty($this->lines)) {
            $this->lines = [['item_id' => null, 'item_name' => '', 'quantity' => '1', 'instructions' => '']];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => null, 'item_name' => '', 'quantity' => '1', 'instructions' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate([
            'number' => 'required|string|max:80',
            'client_id' => 'required|exists:tenant.clients,id',
            'prescriber_name' => 'nullable|string|max:255',
            'prescriber_contact' => 'nullable|string|max:255',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'in:draft,active,dispensed,expired,cancelled',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:tenant.items,id',
            'lines.*.quantity' => 'required|numeric|min:0.001',
        ]);

        $prescription = $this->prescriptionId ? Prescription::find($this->prescriptionId) : new Prescription();
        if (!$prescription) {
            return;
        }

        $prescription->fill([
            'number' => $this->number,
            'client_id' => $this->client_id,
            'prescriber_name' => $this->prescriber_name ?: null,
            'prescriber_contact' => $this->prescriber_contact ?: null,
            'valid_from' => $this->valid_from ?: null,
            'valid_until' => $this->valid_until ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ]);
        $prescription->save();

        $prescription->lines()->delete();
        foreach (array_values(array_filter($this->lines, fn ($r) => !empty($r['item_id']))) as $idx => $row) {
            PrescriptionLine::create([
                'prescription_id' => $prescription->id,
                'item_id' => (int) $row['item_id'],
                'quantity' => (float) $row['quantity'],
                'instructions' => $row['instructions'] ?? null,
                'sort_order' => $idx,
            ]);
        }

        $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
        $this->redirect(route('tenant.prescriptions.index', ['tenant' => $tenantCode]), navigate: true);
    }

    private function generateNumber(): string
    {
        $prefix = 'RX-' . now()->format('Ymd') . '-';
        $last = Prescription::where('number', 'like', $prefix . '%')->orderByDesc('id')->first();
        $seq = $last ? (int) substr($last->number, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('inovcom-prescriptions::livewire.prescription.form')
            ->layout('layouts.app', [
                'title' => $this->prescriptionId ? 'Modifier ordonnance' : 'Nouvelle ordonnance',
                'subtitle' => 'Pharmacie',
            ])
            ->with([
                'clients' => Client::orderBy('name')->get(['id', 'name', 'phone']),
                'items' => Item::orderBy('name')->get(['id', 'name', 'sku']),
            ]);
    }
}
