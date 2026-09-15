<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\UpdateApplier;
use Bproo\Platform\Desktop\Services\UpdateClient;
use Illuminate\Console\Command;

class UpdatesApplyCommand extends Command
{
    protected $signature = 'desktop:updates-apply {--version= : Override current version for check}';

    protected $description = 'Télécharge, vérifie SHA-256, stage + healthcheck + rollback marker';

    public function handle(UpdateClient $updates, UpdateApplier $applier): int
    {
        try {
            $check = $updates->check($this->option('version') ?: null);
            if (! ($check['update_available'] ?? false)) {
                $this->info('Rien à appliquer.');

                return self::SUCCESS;
            }

            $manifest = $check['manifest'] ?? [];
            $path = $updates->download($manifest);
            $this->line('Package téléchargé : '.$path);

            $result = $applier->apply($path, $manifest);
            if (! $result['ok']) {
                $this->error($result['message']);

                return self::FAILURE;
            }

            $this->info($result['message']);
            $this->line('Staging: '.$result['staging']);
            $this->line('Rollback: '.$result['rollback']);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
