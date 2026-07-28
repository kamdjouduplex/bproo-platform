<?php

namespace InovCom\Clients\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientContact;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClientForm extends Component
{
    use AuthorizesWithTenant;

    public ?int $clientId = null;

    // ── Section 1 : Legal ───────────────────────────────────────────
    public string $code         = '';
    public string $name         = '';
    public string $type         = 'company';
    public string $legal_form   = '';
    public string $industry     = '';
    public string $tax_id       = '';
    public string $rccm         = '';
    public string $website      = '';
    public string $segment      = '';
    public string $payment_terms = '30';
    public string $currency     = 'XOF';
    public string $credit_limit = '0';
    public bool   $is_active    = true;

    // ── Section 2 : Address & contact ───────────────────────────────
    public string $address      = '';
    public string $city         = '';
    public string $postal_code  = '';
    public string $country      = '';
    public string $email        = '';
    public string $phone        = '';
    public string $phone2       = '';
    public string $notes        = '';

    // ── Section 3 : Primary contact person ──────────────────────────
    public string $contact_name  = '';
    public string $contact_role  = '';
    public string $contact_email = '';
    public string $contact_phone = '';

    public function mount(?Client $client = null): void
    {
        $this->tenantAuthorize('clients.view');

        if ($client && $client->exists) {
            $this->clientId      = $client->id;
            $this->code          = $client->code;
            $this->name          = $client->name;
            $this->type          = $client->type;
            $this->legal_form    = $client->legal_form   ?? '';
            $this->industry      = $client->industry     ?? '';
            $this->tax_id        = $client->tax_id       ?? '';
            $this->rccm          = $client->rccm         ?? '';
            $this->website       = $client->website      ?? '';
            $this->segment       = $client->segment      ?? '';
            $this->payment_terms = (string) ($client->payment_terms ?? 30);
            $this->currency      = $client->currency     ?? 'XOF';
            $this->credit_limit  = (string) $client->credit_limit;
            $this->is_active     = $client->is_active;

            $this->address       = $client->address      ?? '';
            $this->city          = $client->city         ?? '';
            $this->postal_code   = $client->postal_code  ?? '';
            $this->country       = $client->country      ?? '';
            $this->email         = $client->email        ?? '';
            $this->phone         = $client->phone        ?? '';
            $this->phone2        = $client->phone2       ?? '';
            $this->notes         = $client->notes        ?? '';

            $primary = $client->contacts()->where('is_primary', true)->first();
            if ($primary) {
                $this->contact_name  = $primary->name  ?? '';
                $this->contact_role  = $primary->role  ?? '';
                $this->contact_email = $primary->email ?? '';
                $this->contact_phone = $primary->phone ?? '';
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'type'          => ['required', 'in:company,individual'],
            'legal_form'    => ['nullable', 'string', 'max:50'],
            'industry'      => ['nullable', 'string', 'max:150'],
            'tax_id'        => ['nullable', 'string', 'max:100'],
            'rccm'          => ['nullable', 'string', 'max:100'],
            'website'       => ['nullable', 'string', 'max:255'],
            'segment'       => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'integer', 'min:0'],
            'currency'      => ['nullable', 'string', 'max:3'],
            'credit_limit'  => ['nullable', 'numeric', 'min:0'],
            'is_active'     => ['boolean'],

            'address'       => ['nullable', 'string'],
            'city'          => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'email'         => ['nullable', 'email'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'phone2'        => ['nullable', 'string', 'max:50'],
            'notes'         => ['nullable', 'string'],

            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_role'  => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->clientId) {
            $rules['code'] = ['required', 'string', 'max:50',
                Rule::unique(Client::class, 'code')->ignore($this->clientId)];
        }

        return $rules;
    }

    protected function generateNextClientCode(): string
    {
        $max = Client::where('code', 'like', 'CLI%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) substr($code, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'CLI' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->clientId ? 'clients.edit' : 'clients.create');
        $this->validate();

        $code = $this->clientId ? $this->code : $this->generateNextClientCode();

        $data = [
            'code'          => $code,
            'name'          => $this->name,
            'type'          => $this->type,
            'legal_form'    => $this->legal_form    ?: null,
            'industry'      => $this->industry      ?: null,
            'tax_id'        => $this->tax_id        ?: null,
            'rccm'          => $this->rccm          ?: null,
            'website'       => $this->website       ?: null,
            'segment'       => $this->segment       ?: null,
            'payment_terms' => (int) $this->payment_terms ?: 30,
            'currency'      => $this->currency      ?: 'XOF',
            'credit_limit'  => (float) $this->credit_limit,
            'is_active'     => $this->is_active,
            'address'       => $this->address       ?: null,
            'city'          => $this->city          ?: null,
            'postal_code'   => $this->postal_code   ?: null,
            'country'       => $this->country       ?: null,
            'email'         => $this->email         ?: null,
            'phone'         => $this->phone         ?: null,
            'phone2'        => $this->phone2        ?: null,
            'notes'         => $this->notes         ?: null,
        ];

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        if ($this->clientId) {
            $client = Client::findOrFail($this->clientId);
            $client->update($data);
        } else {
            $client = Client::create($data);
            $this->clientId = $client->id;
        }

        // Sync primary contact
        if ($this->contact_name) {
            $client->contacts()->updateOrCreate(
                ['is_primary' => true],
                [
                    'name'       => $this->contact_name,
                    'role'       => $this->contact_role  ?: null,
                    'email'      => $this->contact_email ?: null,
                    'phone'      => $this->contact_phone ?: null,
                    'is_primary' => true,
                ]
            );
        }

        notify()->success($this->clientId ? __('Client mis à jour.') : __('Client créé.'));

        $this->redirect(
            route('tenant.clients.index', ['tenant' => $tenantCode]),
            navigate: true
        );
    }

    public function render()
    {
        return view('inovcom-clients::livewire.clients.form')
            ->layout('layouts.app', [
                'title'    => $this->clientId ? __('Modifier le client') : __('Nouveau client'),
                'subtitle' => $this->code ?: '',
            ]);
    }
}
