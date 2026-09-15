<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\LicenceClient;
use Illuminate\Console\Command;

class ActivateCommand extends Command
{
    protected $signature = 'desktop:activate {code : Code d\'activation Control Center}';

    protected $description = 'Active cette install desktop auprès du Control Center';

    public function handle(LicenceClient $licences): int
    {
        try {
            $result = $licences->activate((string) $this->argument('code'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $uuid = $result['licence']['install_uuid'] ?? '?';
        $this->info("Activation OK — install_uuid={$uuid}");
        $this->line('Token licence stocké dans storage/app/desktop/state.json');

        return self::SUCCESS;
    }
}
