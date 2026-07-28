<?php

namespace InovCom\Providers\Http\Livewire;

use InovCom\Providers\Models\PaymentTerm;
use InovCom\Providers\Models\Provider;
use InovCom\Providers\Models\ProviderContact;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProvidersForm extends Component
{
    public ?int $providerId = null;

    // Provider fields
    public string $code = '';
    public string $name = '';
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $address = null;
    public ?string $city = null;
    public string $country = 'CM';
    public bool $is_foreign = false;
    public ?string $default_currency = null;
    public ?string $tax_id = null;
    public ?int $payment_term_id = null;
    public ?string $payment_method = null;
    public ?string $notes = null;
    public bool $is_active = true;

    // Contact fields (simplified - just one primary contact)
    public ?string $contact_name = null;
    public ?string $contact_phone = null;
    public ?string $contact_email = null;
    public ?string $contact_position = null;

    // Quick add payment term
    public string $newPaymentTermName = '';
    public string $newPaymentTermDays = '0';

    public function mount(?Provider $provider = null): void
    {
        if (!$provider) {
            $this->code = $this->generateProviderCode();
            return;
        }

        $this->providerId = $provider->id;
        $this->code = $provider->code;
        $this->name = $provider->name;
        $this->phone = $provider->phone;
        $this->email = $provider->email;
        $this->address = $provider->address;
        $this->city = $provider->city;
        $this->country = $provider->country;
        $this->is_foreign = (bool) $provider->is_foreign;
        $this->default_currency = $provider->default_currency;
        $this->tax_id = $provider->tax_id;
        $this->payment_term_id = $provider->payment_term_id;
        $this->payment_method = $provider->payment_method;
        $this->notes = $provider->notes;
        $this->is_active = $provider->is_active;

        // Load primary contact
        $contact = $provider->primaryContact;
        if ($contact) {
            $this->contact_name = $contact->name;
            $this->contact_phone = $contact->phone;
            $this->contact_email = $contact->email;
            $this->contact_position = $contact->position;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique(Provider::class, 'code')->ignore($this->providerId)],
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'is_foreign' => 'boolean',
            'default_currency' => ['nullable', Rule::in(Provider::currencyCodes())],
            'tax_id' => 'nullable|string|max:100',
            'payment_term_id' => ['nullable', Rule::exists(PaymentTerm::class, 'id')],
            'payment_method' => 'nullable|in:cash,mobile_money,check,bank_transfer',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_position' => 'nullable|string|max:100',
        ]);

        $provider = $this->providerId ? Provider::find($this->providerId) : new Provider();
        if (!$provider) {
            return;
        }

        $provider->fill([
            'code' => $data['code'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'city' => $data['city'],
            'country' => $data['country'],
            'is_foreign' => $data['is_foreign'],
            'default_currency' => $data['is_foreign'] ? ($data['default_currency'] ?: null) : null,
            'tax_id' => $data['tax_id'],
            'payment_term_id' => $data['payment_term_id'],
            'payment_method' => $data['payment_method'] ?: null,
            'notes' => $data['notes'],
            'is_active' => $data['is_active'],
        ]);
        $provider->save();

        // Save or update primary contact (simplified - just one contact)
        if (!empty($data['contact_name'])) {
            $contact = $provider->primaryContact;
            if (!$contact) {
                $contact = new ProviderContact();
                $contact->provider_id = $provider->id;
                $contact->is_primary = true;
            }
            $contact->fill([
                'name' => $data['contact_name'],
                'phone' => $data['contact_phone'],
                'email' => $data['contact_email'],
                'position' => $data['contact_position'],
            ]);
            $contact->save();
        } else {
            // Remove contact if name is empty
            $provider->primaryContact?->delete();
        }

        $this->redirect(route('tenant.providers.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function createPaymentTerm(): void
    {
        $data = $this->validate([
            'newPaymentTermName' => 'required|string|max:255',
            'newPaymentTermDays' => 'required|integer|min:0',
        ]);

        $term = PaymentTerm::create([
            'name' => $data['newPaymentTermName'],
            'days' => (int) $data['newPaymentTermDays'],
            'is_active' => true,
        ]);

        $this->payment_term_id = $term->id;
        $this->newPaymentTermName = '';
        $this->newPaymentTermDays = '0';
    }

    private function generateProviderCode(): string
    {
        $lastProvider = Provider::orderBy('id', 'desc')->first();
        $nextNumber = $lastProvider ? ((int) preg_replace('/[^0-9]/', '', $lastProvider->code)) + 1 : 1;
        return 'FOUR-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('inovcom-providers::livewire.providers.form')
            ->layout('layouts.app', [
                'title' => $this->providerId ? 'Modifier fournisseur' : 'Nouveau fournisseur',
                'subtitle' => 'Gestion des fournisseurs',
            ])
            ->with([
                'paymentTerms' => PaymentTerm::orderBy('days')->get(),
                'paymentMethods' => Provider::PAYMENT_METHODS,
                'currencies' => Provider::CURRENCIES,
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
