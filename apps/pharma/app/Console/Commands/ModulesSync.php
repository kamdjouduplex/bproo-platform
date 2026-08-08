<?php

namespace App\Console\Commands;

use App\Services\ModulesCatalogSync;
use Illuminate\Console\Command;

class ModulesSync extends Command
{
    protected $signature = 'modules:sync
                            {--reset-defaults : Overwrite enabled_by_default from config for all modules}';
    protected $description = 'Sync modules from config/modules.php to database (add or update).';

    public function handle(): int
    {
        ModulesCatalogSync::syncFromConfig(preserveDefaults: !$this->option('reset-defaults'));

        $modules = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        foreach ($modules->keys() as $key) {
            $this->line("Synced module: {$key}");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
