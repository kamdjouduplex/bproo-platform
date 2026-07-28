<?php

namespace InovCom\Configuration\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\TenantSettingsApplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ConfigurationIndex extends Component
{
    use WithFileUploads;

    public $logoUpload;

    public $iconUpload;

    public ?string $logoPreviewUrl = null;

    public ?string $iconPreviewUrl = null;
    public string $currency = 'XOF';
    public string $locale = 'fr';
    public string $timezone = 'Africa/Douala';
    public string $tax_rate = '0';
    public string $invoice_prefix = 'INV';
    public string $invoice_prefix_declared = 'FTH';
    public string $invoice_prefix_non_declared = 'FTN';
    public string $shop_tagline = '';
    public string $shop_rccm = '';
    public string $payment_modes_default = 'chèque/virement/espèces';
    public string $shop_name = '';
    public string $login_welcome_message = '';

    // Invoice / receipt header and footer (shown on printed documents)
    public string $shop_phone = '';
    public string $shop_address = '';
    public string $shop_bp = '';
    public string $shop_email = '';
    public string $shop_tax_id = '';
    public string $shop_cnps = '';
    public string $shop_website = '';
    public string $invoice_footer = '';
    public bool $print_show_header_company_info = true;
    public string $collection_reminder_body = '';
    public string $shop_bank_details = '';
    public string $document_line_increment = '10';
    public array $stores = [];
    public ?int $editingStoreId = null;
    public string $store_name = '';
    public string $store_code = '';
    public array $users = [];
    public array $userStoreAssignments = [];
    public array $selectedUserIds = [];
    public ?int $bulk_store_id = null;

