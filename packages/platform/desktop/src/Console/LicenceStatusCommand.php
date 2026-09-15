<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\LicenceGuard;
use Illuminate\Console\Command;

class LicenceStatusCommand extends Command
{
    protected $signature = 'desktop:licence-status';

    protected $description = 'Vérifie le jeton licence local (HMAC + fenêtre offline)';

    public function handle(LicenceGuard $guard): int
    {
        $result = $guard->evaluate();
        if (! ($result['ok'] ?? false)) {
            $this->error('Licence KO: '.($result['reason'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info('Licence OK — mode='.($result['access_mode'] ?? '?'));
        $this->line('Expire: '.($result['expires_at'] ?? '?'));

        return self::SUCCESS;
    }
}
