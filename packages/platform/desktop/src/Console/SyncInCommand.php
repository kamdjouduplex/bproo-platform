<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\EventApplier;
use Bproo\Platform\Desktop\Services\InboxPuller;
use Illuminate\Console\Command;

class SyncInCommand extends Command
{
    protected $signature = 'desktop:sync-in {--limit= : Max events per pull/apply} {--apply-only : Skip pull, only apply pending} {--pull-only : Skip apply}';

    protected $description = 'Tire les evenements des autres PC (meme tenant) depuis Control Center et les applique';

    public function handle(InboxPuller $puller, EventApplier $applier): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        try {
            if (! $this->option('apply-only')) {
                $pulled = $puller->pull($limit);
                $this->info(sprintf(
                    'Sync IN pull — pulled=%d next_after_id=%d',
                    $pulled['pulled'],
                    $pulled['next_after_id']
                ));
            }

            if (! $this->option('pull-only')) {
                $applied = $applier->applyPending($limit);
                $this->info(sprintf(
                    'Sync IN apply — applied=%d failed=%d ignored=%d',
                    $applied['applied'],
                    $applied['failed'],
                    $applied['ignored']
                ));
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