    public function mount(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            $this->currency = (string) $tenant->getSetting('currency', 'XOF');
            $this->locale = (string) $tenant->getSetting('locale', 'fr');
            $this->timezone = (string) $tenant->getSetting('timezone', config('inovcom.default_timezone', 'Africa/Douala'));
            $this->tax_rate = (string) $tenant->getSetting('tax_rate', '0');
            $this->invoice_prefix = (string) $tenant->getSetting('invoice_prefix', 'INV');
            $this->invoice_prefix_declared = (string) $tenant->getSetting('invoice_prefix_declared', 'FTH');
            $this->invoice_prefix_non_declared = (string) $tenant->getSetting('invoice_prefix_non_declared', 'FTN');
            $this->shop_tagline = (string) $tenant->getSetting('shop_tagline', '');
            $this->shop_rccm = (string) $tenant->getSetting('shop_rccm', '');
            $this->payment_modes_default = (string) $tenant->getSetting('payment_modes_default', 'chèque/virement/espèces');
            $this->shop_name = (string) $tenant->getSetting('shop_name', $tenant->name ?? '');
            $this->login_welcome_message = (string) $tenant->getSetting('login_welcome_message', 'Bienvenue dans votre espace de gestion');
            $this->shop_phone = (string) $tenant->getSetting('shop_phone', '');
            $this->shop_address = (string) $tenant->getSetting('shop_address', '');
            $this->shop_bp = (string) $tenant->getSetting('shop_bp', '');
            $this->shop_email = (string) $tenant->getSetting('shop_email', '');
            $this->shop_tax_id = (string) $tenant->getSetting('shop_tax_id', '');
            $this->shop_cnps = (string) $tenant->getSetting('shop_cnps', '');
            $this->shop_website = (string) $tenant->getSetting('shop_website', '');
            $this->invoice_footer = (string) $tenant->getSetting('invoice_footer', '');
            $this->print_show_header_company_info = filter_var(
                $tenant->getSetting('print_show_header_company_info', true),
                FILTER_VALIDATE_BOOLEAN
            );
            $this->collection_reminder_body = (string) $tenant->getSetting('collection_reminder_body', $this->defaultCollectionReminderBody());
            $this->shop_bank_details = (string) $tenant->getSetting('shop_bank_details', '');
            $this->document_line_increment = (string) $tenant->getSetting('document_line_increment', '10');
            $branding = app(TenantBrandingService::class);
            $this->logoPreviewUrl = $branding->url($tenant, TenantBrandingService::LOGO_MAIN);
            $this->iconPreviewUrl = $branding->url($tenant, TenantBrandingService::LOGO_ICON);
            $this->loadStores();
            $this->loadUsers();
        }
    }

    public function uploadLogo(): void
    {
        $maxKb = $this->uploadMaxKilobytes();
        $this->validate(
            [
                'logoUpload' => ['required', 'image', "max:{$maxKb}", 'mimes:png,jpg,jpeg,webp'],
            ],
            [
                'logoUpload.required' => 'Choisissez une image pour le logo.',
                'logoUpload.image' => 'Le fichier doit être une image (PNG, JPG ou WebP).',
                'logoUpload.max' => "L'image ne doit pas dépasser {$maxKb} Ko (limite serveur).",
                'logoUpload.mimes' => 'Formats acceptés : PNG, JPG, WebP.',
                'logoUpload.uploaded' => "Échec du transfert. Réduisez la taille (max. {$maxKb} Ko) ou réessayez.",
            ],
            ['logoUpload' => 'logo']
        );

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            session()->flash('error', 'Tenant non disponible.');
            return;
        }

        app(TenantBrandingService::class)->store($tenant, $this->logoUpload, TenantBrandingService::LOGO_MAIN);
        $this->logoPreviewUrl = app(TenantBrandingService::class)->url($tenant, TenantBrandingService::LOGO_MAIN);
        $this->logoUpload = null;
        $this->dispatch('configuration-updated', shopName: $this->shop_name);
        session()->flash('success', 'Logo principal enregistré.');
    }

    public function uploadIcon(): void
    {
        $maxKb = min($this->uploadMaxKilobytes(), 2048);
        $this->validate(
            [
                'iconUpload' => ['required', 'image', "max:{$maxKb}", 'mimes:png,jpg,jpeg,webp'],
            ],
            [
                'iconUpload.required' => 'Choisissez une image pour l\'icône.',
                'iconUpload.image' => 'Le fichier doit être une image (PNG, JPG ou WebP).',
                'iconUpload.max' => "L'image ne doit pas dépasser {$maxKb} Ko (limite serveur).",
                'iconUpload.mimes' => 'Formats acceptés : PNG, JPG, WebP.',
                'iconUpload.uploaded' => "Échec du transfert. Réduisez la taille (max. {$maxKb} Ko) ou réessayez.",
            ],
            ['iconUpload' => 'icône']
        );

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            session()->flash('error', 'Tenant non disponible.');
            return;
        }

        app(TenantBrandingService::class)->store($tenant, $this->iconUpload, TenantBrandingService::LOGO_ICON);
        $this->iconPreviewUrl = app(TenantBrandingService::class)->url($tenant, TenantBrandingService::LOGO_ICON);
        $this->iconUpload = null;
        $this->dispatch('configuration-updated', shopName: $this->shop_name);
        session()->flash('success', 'Icône enregistrée.');
    }

    public function removeLogo(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        app(TenantBrandingService::class)->delete($tenant, TenantBrandingService::LOGO_MAIN);
        $this->logoPreviewUrl = null;
        $this->logoUpload = null;
        session()->flash('success', 'Logo principal supprimé.');
    }

    public function removeIcon(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        app(TenantBrandingService::class)->delete($tenant, TenantBrandingService::LOGO_ICON);
        $this->iconPreviewUrl = null;
        $this->iconUpload = null;
        session()->flash('success', 'Icône supprimée.');
    }

    public function save(): void
    {
        $data = $this->validate([
            'currency' => 'required|string|max:10',
            'locale' => 'required|string|max:10',
            'timezone' => 'required|timezone:all',
            'tax_rate' => 'required|numeric|min:0',
            'invoice_prefix' => 'required|string|max:20',
            'invoice_prefix_declared' => 'required|string|max:10',
            'invoice_prefix_non_declared' => 'required|string|max:10',
            'shop_tagline' => 'nullable|string|max:255',
            'shop_rccm' => 'nullable|string|max:64',
            'payment_modes_default' => 'nullable|string|max:120',
            'shop_name' => 'nullable|string|max:128',
            'login_welcome_message' => 'required|string|max:500',
            'shop_phone' => 'nullable|string|max:64',
            'shop_address' => 'nullable|string|max:255',
            'shop_bp' => 'nullable|string|max:128',
            'shop_email' => 'nullable|string|max:128',
            'shop_tax_id' => 'nullable|string|max:64',
            'shop_cnps' => 'nullable|string|max:64',
            'shop_website' => 'nullable|string|max:128',
            'invoice_footer' => 'nullable|string|max:500',
            'print_show_header_company_info' => 'boolean',
            'collection_reminder_body' => 'nullable|string|max:2000',
            'shop_bank_details' => 'nullable|string|max:1000',
            'document_line_increment' => 'required|integer|min:1|max:1000',
        ]);

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            session()->flash('error', 'Tenant non disponible.');
            return;
        }

        $tenant->setSetting('currency', $data['currency']);
        $tenant->setSetting('locale', $data['locale']);
        $tenant->setSetting('timezone', $data['timezone']);
        $tenant->setSetting('tax_rate', $data['tax_rate']);
        $tenant->setSetting('invoice_prefix', $data['invoice_prefix']);
        $tenant->setSetting('invoice_prefix_declared', strtoupper($data['invoice_prefix_declared']));
        $tenant->setSetting('invoice_prefix_non_declared', strtoupper($data['invoice_prefix_non_declared']));
        $tenant->setSetting('shop_tagline', $data['shop_tagline'] ?? '');
        $tenant->setSetting('shop_rccm', $data['shop_rccm'] ?? '');
        $tenant->setSetting('payment_modes_default', $data['payment_modes_default'] ?? 'chèque/virement/espèces');
        $tenant->setSetting('shop_name', $data['shop_name'] ?? $tenant->name);
        $tenant->setSetting('login_welcome_message', $data['login_welcome_message']);
        $tenant->setSetting('shop_phone', $data['shop_phone'] ?? '');
        $tenant->setSetting('shop_address', $data['shop_address'] ?? '');
        $tenant->setSetting('shop_bp', $data['shop_bp'] ?? '');
        $tenant->setSetting('shop_email', $data['shop_email'] ?? '');
        $tenant->setSetting('shop_tax_id', $data['shop_tax_id'] ?? '');
        $tenant->setSetting('shop_cnps', $data['shop_cnps'] ?? '');
        $tenant->setSetting('shop_website', $data['shop_website'] ?? '');
        $tenant->setSetting('invoice_footer', $data['invoice_footer'] ?? '');
        $tenant->setSetting('print_show_header_company_info', (bool) ($data['print_show_header_company_info'] ?? true));
        $tenant->setSetting('collection_reminder_body', $data['collection_reminder_body'] ?? '');
        $tenant->setSetting('shop_bank_details', $data['shop_bank_details'] ?? '');
        $tenant->setSetting('document_line_increment', (int) $data['document_line_increment']);

        // Applique immédiatement locale / devise / fuseau pour ce tenant uniquement.
        TenantSettingsApplier::apply($tenant->fresh());

        $this->dispatch('configuration-updated', shopName: $data['shop_name'] ?? $tenant->name);
        notify()->success('Configuration enregistrée avec succès.');
    }

    public function saveStore(): void
    {
        if (!$this->canManageStores()) {
            notify()->error('Permission refusée.');
            return;
        }

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant || !$tenant->multi_store_enabled) {
            notify()->error('Multi-magasins non activé.');
            return;
        }

        if (!Schema::connection('tenant')->hasTable('stores')) {
            notify()->error('Table des boutiques indisponible.');
            return;
        }

        $data = $this->validate([
            'store_name' => 'required|string|max:255',
            'store_code' => 'nullable|string|max:50',
        ]);

        $code = trim($data['store_code']) !== ''
            ? strtoupper(trim($data['store_code']))
            : strtoupper(Str::limit(Str::slug($data['store_name'], ''), 12, ''));
        if ($code === '') {
            $code = 'STORE' . now()->format('His');
        }

        $query = DB::connection('tenant')->table('stores')->where('code', $code);
        if ($this->editingStoreId) {
            $query->where('id', '!=', $this->editingStoreId);
        }
        if ($query->exists()) {
            notify()->error('Code boutique déjà utilisé.');
            return;
        }

        if ($this->editingStoreId) {
            DB::connection('tenant')->table('stores')
                ->where('id', $this->editingStoreId)
                ->update([
                    'name' => $data['store_name'],
                    'code' => $code,
                    'updated_at' => now(),
                ]);
            notify()->success('Boutique mise à jour.');
        } else {
            $isFirst = DB::connection('tenant')->table('stores')->count() === 0;
            $storeId = DB::connection('tenant')->table('stores')->insertGetId([
                'name' => $data['store_name'],
                'code' => $code,
                'is_default' => $isFirst,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($isFirst) {
                $tenant->setSetting('default_store_id', (string) $storeId);
            }
            notify()->success('Boutique créée.');
        }

        $this->resetStoreForm();
        $this->loadStores();
        $this->loadUsers();
    }

    public function editStore(int $storeId): void
    {
        $store = DB::connection('tenant')->table('stores')->where('id', $storeId)->first();
        if (!$store) {
            return;
        }
        $this->editingStoreId = (int) $store->id;
        $this->store_name = (string) $store->name;
        $this->store_code = (string) $store->code;
    }

    public function resetStoreForm(): void
    {
        $this->editingStoreId = null;
        $this->store_name = '';
        $this->store_code = '';
    }

    public function setDefaultStore(int $storeId): void
    {
        if (!$this->canManageStores()) {
            notify()->error('Permission refusée.');
            return;
        }
        DB::connection('tenant')->table('stores')->update(['is_default' => false, 'updated_at' => now()]);
        DB::connection('tenant')->table('stores')->where('id', $storeId)->update(['is_default' => true, 'updated_at' => now()]);
        $tenant = app(TenantManager::class)->tenant();
        $tenant?->setSetting('default_store_id', (string) $storeId);
        notify()->success('Boutique par défaut mise à jour.');
        $this->loadStores();
    }

    public function toggleStoreActive(int $storeId): void
    {
        if (!$this->canManageStores()) {
            notify()->error('Permission refusée.');
            return;
        }
        $store = DB::connection('tenant')->table('stores')->where('id', $storeId)->first();
        if (!$store) {
            return;
        }

        $newState = !(bool) $store->is_active;
        if (!$newState) {
            $activeCount = DB::connection('tenant')->table('stores')->where('is_active', true)->count();
            if ($activeCount <= 1) {
                notify()->error('Impossible de désactiver la dernière boutique active.');
                return;
            }
            if ((bool) $store->is_default) {
                notify()->error('Impossible de désactiver la boutique par défaut.');
                return;
            }
        }

        DB::connection('tenant')->table('stores')
            ->where('id', $storeId)
            ->update(['is_active' => $newState, 'updated_at' => now()]);
        notify()->success($newState ? 'Boutique activée.' : 'Boutique désactivée.');
        $this->loadStores();
        $this->loadUsers();
    }

    public function updateUserStore(int $userId): void
    {
        if (!$this->canManageStores()) {
            notify()->error('Permission refusée.');
            return;
        }
        if (!Schema::connection('tenant')->hasTable('users') || !Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
            notify()->error('Affectation des utilisateurs indisponible.');
            return;
        }

        $storeId = $this->userStoreAssignments[$userId] ?? null;
        if (!$storeId) {
            notify()->error('Veuillez choisir une boutique.');
            return;
        }

        DB::connection('tenant')->table('users')->where('id', $userId)->update([
            'assigned_store_id' => (int) $storeId,
            'updated_at' => now(),
        ]);
        notify()->success('Boutique utilisateur mise à jour.');
        $this->loadUsers();
    }

    public function applyBulkStoreAssignment(): void
    {
        if (!$this->canManageStores()) {
            notify()->error('Permission refusée.');
            return;
        }

        $selectedIds = array_values(array_filter(array_map('intval', $this->selectedUserIds)));
        if (empty($selectedIds)) {
            notify()->error('Sélectionnez au moins un utilisateur.');
            return;
        }
        if (!$this->bulk_store_id) {
            notify()->error('Choisissez une boutique pour l\'affectation en masse.');
            return;
        }

        DB::connection('tenant')->table('users')
            ->whereIn('id', $selectedIds)
            ->update([
                'assigned_store_id' => (int) $this->bulk_store_id,
                'updated_at' => now(),
            ]);

        $this->selectedUserIds = [];
        notify()->success('Affectation en masse terminée.');
        $this->loadUsers();
    }

    private function loadStores(): void
    {
        if (!Schema::connection('tenant')->hasTable('stores')) {
            $this->stores = [];
            return;
        }
        $this->stores = DB::connection('tenant')
            ->table('stores')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'code' => (string) $s->code,
                'is_default' => (bool) $s->is_default,
                'is_active' => (bool) $s->is_active,
            ])->all();
    }

    private function loadUsers(): void
    {
        if (!Schema::connection('tenant')->hasTable('users')) {
            $this->users = [];
            $this->userStoreAssignments = [];
            return;
        }

        $this->users = DB::connection('tenant')
            ->table('users')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'assigned_store_id'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) $u->email,
                'assigned_store_id' => $u->assigned_store_id ? (int) $u->assigned_store_id : null,
            ])->all();

        $this->userStoreAssignments = [];
        foreach ($this->users as $user) {
            $this->userStoreAssignments[$user['id']] = $user['assigned_store_id'];
        }
    }

    private function canManageStores(): bool
    {
        $user = auth('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && ($user->hasPermission('configuration.edit') || $user->hasPermission('stores.view_all'));
    }

    private function defaultCollectionReminderBody(): string
    {
        return "Nous constatons que les factures ci-dessous demeurent impayées malgré l'échéance contractuelle. "
            . "Nous vous remercions de bien vouloir procéder à leur règlement dans les meilleurs délais. "
            . "Pour toute information complémentaire, merci de contacter notre service comptable.";
    }

    private function uploadMaxKilobytes(): int
    {
        $bytes = min(
            $this->parseIniSize((string) ini_get('upload_max_filesize')),
            $this->parseIniSize((string) ini_get('post_max_size'))
        );

        return max(1, (int) floor($bytes / 1024));
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function render()
    {
        return view('inovcom-configuration::livewire.configuration.index')
            ->layout('layouts.app', [
                'title' => 'Paramètres système',
                'subtitle' => 'Configuration du point de vente',
            ])
            ->with([
                'uploadMaxKb' => $this->uploadMaxKilobytes(),
            ]);
    }
}
