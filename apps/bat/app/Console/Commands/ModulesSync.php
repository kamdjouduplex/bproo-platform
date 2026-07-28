<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class ModulesSync extends Command
{
    protected $signature = 'modules:sync';
    protected $description = 'Sync modules from config/modules.php to database (add or update).';

    public function handle(): int
    {
        $modules = collect(config('modules', []))
            ->except('core_migration_tags')
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        foreach ($modules as $key => $config) {
            Module::updateOrCreate(
                ['key' => $key],
                [
                    'label' => $config['label'],
                    'description' => $config['description'] ?? null,
                    'route_name' => $config['route_name'] ?? null,
                    'lifecycle_handler' => $config['lifecycle_handler'] ?? null,
                    'enabled_by_default' => $config['enabled_by_default'] ?? false,
                    'menu_order' => $config['menu_order'] ?? 100,
                ]
            );
            $this->line("Synced module: {$key}");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
