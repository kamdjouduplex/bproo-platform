<?php

namespace App\Console\Commands;

use App\Services\ModuleVersionService;
use Illuminate\Console\Command;

class ModuleVersionSync extends Command
{
    protected $signature = 'modules:version-sync';
    protected $description = 'Sync module versions from composer.json packages';

    public function handle(ModuleVersionService $versionService): int
    {
        $this->info('Syncing module versions...');

        $versionService->syncAllVersions();

        $this->info('Module versions synced successfully.');

        return Command::SUCCESS;
    }
}
