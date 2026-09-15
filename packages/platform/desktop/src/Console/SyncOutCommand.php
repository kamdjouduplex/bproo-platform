<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\OutboxPusher;
use Illuminate\Console\Command;

class SyncOutCommand extends Command
{
    protected $signature = 'desktop:sync-out {--limit= : Max events per batch}';

    protected $description = 'Pousse les événements outbox locaux vers Control Center (OUT sync)';

    public function handle(OutboxPusher $pusher): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        try {
            $result = $pusher->push($limit);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Sync OUT — accepted=%d duplicates=%d rejected=%d',
            $result['accepted'],
            $result['duplicates'],
            $result['rejected']
        ));

        return self::SUCCESS;
    }
}
