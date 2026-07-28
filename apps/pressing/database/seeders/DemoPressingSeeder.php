<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InovCom\Items\Models\Item;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use Pressing\Support\PressingRolePermissions;
use Pressing\Models\Agence;
use Pressing\Models\ArticlePrice;
use Pressing\Models\ArticleType;
use Pressing\Models\OrderStageHistory;
use Pressing\Models\PressingClient;
use Pressing\Models\PressingConsumableIssue;
use Pressing\Models\PressingConsumableIssueLine;
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingLoyaltyEntry;
use Pressing\Models\PressingLoyaltyReward;
use Pressing\Models\PressingNotification;
use Pressing\Models\PressingNotificationLog;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingOrderConstitutionLine;
use Pressing\Models\PressingOrderItem;
use Pressing\Models\PressingOrderPiece;
use Pressing\Models\PressingPayment;
use Pressing\Models\WorkflowStage;
use Pressing\Services\PressingConsumablesService;
use Pressing\Support\PressingBilling;
use Pressing\Support\PressingSettings;
use Pressing\Support\PressingWorkflow;

/**
 * Rich current-month demo dataset for pressing client demos (EN + FR).
 * Idempotent when re-run with --fresh (wipes DEMO-* rows first).
 */
class DemoPressingSeeder extends Seeder
{
    public bool $fresh = true;

    private Carbon $monthStart;

    private Carbon $today;

    private array $stages = [];

    private array $types = [];

    private array $prices = [];

    private array $staff = [];

    private array $agences = [];

    private array $clients = [];

    private array $stats = [
        'agences' => 0,
        'staff' => 0,
        'clients' => 0,
        'orders' => 0,
        'payments' => 0,
        'deliveries' => 0,
        'atelier_issues' => 0,
        'livraison_issues' => 0,
        'loyalty_entries' => 0,
        'loyalty_rewards' => 0,
        'notifications' => 0,
    ];

    public function run(): void
    {
        $this->today = Carbon::create(2026, 7, 24, 12, 0, 0);
        $this->monthStart = $this->today->copy()->startOfMonth();
        Carbon::setTestNow($this->today);

        if ($this->fresh) {
            $this->wipeDemoData();
        }

        $this->configureSettings();
        $this->ensureCatalogAndStages();
        $this->seedAgences();
        $this->seedStaff();
        $this->seedClients();
        $this->seedConsumableStock();
        $this->seedOrdersPipeline();
        $this->seedLoyaltyShowcase();
        $this->seedNotifications();

        Carbon::setTestNow();

        $this->printSummary();
    }

    private function wipeDemoData(): void
    {
        $this->command?->warn('Removing previous DEMO-* data…');

        $demoOrderIds = PressingOrder::withTrashed()
            ->where('number', 'like', 'CMD-DEMO-%')
            ->pluck('id');

        if ($demoOrderIds->isNotEmpty()) {
            PressingConsumableIssueLine::query()
                ->whereIn('issue_id', PressingConsumableIssue::whereIn('order_id', $demoOrderIds)->pluck('id'))
                ->delete();
            PressingConsumableIssue::whereIn('order_id', $demoOrderIds)->delete();
            PressingLoyaltyReward::whereIn('order_id', $demoOrderIds)->delete();
            PressingLoyaltyEntry::whereIn('order_id', $demoOrderIds)->delete();
            PressingNotificationLog::whereIn('order_id', $demoOrderIds)->delete();
            PressingNotification::whereIn('order_id', $demoOrderIds)->delete();
            PressingDelivery::whereIn('order_id', $demoOrderIds)->delete();
            PressingPayment::whereIn('order_id', $demoOrderIds)->delete();
            OrderStageHistory::whereIn('order_id', $demoOrderIds)->delete();
            PressingOrderPiece::whereIn('order_id', $demoOrderIds)->delete();
            PressingOrderConstitutionLine::whereIn('order_id', $demoOrderIds)->delete();
            PressingOrderItem::whereIn('order_id', $demoOrderIds)->delete();
            PressingOrder::withTrashed()->whereIn('id', $demoOrderIds)->forceDelete();
        }

        PressingLoyaltyReward::where('code', 'like', 'LOY-DEMO-%')->delete();
        PressingLoyaltyEntry::where('reason', 'like', '%[DEMO]%')->delete();
        PressingConsumableIssue::where('number', 'like', 'ATS-DEMO-%')
            ->orWhere('number', 'like', 'LIV-DEMO-%')
            ->get()
            ->each(function (PressingConsumableIssue $issue) {
                $issue->lines()->delete();
                $issue->delete();
            });

        PressingClient::withTrashed()->where('code', 'like', 'CL-DEMO-%')->forceDelete();
        User::on('tenant')->where('email', 'like', '%@demo.pressing.local')->delete();
        Agence::where('code', 'like', 'AG-DEMO-%')->delete();
    }

