<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePharmaTestCasesPdf extends Command
{
    protected $signature = 'docs:pharma-test-cases-pdf
                            {--output=docs/pdf/CAS-DE-TEST-BPROO-PHARMA.pdf : Chemin du PDF généré}';

    protected $description = 'Génère le PDF des cas de test complets Bproo Pharma (tous scénarios).';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        $directory = dirname($output);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdf = Pdf::loadView('docs.cas-de-test-pharma-pdf', [
            'generatedAt' => now()->format('d/m/Y H:i'),
            'appName' => config('app.name', 'Bproo Pharma'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ])->setPaper('a4', 'portrait');

        File::put($output, $pdf->output());

        $this->info("PDF généré : {$output}");

        return self::SUCCESS;
    }
}
