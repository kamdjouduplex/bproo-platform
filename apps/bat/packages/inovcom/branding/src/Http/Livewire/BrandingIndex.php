<?php

namespace InovCom\Branding\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use Livewire\Component;

class BrandingIndex extends Component
{
    use AuthorizesWithTenant;
    // ── Identité ────────────────────────────────────────────────────
    public string $shop_name            = '';
    public string $login_welcome_message = '';
    public string $company_tagline      = '';
    public string $company_website      = '';

    // ── Coordonnées ─────────────────────────────────────────────────
    public string $company_email        = '';
    public string $company_phone        = '';
    public string $company_phone2       = '';
    public string $company_address      = '';
    public string $company_city         = '';
    public string $company_country      = '';
    public string $company_postal_code  = '';
    public string $company_registration = ''; // Registre du commerce / NINEA
    public string $company_tax_id       = ''; // Numéro contribuable

    // ── Finance & Facturation ────────────────────────────────────────
    public string $currency             = 'XOF';
    public string $tax_rate             = '19.25';
    public string $invoice_prefix       = 'FAC';
    public string $proforma_prefix      = 'PRO';
    public string $credit_note_prefix   = 'AV';
    public string $quote_prefix         = 'DEV';
    public string $payment_terms        = '30';  // jours
    public string $bank_name            = '';
    public string $bank_account         = '';
    public string $bank_iban            = '';
    public string $invoice_footer       = '';

    // ── Régionalisation ─────────────────────────────────────────────
    public string $timezone             = 'Africa/Douala';
    public string $date_format          = 'd/m/Y';
    public string $locale               = 'fr';

    public function mount(): void
    {
        $this->tenantAuthorize('configuration.view');

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            abort(404, 'Tenant non trouvé.');
        }

        // Identité
        $this->shop_name             = (string) $tenant->getSetting('shop_name', $tenant->name);
        $this->login_welcome_message = (string) $tenant->getSetting('login_welcome_message', '');
        $this->company_tagline       = (string) $tenant->getSetting('company_tagline', '');
        $this->company_website       = (string) $tenant->getSetting('company_website', '');

        // Coordonnées
        $this->company_email        = (string) $tenant->getSetting('company_email', '');
        $this->company_phone        = (string) $tenant->getSetting('company_phone', '');
        $this->company_phone2       = (string) $tenant->getSetting('company_phone2', '');
        $this->company_address      = (string) $tenant->getSetting('company_address', '');
        $this->company_city         = (string) $tenant->getSetting('company_city', '');
        $this->company_country      = (string) $tenant->getSetting('company_country', 'Cameroun');
        $this->company_postal_code  = (string) $tenant->getSetting('company_postal_code', '');
        $this->company_registration = (string) $tenant->getSetting('company_registration', '');
        $this->company_tax_id       = (string) $tenant->getSetting('company_tax_id', '');

        // Finance
        $this->currency      = (string) $tenant->getSetting('currency', 'XOF');
        $this->tax_rate      = (string) $tenant->getSetting('tax_rate', '19.25');
        $this->invoice_prefix      = (string) $tenant->getSetting('invoice_prefix', 'FAC');
        $this->proforma_prefix     = (string) $tenant->getSetting('proforma_prefix', 'PRO');
        $this->credit_note_prefix  = (string) $tenant->getSetting('credit_note_prefix', 'AV');
        $this->quote_prefix        = (string) $tenant->getSetting('quote_prefix', 'DEV');
        $this->payment_terms = (string) $tenant->getSetting('payment_terms', '30');
        $this->bank_name     = (string) $tenant->getSetting('bank_name', '');
        $this->bank_account  = (string) $tenant->getSetting('bank_account', '');
        $this->bank_iban     = (string) $tenant->getSetting('bank_iban', '');
        $this->invoice_footer = (string) $tenant->getSetting('invoice_footer', '');

        // Régionalisation
        $this->timezone    = (string) $tenant->getSetting('timezone', 'Africa/Douala');
        $this->date_format = (string) $tenant->getSetting('date_format', 'd/m/Y');
        $this->locale      = (string) $tenant->getSetting('locale', 'fr');
    }

    public function save(): void
    {
        $this->validate([
            // Identité
            'shop_name'             => 'required|string|max:255',
            'login_welcome_message' => 'nullable|string|max:500',
            'company_tagline'       => 'nullable|string|max:255',
            'company_website'       => 'nullable|url|max:255',

            // Coordonnées
            'company_email'        => 'nullable|email|max:255',
            'company_phone'        => 'nullable|string|max:30',
            'company_phone2'       => 'nullable|string|max:30',
            'company_address'      => 'nullable|string|max:500',
            'company_city'         => 'nullable|string|max:100',
            'company_country'      => 'nullable|string|max:100',
            'company_postal_code'  => 'nullable|string|max:20',
            'company_registration' => 'nullable|string|max:100',
            'company_tax_id'       => 'nullable|string|max:100',

            // Finance
            'currency'       => 'required|string|max:10',
            'tax_rate'       => 'required|numeric|min:0|max:100',
            'invoice_prefix'      => 'required|string|max:10',
            'proforma_prefix'     => 'required|string|max:10',
            'credit_note_prefix'  => 'required|string|max:10',
            'quote_prefix'        => 'required|string|max:10',
            'payment_terms'  => 'required|integer|min:0|max:365',
            'bank_name'      => 'nullable|string|max:255',
            'bank_account'   => 'nullable|string|max:100',
            'bank_iban'      => 'nullable|string|max:100',
            'invoice_footer' => 'nullable|string|max:1000',

            // Régionalisation
            'timezone'    => 'required|string|max:60',
            'date_format' => 'required|string|max:20',
            'locale'      => 'required|in:fr,en',
        ]);

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            return;
        }

        $settings = [
            // Identité
            'shop_name'             => $this->shop_name,
            'login_welcome_message' => $this->login_welcome_message,
            'company_tagline'       => $this->company_tagline,
            'company_website'       => $this->company_website,

            // Coordonnées
            'company_email'        => $this->company_email,
            'company_phone'        => $this->company_phone,
            'company_phone2'       => $this->company_phone2,
            'company_address'      => $this->company_address,
            'company_city'         => $this->company_city,
            'company_country'      => $this->company_country,
            'company_postal_code'  => $this->company_postal_code,
            'company_registration' => $this->company_registration,
            'company_tax_id'       => $this->company_tax_id,

            // Finance
            'currency'       => $this->currency,
            'tax_rate'       => $this->tax_rate,
            'invoice_prefix'     => $this->invoice_prefix,
            'proforma_prefix'    => $this->proforma_prefix,
            'credit_note_prefix' => $this->credit_note_prefix,
            'quote_prefix'       => $this->quote_prefix,
            'payment_terms'  => $this->payment_terms,
            'bank_name'      => $this->bank_name,
            'bank_account'   => $this->bank_account,
            'bank_iban'      => $this->bank_iban,
            'invoice_footer' => $this->invoice_footer,

            // Régionalisation
            'timezone'    => $this->timezone,
            'date_format' => $this->date_format,
            'locale'      => $this->locale,
        ];

        foreach ($settings as $key => $value) {
            $tenant->setSetting($key, $value);
        }

        $this->dispatch('branding-updated', shopName: $this->shop_name);

        notify()->success(__('Configuration enregistrée avec succès.'));
    }

    public function render()
    {
        return view('inovcom-branding::livewire.branding.index')
            ->layout('layouts.app', [
                'title'    => __('Configuration'),
                'subtitle' => __('Identité, coordonnées, finance et régionalisation'),
            ]);
    }
}
