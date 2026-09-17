<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\EventApplier;
use Bproo\Platform\Desktop\Services\InboxPuller;
use Bproo\Platform\Desktop\Services\LicenceClient;
use Bproo\Platform\Desktop\Services\OutboxPusher;
use Bproo\Platform\Desktop\Services\OutboxWriter;
use Bproo\Platform\Desktop\Services\RuntimeStateStore;
use Illuminate\Console\Command;

/**
 * Single-machine multi-PC smoke: two simulated installs (A → CC → B).
 *
 * Usage (same PC, same CC tenant, TWO activation codes):
 *   php artisan desktop:sync-smoke --code-a=XXXX --code-b=YYYY
 *
 * Or if already activated into state-pc-a / state-pc-b:
 *   php artisan desktop:sync-smoke
 */
class SyncSmokeCommand extends Command
{
    protected $signature = 'desktop:sync-smoke
        {--code-a= : Activation code for simulated PC-A}
        {--code-b= : Activation code for simulated PC-B}
        {--skip-activate : Do not activate; reuse existing profile states}';

    protected $description = 'Test sync multi-PC sur UNE seule machine (2 profils A/B)';

    public function handle(
        LicenceClient $licences,
        OutboxWriter $writer,
        OutboxPusher $pusher,
        InboxPuller $puller,
        EventApplier $applier,
        RuntimeStateStore $state
    ): int {
        $profileA = 'desktop/state-pc-a.json';
        $profileB = 'desktop/state-pc-b.json';

        try {
            if (! $this->option('skip-activate')) {
                $codeA = trim((string) $this->option('code-a'));
                $codeB = trim((string) $this->option('code-b'));
                if ($codeA === '' || $codeB === '') {
                    $this->error('Fournis --code-a et --code-b (2 codes CC, même entreprise), ou --skip-activate.');
                    $this->line('Dans le CC: Installations desktop → 2 codes pour le même tenant.');

                    return self::FAILURE;
                }

                $this->activateProfile($licences, $state, $profileA, 'sim-pc-a', $codeA);
                $this->activateProfile($licences, $state, $profileB, 'sim-pc-b', $codeB);
            }

            // --- PC-A: enqueue + OUT ---
            $this->withProfile($state, $profileA, 'sim-pc-a', function () use ($writer, $pusher) {
                $event = $writer->enqueue('sync.ping', [
                    'source' => 'desktop:sync-smoke',
                    'role' => 'pc-a',
                    'at' => now()->toIso8601String(),
                ]);
                $this->info("PC-A outbox + {$event->event_id}");
                $out = $pusher->push();
                $this->info(sprintf(
                    'PC-A sync-out — accepted=%d duplicates=%d',
                    $out['accepted'],
                    $out['duplicates']
                ));
            });

            // --- PC-B: IN ---
            $this->withProfile($state, $profileB, 'sim-pc-b', function () use ($puller, $applier) {
                $pulled = $puller->pull();
                $this->info(sprintf(
                    'PC-B sync-in pull — pulled=%d next_after_id=%d',
                    $pulled['pulled'],
                    $pulled['next_after_id']
                ));
                $applied = $applier->applyPending();
                $this->info(sprintf(
                    'PC-B sync-in apply — applied=%d failed=%d ignored=%d',
                    $applied['applied'],
                    $applied['failed'],
                    $applied['ignored']
                ));
                if ($pulled['pulled'] < 1 && $applied['applied'] < 1) {
                    throw new \RuntimeException(
                        'PC-B n\'a rien reçu. Vérifie: mêmes tenant, CONTROL_CENTER_URL, et que PC-A a bien poussé.'
                    );
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('OK — sync multi-PC simulé sur 1 machine (A → CC → B).');

        return self::SUCCESS;
    }

    private function activateProfile(
        LicenceClient $licences,
        RuntimeStateStore $state,
        string $stateRelative,
        string $fingerprintAlias,
        string $code
    ): void {
        $this->withProfile($state, $stateRelative, $fingerprintAlias, function () use ($licences, $code, $fingerprintAlias) {
            $result = $licences->activate($code);
            $uuid = $result['licence']['install_uuid'] ?? '?';
            $this->info("Activé {$fingerprintAlias} → install_uuid={$uuid}");
        });
    }

    /**
     * Temporarily switch DESKTOP_STATE_PATH + DESKTOP_FINGERPRINT for one profile.
     */
    private function withProfile(RuntimeStateStore $state, string $stateRelative, string $fingerprintAlias, callable $fn): void
    {
        $prevState = config('desktop.state_path');
        $prevFp = env('DESKTOP_FINGERPRINT');

        config(['desktop.state_path' => $stateRelative]);
        putenv('DESKTOP_FINGERPRINT='.$fingerprintAlias);
        $_ENV['DESKTOP_FINGERPRINT'] = $fingerprintAlias;
        $_SERVER['DESKTOP_FINGERPRINT'] = $fingerprintAlias;

        // Force fingerprint into state for this profile.
        $state->merge(['fingerprint' => hash('sha256', 'override|'.$fingerprintAlias.'|'.(string) config('desktop.product_key'))]);

        try {
            $fn();
        } finally {
            config(['desktop.state_path' => $prevState]);
            if ($prevFp === null || $prevFp === false || $prevFp === '') {
                putenv('DESKTOP_FINGERPRINT');
                unset($_ENV['DESKTOP_FINGERPRINT'], $_SERVER['DESKTOP_FINGERPRINT']);
            } else {
                putenv('DESKTOP_FINGERPRINT='.$prevFp);
                $_ENV['DESKTOP_FINGERPRINT'] = $prevFp;
                $_SERVER['DESKTOP_FINGERPRINT'] = $prevFp;
            }
        }
    }
}
