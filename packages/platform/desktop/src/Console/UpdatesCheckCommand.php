<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\UpdateClient;
use Illuminate\Console\Command;

class UpdatesCheckCommand extends Command
{
    protected $signature = 'desktop:updates-check {--current= : Override current app version (e.g. 0.0.1)}';

    protected $description = 'Interroge Control Center pour une mise à jour desktop';

    public function handle(UpdateClient $updates): int
    {
        try {
            $result = $updates->check($this->option('current') ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! ($result['update_available'] ?? false)) {
            $this->info('Aucune mise à jour disponible.');

            return self::SUCCESS;
        }

        $version = $result['manifest']['version'] ?? '?';
        $this->info("Mise à jour disponible : {$version}");
        $this->line('Lancez: php artisan desktop:updates-apply');

        return self::SUCCESS;
    }
}
