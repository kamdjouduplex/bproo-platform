<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantModuleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('code', 'demo')->first();
        if (!$tenant) {
            return;
        }

        $modules = Module::all();
        foreach ($modules as $module) {
            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => $module->enabled_by_default],
            ]);
        }
    }
}
