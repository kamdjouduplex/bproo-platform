<?php

namespace InovCom\Clients\Http\Livewire;

use InovCom\Clients\Concerns\AuthorizesClientActions;
use InovCom\Clients\Models\Address;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientCategory;
use InovCom\Clients\Models\Contact;
use InovCom\Clients\Models\Segment;
use InovCom\Clients\Models\Zone;
use InovCom\Clients\Services\ClientCodeGenerator;
use InovCom\Clients\Services\ClientDebtInsightService;
use InovCom\Clients\Support\ClientRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ClientsForm extends Component
{
    use AuthorizesClientActions;

    public ?int $clientId = null;
    public string $activeTab = 'general';

    // Client fields
    public string $code = '';
    public string $name = '';
    public string $type = 'individual';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $tax_id = null;
    public ?string $rccm = null;
    public ?string $niu = null;
    public ?string $bp = null;
    public ?int $segment_id = null;
    public ?int $zone_id = null;
    public ?int $category_id = null;
    public ?int $payment_term_id = null;
    public ?string $payment_method = null;
    public ?int $salesrep_id = null;
    public string $credit_limit = '0';
    public string $discount_rate = '0';
    public string $price_tier = 'retail';
    public bool $is_active = true;
    public ?string $notes = null;

    // Contacts & addresses (édités en tableaux)
    public array $contacts = [];
    public array $addresses = [];

    // Quick add
    public string $newSegmentName = '';
    public string $newSegmentCode = '';
    public string $newZoneName = '';
    public string $newZoneCode = '';
    public string $newCategoryName = '';
    public string $newCategoryCode = '';

    public function mount(?Client $client = null): void
    {
        if (! $client || ! $client->exists) {
            $this->code = app(ClientCodeGenerator::class)->preview();
            return;
        }

        $this->clientId = $client->id;
        $this->code = $client->code;
        $this->name = $client->name;
        $this->type = $client->type;
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->address = $client->address;
        $this->tax_id = $client->tax_id;
        $this->rccm = $client->rccm;
        $this->niu = $client->niu;
        $this->bp = $client->bp;
        $this->segment_id = $client->segment_id;
        $this->zone_id = $client->zone_id;
        $this->category_id = $client->category_id;
        $this->payment_term_id = $client->payment_term_id;
        $this->payment_method = $client->payment_method;
        $this->salesrep_id = $client->salesrep_id;
        $this->credit_limit = (string) $client->credit_limit;
        $this->discount_rate = (string) $client->discount_rate;
        $this->price_tier = $client->price_tier ?: 'retail';
        $this->is_active = $client->is_active;
        $this->notes = $client->notes;

        $this->contacts = $client->contacts()->orderByDesc('is_primary')->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'civility' => $c->civility,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'role' => $c->role ?: 'other',
                'position' => $c->position,
                'email' => $c->email,
                'phone' => $c->phone,
                'mobile' => $c->mobile,
                'is_primary' => (bool) $c->is_primary,
            ])->toArray();

        $this->addresses = $client->addresses()->get()
            ->map(fn (Address $a) => [
                'id' => $a->id,
                'type' => $a->type ?: 'billing',
                'street' => $a->street,
                'city' => $a->city,
                'state' => $a->state,
                'postal_code' => $a->postal_code,
                'country' => $a->country ?: 'CM',
                'is_default' => (bool) $a->is_default,
            ])->toArray();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedType(string $value): void
    {
        if ($value !== 'company') {
            $this->rccm = null;
            $this->niu = null;
            $this->bp = null;
        }
    }

    public function updatedCategoryId($value): void
    {
        if (! $value) {
            return;
        }

        $category = ClientCategory::find($value);
        if ($category) {
            // Pré-remplit la remise et le palier tarifaire avec les valeurs par défaut
            // de la catégorie (modifiables ensuite manuellement).
            $this->discount_rate = (string) $category->default_discount_rate;
            $this->price_tier = $category->default_price_tier ?: 'retail';
        }
    }

    // --- Contacts ---------------------------------------------------------

    public function addContact(): void
    {
        $this->contacts[] = [
            'id' => null,
            'civility' => null,
            'first_name' => '',
            'last_name' => null,
            'role' => count($this->contacts) === 0 ? 'principal' : 'other',
            'position' => null,
            'email' => null,
            'phone' => null,
            'mobile' => null,
            'is_primary' => count($this->contacts) === 0,
        ];
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function setPrimaryContact(int $index): void
    {
        foreach ($this->contacts as $i => $contact) {
            $this->contacts[$i]['is_primary'] = ($i === $index);
        }
    }

    // --- Adresses ---------------------------------------------------------

    public function addAddress(): void
    {
        $this->addresses[] = [
            'id' => null,
            'type' => 'billing',
            'street' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => 'CM',
            'is_default' => count($this->addresses) === 0,
        ];
    }

    public function removeAddress(int $index): void
    {
        unset($this->addresses[$index]);
        $this->addresses = array_values($this->addresses);
    }

    public function setDefaultAddress(int $index): void
    {
        foreach ($this->addresses as $i => $address) {
            $this->addresses[$i]['is_default'] = ($i === $index);
        }
    }

    public function save(): void
    {
        $permission = $this->clientId ? 'clients.update' : 'clients.create';
        if (! $this->can($permission)) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        // Normalisation
        $this->email = $this->email ? strtolower(trim($this->email)) : null;

        $rules = array_merge(
            ClientRules::rules($this->clientId, $this->type),
            ClientRules::contactRules(),
            ClientRules::addressRules(),
        );

        $data = $this->validate($rules, ClientRules::messages());

        $this->ensureSinglePrimaryContact();
        $this->ensureSingleDefaultAddress();

        DB::connection('tenant')->transaction(function () use ($data) {
            $client = $this->clientId ? Client::find($this->clientId) : new Client();
            if (! $client) {
                return;
            }

            // Code atomique seulement à la création.
            $code = $this->clientId ? $data['code'] : app(ClientCodeGenerator::class)->next();

            $companyFields = $data['type'] === 'company'
                ? ['rccm' => $data['rccm'], 'niu' => $data['niu'], 'bp' => $data['bp']]
                : ['rccm' => null, 'niu' => null, 'bp' => null];

            $userId = Auth::guard('tenant')->id();

            $payload = [
                'code' => $code,
                'name' => $data['name'],
                'type' => $data['type'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'tax_id' => $data['tax_id'],
                ...$companyFields,
                'segment_id' => $data['segment_id'],
                'zone_id' => $data['zone_id'] ?: null,
                'category_id' => $data['category_id'] ?: null,
                'payment_term_id' => $data['payment_term_id'] ?: null,
                'payment_method' => $data['payment_method'] ?: null,
                'salesrep_id' => $data['salesrep_id'] ?: null,
                'credit_limit' => $data['credit_limit'],
                'discount_rate' => $data['discount_rate'],
                'price_tier' => $data['price_tier'],
                'is_active' => $data['is_active'],
                'notes' => $data['notes'],
                'updated_by' => $userId,
            ];

            // current_balance n'est PAS modifiable depuis le formulaire (calculé via les dettes).
            if (! $client->exists) {
                $payload['created_by'] = $userId;
            }

            $client->fill($payload);
            $client->save();

            $this->clientId = $client->id;

            $this->syncContacts($client);
            $this->syncAddresses($client);
        });

        session()->flash('success', 'Client enregistré avec succès.');
        $this->redirect(route('tenant.clients.show', [$this->clientId, 'tenant' => $this->tenantCode()]), navigate: true);
    }

    private function ensureSinglePrimaryContact(): void
    {
        $hasPrimary = false;
        foreach ($this->contacts as $i => $contact) {
            if (! empty($contact['is_primary']) && ! $hasPrimary) {
                $hasPrimary = true;
            } else {
                $this->contacts[$i]['is_primary'] = false;
            }
        }
        if (! $hasPrimary && count($this->contacts) > 0) {
            $this->contacts[0]['is_primary'] = true;
        }
    }

    private function ensureSingleDefaultAddress(): void
    {
        $hasDefault = false;
        foreach ($this->addresses as $i => $address) {
            if (! empty($address['is_default']) && ! $hasDefault) {
                $hasDefault = true;
            } else {
                $this->addresses[$i]['is_default'] = false;
            }
        }
        if (! $hasDefault && count($this->addresses) > 0) {
            $this->addresses[0]['is_default'] = true;
        }
    }

    private function syncContacts(Client $client): void
    {
        // Remplacement complet : les contacts sont gérés intégralement par ce formulaire.
        $client->contacts()->delete();
        foreach ($this->contacts as $contact) {
            if (empty($contact['first_name'])) {
                continue;
            }
            $client->contacts()->create([
                'civility' => $contact['civility'] ?? null,
                'first_name' => $contact['first_name'],
                'last_name' => $contact['last_name'] ?? null,
                'role' => $contact['role'] ?? 'other',
                'position' => $contact['position'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'mobile' => $contact['mobile'] ?? null,
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'is_active' => true,
            ]);
        }
    }

    private function syncAddresses(Client $client): void
    {
        $client->addresses()->delete();
        foreach ($this->addresses as $address) {
            if (empty($address['street']) && empty($address['city'])) {
                continue;
            }
            $client->addresses()->create([
                'type' => $address['type'] ?? 'billing',
                'street' => $address['street'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?: 'CM',
                'is_default' => (bool) ($address['is_default'] ?? false),
            ]);
        }
    }

    public function createSegment(): void
    {
        if (! $this->can('segments.manage')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'newSegmentName' => 'required|string|max:255',
            'newSegmentCode' => 'required|string|max:50|unique:tenant.segments,code',
        ]);

        $segment = Segment::create([
            'name' => $data['newSegmentName'],
            'code' => $data['newSegmentCode'],
            'is_active' => true,
        ]);

        $this->segment_id = $segment->id;
        $this->newSegmentName = '';
        $this->newSegmentCode = '';
    }

    public function createZone(): void
    {
        if (! $this->can('clients.update') && ! $this->can('clients.create')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'newZoneName' => 'required|string|max:255',
            'newZoneCode' => 'required|string|max:30|unique:tenant.zones,code',
        ]);

        $zone = Zone::create([
            'name' => $data['newZoneName'],
            'code' => $data['newZoneCode'],
            'is_active' => true,
        ]);

        $this->zone_id = $zone->id;
        $this->newZoneName = '';
        $this->newZoneCode = '';
    }

    public function createCategory(): void
    {
        if (! $this->can('clients.update') && ! $this->can('clients.create')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'newCategoryName' => 'required|string|max:255',
            'newCategoryCode' => 'required|string|max:30|unique:tenant.client_categories,code',
        ]);

        $category = ClientCategory::create([
            'name' => $data['newCategoryName'],
            'code' => $data['newCategoryCode'],
            'is_active' => true,
        ]);

        $this->category_id = $category->id;
        $this->newCategoryName = '';
        $this->newCategoryCode = '';
    }

    public function render()
    {
        $currentClient = $this->clientId ? Client::with('segment')->find($this->clientId) : null;
        $debtService = app(ClientDebtInsightService::class);
        $insights = $this->buildInsights($currentClient, $debtService);

        return view('inovcom-clients::livewire.clients.form')
            ->layout('layouts.app', [
                'title' => $this->clientId ? 'Modifier client' : 'Nouveau client',
                'subtitle' => 'Gestion des clients',
            ])
            ->with([
                'segments' => Segment::orderBy('name')->get(),
                'zones' => Zone::orderBy('name')->get(),
                'categories' => ClientCategory::orderBy('name')->get(),
                'priceTiers' => Client::PRICE_TIERS,
                'paymentTerms' => $this->paymentTerms(),
                'paymentMethods' => Client::PAYMENT_METHODS,
                'salesreps' => $this->salesreps(),
                'contactRoles' => Contact::ROLES,
                'currentClient' => $currentClient,
                'insights' => $insights,
                'debtsModule' => $debtService->moduleAvailable(),
                'debtSummary' => $insights['debtSummary'],
            ]);
    }

    private function paymentTerms()
    {
        if (! Schema::connection('tenant')->hasTable('payment_terms')) {
            return collect();
        }

        return DB::connection('tenant')->table('payment_terms')
            ->where('is_active', true)
            ->orderBy('days')
            ->get();
    }

    private function salesreps()
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return collect();
        }

        return DB::connection('tenant')->table('users')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function buildInsights(?Client $currentClient, ClientDebtInsightService $debtService): array
    {
        $insights = [
            'totalSalesAmount' => 0.0,
            'salesCount' => 0,
            'totalDebtAmount' => 0.0,
            'outstandingDebt' => 0.0,
            'repaidDebt' => 0.0,
            'openDebtsCount' => 0,
            'overdueDebtsCount' => 0,
            'debtSummary' => $debtService->emptySummary(),
            'lastSaleAt' => null,
            'lastDebtPaymentAt' => null,
            'recentDebts' => collect(),
            'recentDebtPayments' => collect(),
        ];

        if (! $currentClient) {
            return $insights;
        }

        if (Schema::connection('tenant')->hasTable('sales')) {
            $salesQuery = DB::connection('tenant')->table('sales')->where('client_id', $currentClient->id);
            $insights['totalSalesAmount'] = (float) $salesQuery->sum('total');
            $insights['salesCount'] = (int) $salesQuery->count();
            $insights['lastSaleAt'] = $salesQuery->max('created_at');
        }

        if (Schema::connection('tenant')->hasTable('debts')) {
            $debtSummary = $debtService->forClient($currentClient->id);
            $insights['debtSummary'] = $debtSummary;
            $insights['overdueDebtsCount'] = $debtSummary['overdue_count'];

            $debtsQuery = DB::connection('tenant')->table('debts')->where('client_id', $currentClient->id);
            $insights['totalDebtAmount'] = (float) $debtsQuery->sum('total_amount');
            $insights['outstandingDebt'] = $debtSummary['outstanding'];
            $insights['openDebtsCount'] = $debtSummary['open_count'];
            $insights['repaidDebt'] = max(0, $insights['totalDebtAmount'] - $insights['outstandingDebt']);
            $insights['recentDebts'] = DB::connection('tenant')->table('debts')
                ->where('client_id', $currentClient->id)
                ->orderByDesc('opened_at')
                ->limit(5)
                ->get();
        }

        if (Schema::connection('tenant')->hasTable('debt_payments') && Schema::connection('tenant')->hasTable('debts')) {
            $insights['lastDebtPaymentAt'] = DB::connection('tenant')->table('debt_payments')
                ->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->where('debts.client_id', $currentClient->id)
                ->max('debt_payments.created_at');
            $insights['recentDebtPayments'] = DB::connection('tenant')->table('debt_payments')
                ->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->where('debts.client_id', $currentClient->id)
                ->orderByDesc('debt_payments.payment_date')
                ->limit(5)
                ->get([
                    'debt_payments.reference',
                    'debt_payments.payment_date',
                    'debt_payments.amount',
                    'debt_payments.payment_method',
                    'debt_payments.external_reference',
                ]);
        }

        return $insights;
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
