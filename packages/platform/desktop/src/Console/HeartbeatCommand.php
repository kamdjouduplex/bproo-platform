<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\LicenceClient;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'desktop:heartbeat';

    protected $description = 'Rafraîchit le jeton licence / fenêtre offline auprès du Control Center';

    public function handle(LicenceClient $licences): int
    {
        try {
            $result = $licences->heartbeat();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $expires = $result['licence']['offline_access_expires_at'] ?? '?';
        $this->info("Heartbeat OK — offline jusqu’à {$expires}");

        return self::SUCCESS;
    }
}
