<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['code' => 'demo'],
            [
                'name' => 'Boutique Demo',
                'db_name' => 'inovcom_demo',
                'db_host' => null,
                'db_port' => null,
                'db_username' => null,
                'db_password' => null,
                'is_active' => true,
                'metadata' => ['currency' => 'XOF'],
            ]
        );
    }
}
