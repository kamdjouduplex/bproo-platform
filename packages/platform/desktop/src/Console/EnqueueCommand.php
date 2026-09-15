<?php

namespace Bproo\Platform\Desktop\Console;

use Bproo\Platform\Desktop\Services\OutboxWriter;
use Illuminate\Console\Command;

class EnqueueCommand extends Command
{
    protected $signature = 'desktop:enqueue
        {type : Event type e.g. sale.created}
        {payload? : JSON payload (optional)}
        {--file= : Path to JSON payload file}';

    protected $description = 'Ajoute un événement dans l’outbox local (smoke / tooling)';

    public function handle(OutboxWriter $writer): int
    {
        $type = (string) $this->argument('type');
        $payloadRaw = $this->argument('payload');
        $file = $this->option('file');

        try {
            if ($file) {
                $json = file_get_contents((string) $file);
                $payload = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            } elseif ($payloadRaw) {
                $payload = json_decode((string) $payloadRaw, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $payload = ['source' => 'desktop:enqueue', 'at' => now()->toIso8601String()];
            }

            if (! is_array($payload)) {
                throw new \RuntimeException('Payload JSON invalide.');
            }

            $event = $writer->enqueue($type, $payload);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Outbox + {$event->event_id} ({$event->type})");

        return self::SUCCESS;
    }
}
