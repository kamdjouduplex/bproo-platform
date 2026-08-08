<?php

namespace App\Console\Commands;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportConakryProspectsMarkdown extends Command
{
    protected $signature = 'crm:import-conakry-md
                            {path? : Chemin du fichier markdown}
                            {--dry-run : Afficher sans écrire}';

    protected $description = 'Import Conakry B2B prospects from a Compass markdown artifact (UTF-8).';

    public function handle(): int
    {
        $path = $this->argument('path')
            ?: 'C:\\Users\\Duplex Kamdjou\\Downloads\\compass_artifact_wf-cbfca1cf-bb45-5906-987d-a45998d8d549_text_markdown.md';

        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Lecture impossible.');

            return self::FAILURE;
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $prospects = $this->parseMarkdown($raw);
        $this->info(count($prospects).' ligne(s) parsée(s).');

        $ownerId = User::query()->orderBy('id')->value('id');
        $created = 0;
        $skipped = 0;

        foreach ($prospects as $row) {
            $name = $row['company_name'];
            if (PlatformProspect::whereRaw('LOWER(company_name) = ?', [mb_strtolower($name)])->exists()) {
                $skipped++;

                continue;
            }

            $label = "{$name} · {$row['product_interest']}";
            if ($this->option('dry-run')) {
                $this->line('[dry] '.$label);
                $created++;

                continue;
            }

            DB::transaction(function () use ($row, $ownerId) {
                $prospect = PlatformProspect::create([
                    ...$row,
                    'source' => 'other',
                    'stage' => PlatformProspect::STAGE_LEAD,
                    'currency' => 'GNF',
                    'probability' => PlatformProspect::defaultProbabilityForStage(PlatformProspect::STAGE_LEAD),
                    'country' => 'Guinée',
                    'owner_user_id' => $ownerId,
                ]);

                PlatformProspectActivity::create([
                    'platform_prospect_id' => $prospect->id,
                    'user_id' => $ownerId,
                    'type' => 'note',
                    'subject' => 'Import Conakry (Go Africa Online)',
                    'body' => 'Prospect importé depuis la liste Compass Conakry 160+.',
                ]);
            });

            $created++;
            if ($created % 25 === 0) {
                $this->line("… {$created} créés");
            }
        }

        $this->newLine();
        $this->info("Terminé : {$created} créé(s), {$skipped} déjà existant(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseMarkdown(string $raw): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $sectionProduct = 'erp';
        $sectionLabel = '';
        $headers = [];
        $out = [];
        $seen = [];

        foreach ($lines as $line) {
            $trim = trim($line);

            if (preg_match('/^###\s+\d+\.\s+(.+)$/u', $trim, $m)) {
                $sectionLabel = $this->clean($m[1]);
                $sectionProduct = $this->productFromSection($sectionLabel);
                $headers = [];

                continue;
            }

            if (! str_starts_with($trim, '|')) {
                continue;
            }

            $cells = $this->splitRow($trim);
            if ($cells === []) {
                continue;
            }

            // Separator row
            if (preg_match('/^[\s|:\-]+$/u', $trim)) {
                continue;
            }

            // Header row
            $first = mb_strtolower($cells[0] ?? '');
            if ($first === 'nom') {
                $headers = array_map(fn ($h) => $this->normalizeHeader($h), $cells);

                continue;
            }

            if ($headers === []) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = $this->clean((string) ($cells[$i] ?? ''));
            }

            $name = $assoc['nom'] ?? '';
            if ($name === '' || mb_strtolower($name) === 'nom') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                // Keep both sites for multi-site (append marker already in name)
                if (! str_contains($name, '2e site') && ! str_contains($name, '—')) {
                    continue;
                }
            }
            $seen[$key] = true;

            $product = $this->mapLogiciel($assoc['logiciel'] ?? '') ?: $sectionProduct;
            $phone = $this->nullable($assoc['telephone'] ?? '');
            $email = $this->nullable($assoc['email'] ?? '');
            $contact = $this->nullable($assoc['decideur'] ?? '');
            $city = $this->nullable($assoc['commune'] ?? '') ?: 'Conakry';
            $address = $this->nullable($assoc['adresse'] ?? '');
            $sector = $this->nullable($assoc['secteur'] ?? ($assoc['type'] ?? $sectionLabel));
            $size = $this->nullable($assoc['taille'] ?? ($assoc['taille_estimee'] ?? ''));
            $why = $this->nullable($assoc['pourquoi'] ?? ($assoc['pourquoi_prospect'] ?? ''));

            $notes = $this->buildNotes([
                'Secteur' => $sector,
                'Section' => $sectionLabel,
                'Taille estimée' => $size,
                'Adresse' => $address,
                'Site' => $this->nullable($assoc['site'] ?? ($assoc['site_web'] ?? '')),
                'Facebook' => $this->nullable($assoc['facebook'] ?? ''),
                'LinkedIn' => $this->nullable($assoc['linkedin'] ?? ''),
                'Fonction décideur' => $this->nullable($assoc['fonction'] ?? ''),
                'Pourquoi prospect' => $why,
                'Logiciel cible (source)' => $this->nullable($assoc['logiciel'] ?? '') ?: strtoupper($product),
            ]);

            $out[] = [
                'company_name' => $name,
                'contact_name' => $contact,
                'contact_email' => $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'contact_phone' => $phone,
                'city' => $city,
                'product_interest' => $product,
                'notes' => $notes,
                'expected_value' => null,
                'next_follow_up_at' => null,
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private function splitRow(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        $parts = array_map(fn ($p) => trim($p), explode('|', $line));

        return $parts;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower($this->clean($h));
        $h = strtr($h, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
        ]);
        $h = preg_replace('/[^a-z0-9]+/u', '_', $h) ?? $h;
        $h = trim($h, '_');

        $map = [
            'nom' => 'nom',
            'secteur' => 'secteur',
            'logiciel' => 'logiciel',
            'taille' => 'taille',
            'taille_estimee' => 'taille',
            'commune' => 'commune',
            'adresse' => 'adresse',
            'telephone' => 'telephone',
            'email' => 'email',
            'site' => 'site',
            'site_web' => 'site',
            'facebook' => 'facebook',
            'linkedin' => 'linkedin',
            'decideur' => 'decideur',
            'fonction' => 'fonction',
            'pourquoi_prospect' => 'pourquoi',
            'pourquoi' => 'pourquoi',
            'type' => 'type',
        ];

        return $map[$h] ?? $h;
    }

    private function productFromSection(string $section): string
    {
        $s = mb_strtolower($section);
        if (str_contains($s, 'pressing')) {
            return 'pressing';
        }
        if (str_contains($s, 'bat') || str_contains($s, 'btp') || str_contains($s, 'architecture') || str_contains($s, 'promoteur') || str_contains($s, 'métallique') || str_contains($s, 'metallique')) {
            return 'bat';
        }
        // POS / pharmacies / boulangeries / restos / stations → ERP host for now
        return 'erp';
    }

    private function mapLogiciel(string $logiciel): ?string
    {
        $l = mb_strtoupper(trim($logiciel));
        if ($l === '') {
            return null;
        }
        if (str_contains($l, 'PRESS')) {
            return 'pressing';
        }
        if (str_contains($l, 'BAT')) {
            return 'bat';
        }
        if (str_contains($l, 'ERP') || str_contains($l, 'POS')) {
            return 'erp';
        }

        return null;
    }

    private function nullable(string $value): ?string
    {
        $value = $this->clean($value);
        if ($value === '' || preg_match('/^non trouv/ui', $value)) {
            return null;
        }

        return $value;
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = trim($value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return $value;
    }

    /** @param array<string, ?string> $parts */
    private function buildNotes(array $parts): string
    {
        $lines = ['[Import Conakry — Go Africa Online]'];
        foreach ($parts as $label => $value) {
            if ($value) {
                $lines[] = "{$label} : {$value}";
            }
        }

        return implode("\n\n", $lines);
    }
}
