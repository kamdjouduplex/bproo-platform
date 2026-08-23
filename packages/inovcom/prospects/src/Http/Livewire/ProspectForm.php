<?php

namespace InovCom\Prospects\Http\Livewire;

use InovCom\Prospects\Concerns\AuthorizesProspectActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Component;

class ProspectForm extends Component
{
    use AuthorizesProspectActions;

    public ?int $prospectId = null;

    public string $name = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $company_name = '';

    public string $job_title = '';

    public string $type = 'company';

    public string $email = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $address = '';

    public string $city = '';

    public string $tax_id = '';

    public string $rccm = '';

    public string $niu = '';

    public string $source = 'other';

    public string $cost = '0';

    public string $expected_value = '';

    public ?int $owner_id = null;

    public string $notes = '';

    public string $need = '';

    public string $product_interest = '';

    public function mount(?Prospect $prospect = null): void
    {
        if ($prospect && $prospect->exists) {
            $this->authorizeProspectAction('prospects.update');
            if (! $prospect->isEditable()) {
                session()->flash('error', 'Ce prospect converti ne peut plus être modifié.');
                $this->redirect(route('tenant.prospects.show', [
                    'prospect' => $prospect->id,
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);

                return;
            }

            $this->prospectId = $prospect->id;
            $this->name = (string) $prospect->name;
            $this->first_name = (string) ($prospect->first_name ?? '');
            $this->last_name = (string) ($prospect->last_name ?? '');
            $this->company_name = (string) ($prospect->company_name ?? '');
            $this->job_title = (string) ($prospect->job_title ?? '');
            $this->type = (string) $prospect->type;
            $this->email = (string) ($prospect->email ?? '');
            $this->phone = (string) ($prospect->phone ?? '');
            $this->whatsapp = (string) ($prospect->whatsapp ?? '');
            $this->address = (string) ($prospect->address ?? '');
            $this->city = (string) ($prospect->city ?? '');
            $this->tax_id = (string) ($prospect->tax_id ?? '');
            $this->rccm = (string) ($prospect->rccm ?? '');
            $this->niu = (string) ($prospect->niu ?? '');
            $this->source = (string) $prospect->source;
            $this->cost = (string) $prospect->cost;
            $this->expected_value = $prospect->expected_value !== null ? (string) $prospect->expected_value : '';
            $this->owner_id = $prospect->owner_id;
            $this->notes = (string) ($prospect->notes ?? '');
            $this->need = (string) ($prospect->need ?? '');
            $this->product_interest = (string) ($prospect->product_interest ?? '');
        } else {
            $this->authorizeProspectAction('prospects.create');
        }
    }

    public function save(): void
    {
        $this->authorizeProspectAction($this->prospectId ? 'prospects.update' : 'prospects.create');

        $this->validate([
            'name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'company_name' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:120',
            'type' => 'required|in:individual,company',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
            'whatsapp' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:120',
            'tax_id' => 'nullable|string|max:64',
            'rccm' => 'nullable|string|max:64',
            'niu' => 'nullable|string|max:64',
            'source' => 'required|in:' . implode(',', array_keys(Prospect::sourceOptions())),
            'cost' => 'nullable|numeric|min:0',
            'expected_value' => 'nullable|numeric|min:0',
            'owner_id' => 'nullable|integer|exists:tenant.users,id',
            'notes' => 'nullable|string|max:5000',
            'need' => 'nullable|string|max:180',
            'product_interest' => 'nullable|string|max:180',
        ]);

        $payload = [
            'name' => $this->name,
            'first_name' => $this->first_name ?: null,
            'last_name' => $this->last_name ?: null,
            'company_name' => $this->company_name ?: ($this->type === 'company' ? $this->name : null),
            'job_title' => $this->job_title ?: null,
            'type' => $this->type,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'whatsapp' => $this->whatsapp ?: $this->phone ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'tax_id' => $this->tax_id ?: null,
            'rccm' => $this->rccm ?: null,
            'niu' => $this->niu ?: null,
            'source' => $this->source,
            'cost' => $this->cost !== '' ? $this->cost : 0,
            'expected_value' => $this->expected_value !== '' ? $this->expected_value : null,
            'owner_id' => $this->owner_id,
            'notes' => $this->notes ?: null,
            'need' => $this->need ?: null,
            'product_interest' => $this->product_interest ?: null,
        ];

        try {
            $service = app(ProspectsService::class);
            if ($this->prospectId) {
                $prospect = Prospect::findOrFail($this->prospectId);
                $prospect = $service->update($prospect, $payload);
                session()->flash('success', 'Prospect mis à jour.');
            } else {
                $prospect = $service->create($payload);
                session()->flash('success', 'Prospect ' . $prospect->reference . ' créé.');
            }

            $this->redirect(route('tenant.prospects.show', [
                'prospect' => $prospect->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('inovcom-prospects::livewire.prospects.form')
            ->layout('layouts.app', [
                'title' => $this->prospectId ? 'Modifier le prospect' : 'Nouveau prospect',
                'subtitle' => 'Fiche de prospection',
            ])
            ->with([
                'owners' => User::query()->orderBy('name')->get(['id', 'name']),
                'tenantCode' => $this->tenantCode(),
            ]);
    }
}