    private function configureSettings(): void
    {
        PressingSettings::seedDefaults();

        PressingSettings::set(PressingSettings::KEY_LOYALTY_ACTIVE, true);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_POINTS_PER_ORDER, 1);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_AMOUNT_PER_POINT, 2000);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_THRESHOLD, 10);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_REWARD_TYPE, 'value');
        PressingSettings::set(PressingSettings::KEY_LOYALTY_REWARD_VALUE, 2000);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_REWARD_MAX, '');
        PressingSettings::set(PressingSettings::KEY_LOYALTY_EXPIRY_DAYS, 90);

        PressingSettings::set(PressingSettings::KEY_TAX_ENABLED, false);
        PressingSettings::set(PressingSettings::KEY_DEFAULT_DELAY_HOURS, 48);
        PressingSettings::set(PressingSettings::KEY_BILLING_DEFAULT_MODE, PressingBilling::MODE_MIXED);
        PressingSettings::set(PressingSettings::KEY_WEIGHT_PRICE_GLOBAL, 1500);

        PressingSettings::set(PressingSettings::KEY_NOTIF_ENABLED, true);
        PressingSettings::set(PressingSettings::KEY_NOTIF_IN_APP, true);
        PressingSettings::set(PressingSettings::KEY_NOTIF_WHATSAPP, true);
        PressingSettings::set(PressingSettings::KEY_NOTIF_SMS, false);
        PressingSettings::set(PressingSettings::KEY_NOTIF_EMAIL, true);
    }

    private function ensureCatalogAndStages(): void
    {
        $priceMap = [
            'Chemise' => [1500, 1200],
            'Costume' => [5000, 2500],
            'Pantalon' => [2000, 1500],
            'Robe' => [3500, 2000],
            'Boubou' => [4000, 2200],
            'Culotte' => [800, 800],
            'Rideaux' => [6000, 2500],
            'Couverture' => [4500, 2000],
            'Tapis' => [10000, 3000],
            'Chaussures' => [2500, 0],
        ];

        foreach ($priceMap as $name => [$fixed, $perKg]) {
            $type = ArticleType::firstOrCreate(
                ['name' => $name],
                [
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6)) ?: 'ART',
                    'sort_order' => count($this->types) + 1,
                    'is_active' => true,
                    'pricing_mode' => $perKg > 0 ? 'mixed' : 'fixed',
                ]
            );
            $this->types[$name] = $type;

            ArticlePrice::updateOrCreate(
                ['article_type_id' => $type->id, 'agence_id' => null],
                [
                    'amount' => $fixed,
                    'price_per_kg' => $perKg,
                    'pricing_mode' => $perKg > 0 ? 'mixed' : 'fixed',
                    'currency' => 'XAF',
                    'is_active' => true,
                ]
            );
            $this->prices[$name] = ['fixed' => $fixed, 'per_kg' => $perKg];
        }

        foreach ([
            PressingWorkflow::STAGE_TRI,
            PressingWorkflow::STAGE_MISE_EN_PRODUCTION,
            PressingWorkflow::STAGE_LAVAGE,
            PressingWorkflow::STAGE_SECHAGE,
            PressingWorkflow::STAGE_REPASSAGE,
            PressingWorkflow::STAGE_FIN_PRODUCTION,
            PressingWorkflow::STAGE_PRET,
            PressingWorkflow::STAGE_LIVRE,
        ] as $name) {
            $stage = PressingWorkflow::stageByName($name)
                ?? WorkflowStage::where('name', $name)->first();
            if ($stage) {
                $this->stages[$name] = $stage;
            }
        }

        app(PressingConsumablesService::class)->seedCatalog();
    }

    private function seedAgences(): void
    {
        $akwa = Agence::firstOrCreate(
            ['code' => 'AG-001'],
            [
                'name' => 'Agence Akwa',
                'country' => 'Cameroun',
                'city' => 'Douala',
                'location' => 'Boulevard de la Liberté, Akwa',
                'phone' => '+237 233 42 15 80',
                'email' => 'akwa@pressing-excellence.com',
                'is_active' => true,
            ]
        );
        $akwa->fill([
            'name' => 'Agence Akwa',
            'city' => 'Douala',
            'location' => 'Boulevard de la Liberté, Akwa',
            'phone' => '+237 233 42 15 80',
            'email' => 'akwa@pressing-excellence.com',
            'is_active' => true,
        ])->save();
        $this->agences['akwa'] = $akwa;

        $bonanjo = Agence::updateOrCreate(
            ['code' => 'AG-DEMO-002'],
            [
                'name' => 'Agence Bonanjo',
                'country' => 'Cameroun',
                'city' => 'Douala',
                'location' => 'Rue Joss, face Chambre de Commerce',
                'phone' => '+237 233 42 16 90',
                'email' => 'bonanjo@pressing-excellence.com',
                'is_active' => true,
            ]
        );
        $this->agences['bonanjo'] = $bonanjo;
        $this->stats['agences'] = 2;
    }

    private function seedStaff(): void
    {
        $admin = User::on('tenant')->where('email', 'admin@pressing.com')->first()
            ?? User::on('tenant')->orderBy('id')->first();

        $adminRole = Role::on('tenant')->where('name', 'admin')->first();

        $receptionRole = Role::on('tenant')->firstOrCreate(
            ['name' => 'pressing_reception'],
            ['description' => 'Réception / caisse pressing']
        );
        $productionRole = Role::on('tenant')->firstOrCreate(
            ['name' => 'pressing_production'],
            ['description' => 'Atelier / production pressing']
        );
        $driverRole = Role::on('tenant')->firstOrCreate(
            ['name' => 'pressing_driver'],
            ['description' => 'Livreur pressing']
        );

        // Keep legacy + French UI role names in sync with least-privilege matrix
        Role::on('tenant')->firstOrCreate(['name' => 'cashier'], ['description' => 'Caisse / réception']);
        Role::on('tenant')->firstOrCreate(['name' => 'Réception'], ['description' => 'Réception pressing']);
        Role::on('tenant')->firstOrCreate(['name' => 'Production'], ['description' => 'Production pressing']);
        Role::on('tenant')->firstOrCreate(['name' => 'Repassage'], ['description' => 'Repassage / fin de production']);
        Role::on('tenant')->firstOrCreate(['name' => 'Livreur'], ['description' => 'Livreur pressing']);

        PressingRolePermissions::syncAll();

        $definitions = [
            'receptionist' => [
                'name' => 'Amina Ngozi',
                'email' => 'amina.ngozi@demo.pressing.local',
                'agence' => 'akwa',
                'role' => $receptionRole,
            ],
            'receptionist2' => [
                'name' => 'Paul Essomba',
                'email' => 'paul.essomba@demo.pressing.local',
                'agence' => 'bonanjo',
                'role' => $receptionRole,
            ],
            'production' => [
                'name' => 'Jean Mbarga',
                'email' => 'jean.mbarga@demo.pressing.local',
                'agence' => 'akwa',
                'role' => $productionRole,
            ],
            'presser' => [
                'name' => 'Fatou Diallo',
                'email' => 'fatou.diallo@demo.pressing.local',
                'agence' => 'akwa',
                'role' => $productionRole,
            ],
            'driver' => [
                'name' => 'Eric Fokou',
                'email' => 'eric.fokou@demo.pressing.local',
                'agence' => 'akwa',
                'role' => $driverRole,
            ],
        ];

        foreach ($definitions as $key => $def) {
            $user = User::on('tenant')->updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => Hash::make('Pressing2026!'),
                    'is_active' => true,
                    'assigned_agence_id' => $this->agences[$def['agence']]->id,
                ]
            );
            if ($def['role']) {
                // Replace roles so profile detection is clean (no leftover cashier on drivers)
                $user->roles()->sync([$def['role']->id]);
            }
            $this->staff[$key] = $user;
            $this->stats['staff']++;
        }

        $this->staff['admin'] = $admin;
        if ($admin && $adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
            $this->agences['akwa']->update(['manager_user_id' => $admin->id]);
            $this->agences['bonanjo']->update(['manager_user_id' => $this->staff['receptionist2']->id]);
        }

        Auth::guard('tenant')->login($this->staff['admin']);
    }

    private function seedClients(): void
    {
        $profiles = [
            // VIP / high volume
            ['CL-DEMO-001', 'akwa', 'Ngono', 'Clarisse', '690112233', 'clarisse.ngono@email.com', 'Bonapriso, Rue des Palmiers', 'VIP — hotel partner', 18, 4],
            ['CL-DEMO-002', 'akwa', 'Fotso', 'Michel', '677445566', 'michel.fotso@corp.cm', 'Akwa, Immeuble Saker', 'Corporate account', 14, 2],
            ['CL-DEMO-003', 'bonanjo', 'Owona', 'Sandrine', '655998877', null, 'Bonanjo, près du port', 'Weekly customer', 12, 1],
            // Regulars
            ['CL-DEMO-004', 'akwa', 'Kamga', 'Eric', '699001122', 'eric.kamga@gmail.com', 'Makepe, Carrefour Ndokoti', null, 8, 0],
            ['CL-DEMO-005', 'akwa', 'Bella', 'Grace', '670334455', null, 'Deido, Marché central', 'Sensitive fabrics', 7, 1],
            ['CL-DEMO-006', 'bonanjo', 'Tchoumi', 'Alain', '682556677', 'alain.t@yahoo.fr', 'Bali, face école publique', null, 6, 0],
            ['CL-DEMO-007', 'akwa', 'Nana', 'Patricia', '691778899', null, 'Logbaba', 'Prefers WhatsApp', 5, 0],
            ['CL-DEMO-008', 'bonanjo', 'Mbarga', 'Joseph', '698223344', 'j.mbarga@outlook.com', 'New Bell', null, 4, 0],
            // New / light activity
            ['CL-DEMO-009', 'akwa', 'Etoa', 'Marie-Claire', '673445566', null, 'PK 14', 'First visits this month', 2, 0],
            ['CL-DEMO-010', 'akwa', 'Biya', 'Thomas', '656667788', 'thomas.biya@mail.com', 'Bépanda', null, 3, 0],
            ['CL-DEMO-011', 'bonanjo', 'Ateba', 'Julie', '681990011', null, 'Akwa Nord', null, 1, 0],
            ['CL-DEMO-012', 'akwa', 'Fouda', 'Rodrigue', '694112233', null, 'Yassa', 'Wedding package', 9, 0],
            ['CL-DEMO-013', 'akwa', 'Simo', 'Hélène', '677889900', 'helene.simo@email.cm', 'Bonamoussadi', null, 5, 0],
            ['CL-DEMO-014', 'bonanjo', 'Nguema', 'Pierre', '699334455', null, 'Douala Port', 'Restaurant linen', 11, 0],
            ['CL-DEMO-015', 'akwa', 'Kenfack', 'Danielle', '655667788', null, 'Cité SIC', null, 3, 0],
            ['CL-DEMO-016', 'akwa', 'Moussa', 'Ibrahim', '670998877', 'ibrahim.m@mail.com', 'Ndokoti', null, 4, 0],
            ['CL-DEMO-017', 'bonanjo', 'Tchamba', 'Rose', '682112244', null, 'New Deido', null, 2, 0],
            ['CL-DEMO-018', 'akwa', 'Onana', 'Serge', '691556677', null, 'Makepe Missoke', 'Express service', 6, 0],
            ['CL-DEMO-019', 'akwa', 'Abega', 'Christelle', '677001122', 'c.abega@gmail.com', 'Bonanjo Centre', null, 8, 1],
            ['CL-DEMO-020', 'bonanjo', 'Essomba', 'David', '698445566', null, 'Akwa Maritime', null, 3, 0],
            ['CL-DEMO-021', 'akwa', 'Ngo', 'Vanessa', '655223344', null, 'Logbessou', null, 1, 0],
            ['CL-DEMO-022', 'akwa', 'Kuete', 'Francis', '670556677', 'f.kuete@corp.cm', 'Bonapriso Suites', 'Hotel linen weekly', 15, 0],
        ];

        foreach ($profiles as [$code, $agenceKey, $last, $first, $wa, $email, $address, $notes, $points, $ordersCount]) {
            $client = PressingClient::updateOrCreate(
                ['code' => $code],
                [
                    'agence_id' => $this->agences[$agenceKey]->id,
                    'last_name' => $last,
                    'first_name' => $first,
                    'whatsapp' => $wa,
                    'phone' => $wa,
                    'email' => $email,
                    'address' => $address,
                    'notes' => $notes,
                    'is_active' => true,
                    'loyalty_points' => $points,
                    'loyalty_orders_count' => max(1, (int) floor($points / 2)),
                ]
            );
            $this->clients[$code] = $client;
            $this->stats['clients']++;
        }
    }

    private function seedConsumableStock(): void
    {
        $service = app(PressingConsumablesService::class);
        $qty = [
            'CONS-LESSIVE' => 80,
            'CONS-SAVON' => 120,
            'CONS-PARFUM' => 40,
            'CONS-CINTRES' => 400,
            'CONS-EMBALLAGES' => 250,
            'CONS-ETIQUETTES' => 800,
        ];

        foreach ($qty as $sku => $amount) {
            $item = Item::where('sku', $sku)->first();
            if (! $item) {
                continue;
            }
            $level = $service->resolveStockLevel($item->id);
            if ((float) $level->quantity < $amount) {
                $service->restock($item->id, $amount - (float) $level->quantity, 'Demo stock refill [DEMO]');
            }
        }
    }

    private function seedOrdersPipeline(): void
    {
        $seq = 1;

        // —— DELIVERED (many, spread across the month) ——
        foreach ($this->deliveredSpecs() as $spec) {
            $this->createOrderFromSpec($spec, $seq++);
        }

        // —— READY / pending delivery ——
        foreach ($this->readySpecs() as $spec) {
            $this->createOrderFromSpec($spec, $seq++);
        }

        // —— IN PRODUCTION (kanban columns) ——
        foreach ($this->productionSpecs() as $spec) {
            $this->createOrderFromSpec($spec, $seq++);
        }

        // —— TRI / reception ——
        foreach ($this->triSpecs() as $spec) {
            $this->createOrderFromSpec($spec, $seq++);
        }
    }

    private function deliveredSpecs(): array
    {
        $specs = [];
        $days = [1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 21, 22];
        $clientCodes = array_keys($this->clients);
        $modes = ['fixed', 'mixed', 'weight_by_type', 'weight_global', 'mixed', 'fixed'];
        $methods = ['cash', 'mobile_money', 'card', 'transfer', 'cash', 'mobile_money'];

        foreach ($days as $i => $day) {
            $clientCode = $clientCodes[$i % count($clientCodes)];
            $agence = ($i % 3 === 0) ? 'bonanjo' : 'akwa';
            $deliveryType = ($i % 4 === 0) ? 'domicile' : 'agence';
            $specs[] = [
                'day' => $day,
                'hour' => 8 + ($i % 8),
                'client' => $clientCode,
                'agence' => $agence,
                'mode' => $modes[$i % count($modes)],
                'pipeline' => 'delivered',
                'payment' => 'full',
                'method' => $methods[$i % count($methods)],
                'delivery_type' => $deliveryType,
                'with_consumables' => $i % 2 === 0,
                'notes' => $i % 5 === 0 ? 'Client VIP — soigner le pliage' : null,
            ];
        }

        // Partial then settled (showcase payment history)
        $specs[] = [
            'day' => 6, 'hour' => 10, 'client' => 'CL-DEMO-001', 'agence' => 'akwa',
            'mode' => 'mixed', 'pipeline' => 'delivered', 'payment' => 'partial_then_full',
            'method' => 'mobile_money', 'delivery_type' => 'agence', 'with_consumables' => true,
            'notes' => 'Acompte puis solde Mobile Money',
        ];

        // Credit-approved then delivered
        $specs[] = [
            'day' => 13, 'hour' => 11, 'client' => 'CL-DEMO-002', 'agence' => 'akwa',
            'mode' => 'fixed', 'pipeline' => 'delivered', 'payment' => 'credit_approved',
            'method' => 'transfer', 'delivery_type' => 'domicile', 'with_consumables' => true,
            'notes' => 'Crédit corporate validé',
        ];

        return $specs;
    }

    private function readySpecs(): array
    {
        return [
            [
                'day' => 23, 'hour' => 9, 'client' => 'CL-DEMO-004', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'ready', 'payment' => 'full',
                'method' => 'cash', 'delivery_type' => 'agence', 'with_consumables' => false,
                'notes' => 'Prêt — client informé WhatsApp',
            ],
            [
                'day' => 23, 'hour' => 14, 'client' => 'CL-DEMO-014', 'agence' => 'bonanjo',
                'mode' => 'weight_global', 'pipeline' => 'ready', 'payment' => 'partial',
                'method' => 'mobile_money', 'delivery_type' => 'domicile', 'with_consumables' => false,
                'notes' => 'Linge restaurant — solde à encaisser',
            ],
            [
                'day' => 22, 'hour' => 16, 'client' => 'CL-DEMO-007', 'agence' => 'akwa',
                'mode' => 'fixed', 'pipeline' => 'ready', 'payment' => 'full',
                'method' => 'card', 'delivery_type' => 'agence', 'with_consumables' => false,
            ],
            [
                'day' => 24, 'hour' => 8, 'client' => 'CL-DEMO-012', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'ready', 'payment' => 'credit_pending',
                'method' => 'cash', 'delivery_type' => 'agence', 'with_consumables' => false,
                'notes' => 'Crédit en attente de validation',
            ],
            [
                'day' => 21, 'hour' => 10, 'client' => 'CL-DEMO-019', 'agence' => 'akwa',
                'mode' => 'weight_by_type', 'pipeline' => 'ready_in_transit', 'payment' => 'full',
                'method' => 'transfer', 'delivery_type' => 'domicile', 'with_consumables' => false,
                'notes' => 'Chauffeur en route',
            ],
        ];
    }

    private function productionSpecs(): array
    {
        return [
            [
                'day' => 24, 'hour' => 7, 'client' => 'CL-DEMO-005', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'lavage', 'payment' => 'full', 'method' => 'cash',
            ],
            [
                'day' => 24, 'hour' => 8, 'client' => 'CL-DEMO-008', 'agence' => 'bonanjo',
                'mode' => 'fixed', 'pipeline' => 'lavage', 'payment' => 'partial', 'method' => 'mobile_money',
            ],
            [
                'day' => 23, 'hour' => 11, 'client' => 'CL-DEMO-010', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'sechage', 'payment' => 'full', 'method' => 'cash',
            ],
            [
                'day' => 23, 'hour' => 15, 'client' => 'CL-DEMO-015', 'agence' => 'akwa',
                'mode' => 'weight_by_type', 'pipeline' => 'sechage', 'payment' => 'full', 'method' => 'card',
            ],
            [
                'day' => 22, 'hour' => 9, 'client' => 'CL-DEMO-006', 'agence' => 'bonanjo',
                'mode' => 'fixed', 'pipeline' => 'repassage', 'payment' => 'full', 'method' => 'cash',
            ],
            [
                'day' => 22, 'hour' => 13, 'client' => 'CL-DEMO-016', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'repassage', 'payment' => 'partial', 'method' => 'mobile_money',
            ],
            [
                'day' => 21, 'hour' => 14, 'client' => 'CL-DEMO-013', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'fin_production', 'payment' => 'full', 'method' => 'transfer',
                'notes' => 'Contrôle qualité / emballage',
            ],
            [
                'day' => 20, 'hour' => 10, 'client' => 'CL-DEMO-018', 'agence' => 'akwa',
                'mode' => 'fixed', 'pipeline' => 'mise_en_production', 'payment' => 'full', 'method' => 'cash',
            ],
            [
                'day' => 24, 'hour' => 9, 'client' => 'CL-DEMO-022', 'agence' => 'akwa',
                'mode' => 'weight_global', 'pipeline' => 'lavage', 'payment' => 'full', 'method' => 'transfer',
                'notes' => 'Lot hôtel — 18 kg',
            ],
            // Overdue (due yesterday, still in production)
            [
                'day' => 18, 'hour' => 9, 'client' => 'CL-DEMO-011', 'agence' => 'bonanjo',
                'mode' => 'fixed', 'pipeline' => 'lavage', 'payment' => 'partial', 'method' => 'cash',
                'overdue' => true, 'notes' => 'EN RETARD — prioriser',
            ],
        ];
    }

    private function triSpecs(): array
    {
        return [
            [
                'day' => 24, 'hour' => 10, 'client' => 'CL-DEMO-009', 'agence' => 'akwa',
                'mode' => 'mixed', 'pipeline' => 'tri_in_progress', 'payment' => 'advance', 'method' => 'cash',
                'notes' => 'Constitution du lot en cours',
            ],
            [
                'day' => 24, 'hour' => 11, 'client' => 'CL-DEMO-021', 'agence' => 'akwa',
                'mode' => 'fixed', 'pipeline' => 'tri_in_progress', 'payment' => 'none', 'method' => 'cash',
            ],
            [
                'day' => 24, 'hour' => 9, 'client' => 'CL-DEMO-017', 'agence' => 'bonanjo',
                'mode' => 'weight_global', 'pipeline' => 'tri_pending', 'payment' => 'advance', 'method' => 'mobile_money',
            ],
            [
                'day' => 23, 'hour' => 17, 'client' => 'CL-DEMO-020', 'agence' => 'bonanjo',
                'mode' => 'mixed', 'pipeline' => 'tri_in_progress', 'payment' => 'advance', 'method' => 'cash',
            ],
        ];
    }

    private function createOrderFromSpec(array $spec, int $seq): void
    {
        $client = $this->clients[$spec['client']];
        $agence = $this->agences[$spec['agence']];
        $receptionist = $spec['agence'] === 'bonanjo'
            ? $this->staff['receptionist2']
            : $this->staff['receptionist'];
        $receivedAt = Carbon::create(2026, 7, $spec['day'], $spec['hour'], 15 + ($seq % 40), 0);
        $dueAt = ! empty($spec['overdue'])
            ? $receivedAt->copy()->addHours(24)
            : $receivedAt->copy()->addHours(48);

        $number = sprintf('CMD-DEMO-%s-%04d', $receivedAt->format('Ymd'), $seq);
        if (PressingOrder::withTrashed()->where('number', $number)->exists()) {
            return;
        }

        $pipeline = $spec['pipeline'];
        $sorted = ! in_array($pipeline, ['tri_pending', 'tri_in_progress'], true);
        $stageName = $this->stageForPipeline($pipeline);
        $status = match (true) {
            str_starts_with($pipeline, 'delivered') => 'delivered',
            in_array($pipeline, ['ready', 'ready_in_transit'], true) => 'ready',
            default => 'open',
        };

        $billing = $this->buildBillingPayload($spec['mode'], $agence->id);
        $order = PressingOrder::create(array_merge([
            'number' => $number,
            'agence_id' => $agence->id,
            'client_id' => $client->id,
            'receptionist_id' => $receptionist->id,
            'assigned_user_id' => $this->staff['production']->id,
            'received_at' => $receivedAt,
            'due_at' => $dueAt,
            'current_stage_id' => $this->stages[$stageName]->id ?? null,
            'status' => $status,
            'sorting_status' => $sorted ? 'completed' : ($pipeline === 'tri_pending' ? 'pending' : 'in_progress'),
            'sorting_completed_at' => $sorted ? $receivedAt->copy()->addMinutes(35) : null,
            'sorted_by' => $sorted ? $receptionist->id : null,
            'notes' => $spec['notes'] ?? null,
            'qr_token' => (string) Str::uuid(),
        ], $billing['order']));

        foreach ($billing['items'] as $item) {
            $line = PressingOrderItem::create(array_merge($item, ['order_id' => $order->id]));
            if ($sorted && ($item['quantity'] ?? 0) > 0 && ($item['pricing_mode'] ?? '') !== 'per_kg') {
                for ($p = 1; $p <= min(3, (int) $item['quantity']); $p++) {
                    PressingOrderPiece::create([
                        'order_id' => $order->id,
                        'order_item_id' => $line->id,
                        'piece_index' => $p,
                        'label' => ($this->types[$item['_type_name']]->name ?? 'Pièce').' #'.$p,
                        'color' => ['Blanc', 'Noir', 'Bleu', 'Gris', 'Beige'][($seq + $p) % 5],
                        'size' => ['M', 'L', 'XL', ''][($seq + $p) % 4],
                        'fabric' => ['Coton', 'Lin', 'Polyester', 'Laine'][($seq + $p) % 4],
                        'defects' => $p === 1 && $seq % 7 === 0 ? 'Petite tache sur le col' : null,
                        'sorted_at' => $receivedAt->copy()->addMinutes(20 + $p),
                        'sorted_by' => $receptionist->id,
                    ]);
                }
            }
        }

        if ($sorted) {
            $this->seedConstitution($order, $billing['items'], $seq);
        }

        $order->recalculateTotals();
        $order->refresh();

        $this->applyPayments($order, $spec, $receptionist, $receivedAt);
        $order->refresh();

        $this->writeStageHistory($order, $pipeline, $receivedAt);
        $this->maybeCreateDelivery($order, $spec, $receivedAt);
        $this->maybeAtelierIssue($order, $pipeline, $receivedAt);

        if ($order->isFullyPaid() && in_array($status, ['ready', 'delivered'], true)) {
            // Lightweight loyalty earn (avoid full service side-effects duplicating)
            $this->recordLoyaltyEarn($order);
        }

        $this->stats['orders']++;
    }

    private function stageForPipeline(string $pipeline): string
    {
        return match ($pipeline) {
            'tri_pending', 'tri_in_progress' => PressingWorkflow::STAGE_TRI,
            'mise_en_production' => PressingWorkflow::STAGE_MISE_EN_PRODUCTION,
            'lavage' => PressingWorkflow::STAGE_LAVAGE,
            'sechage' => PressingWorkflow::STAGE_SECHAGE,
            'repassage' => PressingWorkflow::STAGE_REPASSAGE,
            'fin_production' => PressingWorkflow::STAGE_FIN_PRODUCTION,
            'ready', 'ready_in_transit' => PressingWorkflow::STAGE_PRET,
            'delivered' => PressingWorkflow::STAGE_LIVRE,
            default => PressingWorkflow::STAGE_TRI,
        };
    }

    private function buildBillingPayload(string $mode, int $agenceId): array
    {
        $chemise = $this->types['Chemise'];
        $pantalon = $this->types['Pantalon'];
        $costume = $this->types['Costume'];
        $robe = $this->types['Robe'];
        $rideaux = $this->types['Rideaux'];
        $couverture = $this->types['Couverture'];
        $boubou = $this->types['Boubou'];

        return match ($mode) {
            'fixed' => [
                'order' => [
                    'billing_mode' => 'fixed',
                    'total_weight_kg' => null,
                    'weight_unit_price' => null,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
                'items' => [
                    $this->fixedItem($chemise, 3, 'Blanc'),
                    $this->fixedItem($pantalon, 2, 'Noir'),
                    $this->fixedItem($costume, 1, 'Gris'),
                ],
            ],
            'weight_global' => [
                'order' => [
                    'billing_mode' => 'weight_global',
                    'total_weight_kg' => 12.5,
                    'weight_unit_price' => 1500,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
                'items' => [
                    $this->metaItem($couverture, 2, 'weight_global'),
                    $this->metaItem($rideaux, 1, 'weight_global'),
                ],
            ],
            'weight_by_type' => [
                'order' => [
                    'billing_mode' => 'weight_by_type',
                    'total_weight_kg' => 8.2,
                    'weight_unit_price' => null,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
                'items' => [
                    $this->kgItem($rideaux, 3.5, 'Beige'),
                    $this->kgItem($couverture, 4.7, 'Bordeaux'),
                ],
            ],
            default => [ // mixed
                'order' => [
                    'billing_mode' => 'mixed',
                    'total_weight_kg' => 2.4,
                    'weight_unit_price' => null,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
                'items' => [
                    $this->fixedItem($chemise, 4, 'Bleu ciel'),
                    $this->fixedItem($pantalon, 2, 'Noir'),
                    $this->fixedItem($boubou, 1, 'Wax'),
                    $this->kgItem($robe, 2.4, 'Rouge'),
                ],
            ],
        };
    }

    private function fixedItem(ArticleType $type, int $qty, string $color): array
    {
        $unit = $this->prices[$type->name]['fixed'];

        return [
            '_type_name' => $type->name,
            'article_type_id' => $type->id,
            'quantity' => $qty,
            'weight_kg' => null,
            'price_per_kg' => null,
            'pricing_mode' => 'fixed',
            'color' => $color,
            'brand' => null,
            'size' => 'L',
            'notes' => null,
            'condition_on_receipt' => 'Bon état',
            'unit_price' => $unit,
            'line_total' => $unit * $qty,
        ];
    }

    private function kgItem(ArticleType $type, float $kg, string $color): array
    {
        $perKg = $this->prices[$type->name]['per_kg'] ?: 1500;

        return [
            '_type_name' => $type->name,
            'article_type_id' => $type->id,
            'quantity' => 1,
            'weight_kg' => $kg,
            'price_per_kg' => $perKg,
            'pricing_mode' => 'per_kg',
            'color' => $color,
            'brand' => null,
            'size' => null,
            'notes' => null,
            'condition_on_receipt' => 'À laver',
            'unit_price' => 0,
            'line_total' => round($kg * $perKg, 2),
        ];
    }

    private function metaItem(ArticleType $type, int $qty, string $mode): array
    {
        return [
            '_type_name' => $type->name,
            'article_type_id' => $type->id,
            'quantity' => $qty,
            'weight_kg' => null,
            'price_per_kg' => null,
            'pricing_mode' => $mode,
            'color' => null,
            'brand' => null,
            'size' => null,
            'notes' => 'Pesée globale',
            'condition_on_receipt' => 'Bon état',
            'unit_price' => 0,
            'line_total' => 0,
        ];
    }

    private function seedConstitution(PressingOrder $order, array $items, int $seq): void
    {
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['article_type_id'])) {
                continue;
            }
            PressingOrderConstitutionLine::create([
                'order_id' => $order->id,
                'article_type_id' => $item['article_type_id'],
                'color' => $item['color'] ?? ['Blanc', 'Noir', 'Bleu'][$seq % 3],
                'pattern' => $seq % 6 === 0 ? 'jean' : null,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'notes' => null,
                'sort_order' => $sort++,
            ]);
        }
    }

    private function applyPayments(PressingOrder $order, array $spec, User $receptionist, Carbon $receivedAt): void
    {
        $mode = $spec['payment'] ?? 'full';
        $method = $spec['method'] ?? 'cash';
        $total = (float) $order->total;

        if ($mode === 'none' || $total <= 0) {
            return;
        }

        $pay = function (float $amount, string $m, Carbon $at, ?string $ref = null, ?string $notes = null) use ($order, $receptionist) {
            PressingPayment::create([
                'order_id' => $order->id,
                'agence_id' => $order->agence_id,
                'method' => $m,
                'amount' => $amount,
                'reference' => $ref,
                'received_by' => $receptionist->id,
                'paid_at' => $at,
                'notes' => $notes,
            ]);
            $this->stats['payments']++;
        };

        match ($mode) {
            'advance' => $pay(min(2000, $total * 0.3), $method, $receivedAt, null, 'Avance à la réception'),
            'partial' => $pay(round($total * 0.5, 0), $method, $receivedAt->copy()->addHours(2), 'MM-'.Str::upper(Str::random(6)), 'Paiement partiel'),
            'partial_then_full' => (function () use ($pay, $total, $method, $receivedAt) {
                $first = round($total * 0.4, 0);
                $pay($first, 'cash', $receivedAt, null, 'Acompte');
                $pay($total - $first, $method, $receivedAt->copy()->addDays(1)->setHour(16), 'MM-'.Str::upper(Str::random(6)), 'Solde');
            })(),
            'credit_approved' => (function () use ($order, $pay, $total, $method, $receivedAt) {
                $paid = round($total * 0.3, 0);
                $pay($paid, $method, $receivedAt, 'VIR-'.Str::upper(Str::random(5)), 'Acompte corporate');
                $order->forceFill([
                    'credit_status' => 'approved',
                    'credit_amount' => $total - $paid,
                    'credit_notes' => 'Compte entreprise — règlement fin de mois',
                    'credit_requested_by' => $this->staff['receptionist']->id,
                    'credit_requested_at' => $receivedAt->copy()->addHours(1),
                    'credit_validated_by' => $this->staff['admin']->id,
                    'credit_validated_at' => $receivedAt->copy()->addHours(2),
                ])->save();
            })(),
            'credit_pending' => (function () use ($order, $pay, $total, $method, $receivedAt) {
                $paid = round($total * 0.2, 0);
                $pay($paid, $method, $receivedAt, null, 'Acompte');
                $order->forceFill([
                    'credit_status' => 'pending',
                    'credit_amount' => $total - $paid,
                    'credit_notes' => 'Demande crédit client fidèle',
                    'credit_requested_by' => $this->staff['receptionist']->id,
                    'credit_requested_at' => $receivedAt->copy()->addHours(3),
                ])->save();
            })(),
            default => $pay($total, $method, $receivedAt->copy()->addMinutes(10), $method === 'mobile_money' ? 'MM-'.Str::upper(Str::random(6)) : null),
        };

        $order->recalculateTotals();
    }

    private function writeStageHistory(PressingOrder $order, string $pipeline, Carbon $start): void
    {
        $path = match ($pipeline) {
            'tri_pending', 'tri_in_progress' => [PressingWorkflow::STAGE_TRI],
            'mise_en_production' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION],
            'lavage' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE],
            'sechage' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE, PressingWorkflow::STAGE_SECHAGE],
            'repassage' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE, PressingWorkflow::STAGE_SECHAGE, PressingWorkflow::STAGE_REPASSAGE],
            'fin_production' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE, PressingWorkflow::STAGE_SECHAGE, PressingWorkflow::STAGE_REPASSAGE, PressingWorkflow::STAGE_FIN_PRODUCTION],
            'ready', 'ready_in_transit' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE, PressingWorkflow::STAGE_SECHAGE, PressingWorkflow::STAGE_REPASSAGE, PressingWorkflow::STAGE_FIN_PRODUCTION, PressingWorkflow::STAGE_PRET],
            'delivered' => [PressingWorkflow::STAGE_TRI, PressingWorkflow::STAGE_MISE_EN_PRODUCTION, PressingWorkflow::STAGE_LAVAGE, PressingWorkflow::STAGE_SECHAGE, PressingWorkflow::STAGE_REPASSAGE, PressingWorkflow::STAGE_FIN_PRODUCTION, PressingWorkflow::STAGE_PRET, PressingWorkflow::STAGE_LIVRE],
            default => [PressingWorkflow::STAGE_TRI],
        };

        $actors = [
            PressingWorkflow::STAGE_TRI => $this->staff['receptionist']->id,
            PressingWorkflow::STAGE_MISE_EN_PRODUCTION => $this->staff['production']->id,
            PressingWorkflow::STAGE_LAVAGE => $this->staff['production']->id,
            PressingWorkflow::STAGE_SECHAGE => $this->staff['production']->id,
            PressingWorkflow::STAGE_REPASSAGE => $this->staff['presser']->id,
            PressingWorkflow::STAGE_FIN_PRODUCTION => $this->staff['presser']->id,
            PressingWorkflow::STAGE_PRET => $this->staff['receptionist']->id,
            PressingWorkflow::STAGE_LIVRE => $this->staff['driver']->id,
        ];

        $cursor = $start->copy();
        foreach ($path as $i => $name) {
            $stage = $this->stages[$name] ?? null;
            if (! $stage) {
                continue;
            }
            OrderStageHistory::create([
                'order_id' => $order->id,
                'stage_id' => $stage->id,
                'stage_name' => $name,
                'user_id' => $actors[$name] ?? $this->staff['admin']->id,
                'moved_at' => $cursor->copy(),
                'note' => $i === 0 ? 'Réception enregistrée' : 'Passage automatique [DEMO]',
            ]);
            $cursor->addHours(2 + ($i % 3));
        }
    }

    private function maybeCreateDelivery(PressingOrder $order, array $spec, Carbon $receivedAt): void
    {
        $pipeline = $spec['pipeline'];
        if (! in_array($pipeline, ['delivered', 'ready', 'ready_in_transit'], true)) {
            return;
        }

        $type = $spec['delivery_type'] ?? 'agence';
        $status = match ($pipeline) {
            'delivered' => 'delivered',
            'ready_in_transit' => 'in_transit',
            default => 'pending',
        };

        $scheduled = $receivedAt->copy()->addDays(2)->setHour(15);
        $deliveredAt = $status === 'delivered' ? $scheduled->copy()->addHours(1) : null;

        $delivery = PressingDelivery::create([
            'order_id' => $order->id,
            'agence_id' => $order->agence_id,
            'type' => $type,
            'status' => $status,
            'driver_user_id' => $type === 'domicile' ? $this->staff['driver']->id : null,
            'vehicle' => $type === 'domicile' ? 'Toyota Hilux — LT 4523 D' : null,
            'address' => $type === 'domicile' ? ($order->client?->address ?: 'Adresse client') : null,
            'scheduled_at' => $scheduled,
            'delivered_at' => $deliveredAt,
            'notes' => $type === 'domicile' ? 'Appeler 10 min avant' : 'Retrait comptoir',
            'created_by' => $this->staff['receptionist']->id,
        ]);
        $this->stats['deliveries']++;

        if ($status === 'delivered' && ! empty($spec['with_consumables'])) {
            $this->createLivraisonIssue($delivery, $deliveredAt);
        }

        if ($status === 'delivered') {
            $order->forceFill([
                'status' => 'delivered',
                'current_stage_id' => $this->stages[PressingWorkflow::STAGE_LIVRE]->id ?? $order->current_stage_id,
            ])->save();
        }
    }

    private function createLivraisonIssue(PressingDelivery $delivery, ?Carbon $at): void
    {
        $items = Item::whereIn('sku', ['CONS-CINTRES', 'CONS-EMBALLAGES', 'CONS-ETIQUETTES'])->get()->keyBy('sku');
        if ($items->isEmpty()) {
            return;
        }

        $issue = PressingConsumableIssue::create([
            'number' => 'LIV-DEMO-'.$delivery->id,
            'type' => PressingConsumableIssue::TYPE_LIVRAISON,
            'order_id' => $delivery->order_id,
            'delivery_id' => $delivery->id,
            'taken_by' => $this->staff['driver']->id,
            'issued_by' => $this->staff['receptionist']->id,
            'purpose' => 'livraison',
            'work_context' => 'Remise '.$delivery->order?->number,
            'pieces_processed' => null,
            'notes' => '[DEMO]',
            'issued_at' => $at ?? now(),
        ]);

        $lines = [
            ['sku' => 'CONS-CINTRES', 'qty' => 4],
            ['sku' => 'CONS-EMBALLAGES', 'qty' => 2],
            ['sku' => 'CONS-ETIQUETTES', 'qty' => 4],
        ];
        foreach ($lines as $line) {
            if (! isset($items[$line['sku']])) {
                continue;
            }
            PressingConsumableIssueLine::create([
                'issue_id' => $issue->id,
                'item_id' => $items[$line['sku']]->id,
                'quantity' => $line['qty'],
                'unit_label' => 'pc',
            ]);
            try {
                app(\InovCom\Stock\Services\StockService::class)->removeStock(
                    $items[$line['sku']]->id,
                    $line['qty'],
                    'out',
                    'PressingConsumableIssue',
                    $issue->id,
                    'Demo livraison'
                );
            } catch (\Throwable) {
                // ignore stock underflow in demo
            }
        }
        $this->stats['livraison_issues']++;
    }

    private function maybeAtelierIssue(PressingOrder $order, string $pipeline, Carbon $receivedAt): void
    {
        if (! in_array($pipeline, ['lavage', 'sechage', 'repassage', 'fin_production', 'ready', 'ready_in_transit', 'delivered'], true)) {
            return;
        }
        // Only create atelier issues for a subset to keep volume realistic
        if ($order->id % 3 !== 0) {
            return;
        }

        $purpose = match ($pipeline) {
            'lavage' => 'lavage',
            'sechage' => 'sechage',
            'repassage' => 'repassage',
            default => 'finition',
        };

        $items = Item::whereIn('sku', ['CONS-LESSIVE', 'CONS-SAVON', 'CONS-PARFUM'])->get()->keyBy('sku');
        if ($items->isEmpty()) {
            return;
        }

        $issue = PressingConsumableIssue::create([
            'number' => 'ATS-DEMO-'.$order->id,
            'type' => PressingConsumableIssue::TYPE_ATELIER,
            'order_id' => $order->id,
            'taken_by' => $this->staff['production']->id,
            'issued_by' => $this->staff['admin']->id,
            'purpose' => $purpose,
            'work_context' => 'Lot '.$order->number,
            'pieces_processed' => 8 + ($order->id % 12),
            'notes' => 'Sortie atelier [DEMO]',
            'issued_at' => $receivedAt->copy()->addHours(3),
        ]);

        $qtyMap = [
            'CONS-LESSIVE' => 1.5,
            'CONS-SAVON' => 2,
            'CONS-PARFUM' => 0.5,
        ];
        foreach ($qtyMap as $sku => $qty) {
            if (! isset($items[$sku])) {
                continue;
            }
            PressingConsumableIssueLine::create([
                'issue_id' => $issue->id,
                'item_id' => $items[$sku]->id,
                'quantity' => $qty,
                'unit_label' => $sku === 'CONS-SAVON' ? 'pc' : 'L',
            ]);
            try {
                app(\InovCom\Stock\Services\StockService::class)->removeStock(
                    $items[$sku]->id,
                    $qty,
                    'out',
                    'PressingConsumableIssue',
                    $issue->id,
                    'Demo atelier'
                );
            } catch (\Throwable) {
            }
        }
        $this->stats['atelier_issues']++;
    }

    private function recordLoyaltyEarn(PressingOrder $order): void
    {
        if (PressingLoyaltyEntry::where('order_id', $order->id)->where('type', 'earn')->exists()) {
            return;
        }
        $client = PressingClient::find($order->client_id);
        if (! $client) {
            return;
        }

        $points = 1 + (int) floor((float) $order->total / 2000);
        $client->loyalty_points = (int) $client->loyalty_points + $points;
        $client->loyalty_orders_count = (int) $client->loyalty_orders_count + 1;
        $client->save();

        PressingLoyaltyEntry::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'type' => PressingLoyaltyEntry::TYPE_EARN,
            'points' => $points,
            'balance_after' => (int) $client->loyalty_points,
            'reason' => __('Commande :number payée', ['number' => $order->number]).' [DEMO]',
            'created_by' => $this->staff['admin']->id,
        ]);
        $this->stats['loyalty_entries']++;
    }

    private function seedLoyaltyShowcase(): void
    {
        // Ensure VIP clients have available / used rewards for demo
        $vip = $this->clients['CL-DEMO-001'] ?? null;
        if ($vip) {
            $vip->update(['loyalty_points' => 8, 'loyalty_orders_count' => max(10, $vip->loyalty_orders_count)]);

            PressingLoyaltyReward::updateOrCreate(
                ['code' => 'LOY-DEMO-VIP1'],
                [
                    'client_id' => $vip->id,
                    'reward_type' => 'value',
                    'reward_value' => 2000,
                    'reward_max' => null,
                    'points_spent' => 10,
                    'status' => 'available',
                    'issued_by' => $this->staff['admin']->id,
                    'expires_at' => $this->today->copy()->addDays(60),
                ]
            );

            PressingLoyaltyReward::updateOrCreate(
                ['code' => 'LOY-DEMO-VIP2'],
                [
                    'client_id' => $vip->id,
                    'reward_type' => 'percent',
                    'reward_value' => 10,
                    'reward_max' => 5000,
                    'points_spent' => 10,
                    'status' => 'used',
                    'order_id' => PressingOrder::where('client_id', $vip->id)->where('number', 'like', 'CMD-DEMO-%')->value('id'),
                    'discount_amount' => 2000,
                    'issued_by' => $this->staff['admin']->id,
                    'used_by' => $this->staff['receptionist']->id,
                    'used_at' => $this->today->copy()->subDays(5),
                    'expires_at' => $this->today->copy()->addDays(30),
                ]
            );
            $this->stats['loyalty_rewards'] += 2;
        }

        $corp = $this->clients['CL-DEMO-002'] ?? null;
        if ($corp) {
            PressingLoyaltyReward::updateOrCreate(
                ['code' => 'LOY-DEMO-CORP'],
                [
                    'client_id' => $corp->id,
                    'reward_type' => 'value',
                    'reward_value' => 2000,
                    'points_spent' => 10,
                    'status' => 'available',
                    'issued_by' => $this->staff['admin']->id,
                    'expires_at' => $this->today->copy()->addDays(45),
                ]
            );
            $this->stats['loyalty_rewards']++;
        }

        // Manual adjust showcase
        if ($vip) {
            PressingLoyaltyEntry::create([
                'client_id' => $vip->id,
                'order_id' => null,
                'type' => PressingLoyaltyEntry::TYPE_ADJUST,
                'points' => 2,
                'balance_after' => (int) $vip->fresh()->loyalty_points,
                'reason' => 'Bonus fidélité juillet [DEMO]',
                'created_by' => $this->staff['admin']->id,
            ]);
            $this->stats['loyalty_entries']++;
        }
    }

    private function seedNotifications(): void
    {
        $adminId = $this->staff['admin']->id;
        $recent = PressingOrder::where('number', 'like', 'CMD-DEMO-%')
            ->latest('received_at')
            ->limit(12)
            ->get();

        foreach ($recent as $i => $order) {
            $events = match ($order->status) {
                'delivered' => ['order_created', 'payment_received', 'order_ready', 'order_delivered'],
                'ready' => ['order_created', 'payment_received', 'order_ready'],
                default => ['order_created'],
            };

            foreach ($events as $event) {
                PressingNotification::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'type' => $event,
                    'title' => match ($event) {
                        'order_created' => 'Nouvelle réception',
                        'payment_received' => 'Paiement reçu',
                        'order_ready' => 'Commande prête',
                        'order_delivered' => 'Commande livrée',
                        default => $event,
                    },
                    'body' => $order->number.' — '.$order->client?->full_name,
                    'data' => ['order_id' => $order->id],
                    'order_id' => $order->id,
                    'read_at' => $i > 4 ? $order->received_at->copy()->addHours(2) : null,
                ]);

                foreach (['in_app', 'whatsapp'] as $channel) {
                    PressingNotificationLog::create([
                        'event' => $event,
                        'channel' => $channel,
                        'status' => 'sent',
                        'order_id' => $order->id,
                        'user_id' => $adminId,
                        'recipient' => $channel === 'whatsapp' ? ($order->client?->whatsapp ?: '') : ($order->client?->email ?: 'staff'),
                        'message' => $order->number.' / '.$event,
                        'error' => null,
                    ]);
                }
                $this->stats['notifications']++;
            }
        }

        // Overdue alert
        $overdue = PressingOrder::where('number', 'like', 'CMD-DEMO-%')
            ->where('status', 'open')
            ->where('due_at', '<', $this->today)
            ->first();
        if ($overdue) {
            PressingNotification::create([
                'id' => (string) Str::uuid(),
                'user_id' => $adminId,
                'type' => 'order_overdue',
                'title' => 'Commande en retard',
                'body' => $overdue->number.' — délai dépassé',
                'data' => ['order_id' => $overdue->id],
                'order_id' => $overdue->id,
                'read_at' => null,
            ]);
            $this->stats['notifications']++;
        }
    }

    private function printSummary(): void
    {
        $this->command?->info('✓ Agences: '.$this->stats['agences'].' (Akwa + Bonanjo)');
        $this->command?->info('✓ Staff: '.$this->stats['staff'].' employés démo (+ admin)');
        $this->command?->info('✓ Clients: '.$this->stats['clients']);
        $this->command?->info('✓ Commandes: '.$this->stats['orders'].' (pipeline complet juillet 2026)');
        $this->command?->info('✓ Paiements: '.$this->stats['payments']);
        $this->command?->info('✓ Livraisons: '.$this->stats['deliveries']);
        $this->command?->info('✓ Bons atelier: '.$this->stats['atelier_issues'].' · Remises: '.$this->stats['livraison_issues']);
        $this->command?->info('✓ Fidélité: '.$this->stats['loyalty_entries'].' mouvements, '.$this->stats['loyalty_rewards'].' bons');
        $this->command?->info('✓ Notifications: '.$this->stats['notifications']);
        $this->command?->line('  Staff passwords: Pressing2026!');
        $this->command?->line('  Loyalty program: ENABLED (10 pts = 2 000 FCFA)');
    }
}
