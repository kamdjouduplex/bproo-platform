<?php

namespace App\Console\Commands;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGuineaProspectsCsv extends Command
{
    protected $signature = 'crm:import-guinea-prospects
                            {path? : Chemin du CSV}
                            {--dry-run : Afficher sans écrire}';

    protected $description = 'Import the Guinea top-15 prospect strategy CSV into platform_prospects (UTF-8).';

    public function handle(): int
    {
        $path = $this->argument('path')
            ?: 'C:\\Users\\Duplex Kamdjou\\Downloads\\top15_strategie_approche.csv';

        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Impossible de lire le fichier.');

            return self::FAILURE;
        }

        // Normalize encoding (Excel often exports Windows-1252 / UTF-8 BOM)
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = $this->fixMojibake($raw);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = fgetcsv($handle);
        if (! $header) {
            $this->error('CSV vide.');

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
        $rows = [];
        while (($cols = fgetcsv($handle)) !== false) {
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad($cols, count($header), null));
        }
        fclose($handle);

        $ownerId = User::query()->orderBy('id')->value('id');
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = $this->clean((string) ($row['nom'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (PlatformProspect::whereRaw('LOWER(company_name) = ?', [mb_strtolower($name)])->exists()) {
                $this->line("Skip (existe) : {$name}");
                $skipped++;

                continue;
            }

            $product = $this->detectProduct($name, $row);
            $notes = $this->buildNotes($row);
            $contact = $this->detectContact($row);

            $payload = [
                'company_name' => $name,
                'contact_name' => $contact,
                'contact_email' => null,
                'contact_phone' => null,
                'country' => 'Guinée',
                'city' => $this->detectCity($name, $row),
                'source' => 'other',
                'stage' => PlatformProspect::STAGE_LEAD,
                'product_interest' => $product,
                'expected_value' => null,
                'currency' => 'GNF',
                'probability' => PlatformProspect::defaultProbabilityForStage(PlatformProspect::STAGE_LEAD),
                'next_follow_up_at' => null,
                'notes' => $notes,
                'owner_user_id' => $ownerId,
            ];

            $this->info(($this->option('dry-run') ? '[dry] ' : '') . "{$name} · {$product}");

            if ($this->option('dry-run')) {
                $created++;

                continue;
            }

            DB::transaction(function () use ($payload, $name) {
                $prospect = PlatformProspect::create($payload);
                PlatformProspectActivity::create([
                    'platform_prospect_id' => $prospect->id,
                    'user_id' => $payload['owner_user_id'],
                    'type' => 'note',
                    'subject' => 'Import liste Guinée',
                    'body' => "Prospect importé depuis la liste stratégique top 15 ({$name}).",
                ]);
            });
            $created++;
        }

        $this->newLine();
        $this->info("Terminé : {$created} créé(s), {$skipped} ignoré(s).");

        return self::SUCCESS;
    }

    private function normalizeHeader(string $h): string
    {
        $h = $this->clean($h);
        $h = mb_strtolower($h);
        $map = [
            'nom' => 'nom',
            "angle d'approche" => 'angle',
            'problème probable' => 'probleme',
            'probleme probable' => 'probleme',
            'argument de vente' => 'argument',
            'fonctionnalité bproo à mettre en avant' => 'fonctionnalite',
            'fonctionnalite bproo a mettre en avant' => 'fonctionnalite',
            'objection probable' => 'objection',
            "réponse à l'objection" => 'reponse',
            "reponse a l'objection" => 'reponse',
        ];

        return $map[$h] ?? preg_replace('/[^a-z0-9]+/u', '_', $h);
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = trim($value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return $this->fixMojibake($value);
    }

    /**
     * Fix common UTF-8 mojibake (Ã© → é, etc.).
     */
    private function fixMojibake(string $value): string
    {
        if (! str_contains($value, 'Ã') && ! str_contains($value, 'Â')) {
            return $value;
        }

        $fixed = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $try = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
        if (is_string($try) && $try !== '' && ! str_contains($try, 'Ã')) {
            return $try;
        }

        $map = [
            'Ã©' => 'é', 'Ã¨' => 'è', 'Ãª' => 'ê', 'Ã«' => 'ë',
            'Ã ' => 'à', 'Ã¢' => 'â', 'Ã¤' => 'ä',
            'Ã®' => 'î', 'Ã¯' => 'ï',
            'Ã´' => 'ô', 'Ã¶' => 'ö',
            'Ã¹' => 'ù', 'Ã»' => 'û', 'Ã¼' => 'ü',
            'Ã§' => 'ç', 'Ã‰' => 'É', 'Ãˆ' => 'È', 'Ã€' => 'À',
            'â€™' => "'", 'â€œ' => '"', 'â€' => '"', 'â€“' => '–', 'â€”' => '—',
            'Â ' => ' ', 'Â·' => '·',
        ];

        return strtr($value, $map);
    }

    private function detectProduct(string $name, array $row): string
    {
        $blob = mb_strtolower($name.' '.($row['fonctionnalite'] ?? '').' '.($row['angle'] ?? '').' '.($row['argument'] ?? ''));

        if (str_contains($blob, 'pressing') || str_contains($blob, 'cleaner') || str_contains($blob, 'xpress') || str_contains($blob, 'élégance') || str_contains($blob, 'elegance') || str_contains($blob, 'étiquet') || str_contains($blob, 'etiquet')) {
            return 'pressing';
        }
        if (str_contains($blob, 'bat') || str_contains($blob, 'chantier') || str_contains($blob, 'btp') || str_contains($blob, 'travaux') || str_contains($blob, 'matériel') || str_contains($blob, 'materiel') || str_contains($blob, 'contractor')) {
            return 'bat';
        }

        return 'erp';
    }

    private function detectCity(string $name, array $row): ?string
    {
        $blob = mb_strtolower($name.' '.($row['angle'] ?? '').' '.($row['argument'] ?? ''));
        if (str_contains($blob, 'kamsar') || str_contains($blob, 'boké') || str_contains($blob, 'boke')) {
            return 'Conakry / Kamsar';
        }
        if (str_contains($blob, 'conakry')) {
            return 'Conakry';
        }

        return 'Conakry';
    }

    private function detectContact(array $row): ?string
    {
        $angle = (string) ($row['angle'] ?? '');
        if (preg_match('/Kerfalla\s+Camara/ui', $angle, $m)) {
            return $m[0];
        }

        return null;
    }

    private function buildNotes(array $row): string
    {
        $parts = [
            'Angle' => $this->clean((string) ($row['angle'] ?? '')),
            'Problème probable' => $this->clean((string) ($row['probleme'] ?? '')),
            'Argument de vente' => $this->clean((string) ($row['argument'] ?? '')),
            'Fonctionnalité Bproo' => $this->clean((string) ($row['fonctionnalite'] ?? '')),
            'Objection probable' => $this->clean((string) ($row['objection'] ?? '')),
            "Réponse à l'objection" => $this->clean((string) ($row['reponse'] ?? '')),
        ];

        $lines = ["[Stratégie d'approche — Guinée]"];
        foreach ($parts as $label => $value) {
            if ($value !== '') {
                $lines[] = "{$label} : {$value}";
            }
        }

        return implode("\n\n", $lines);
    }
}
