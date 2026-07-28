<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateUpdatesManualPdf extends Command
{
    protected $signature = 'docs:updates-manual-pdf
                            {--output=docs/pdf/MANUEL-MISES-A-JOUR.pdf : Chemin du PDF généré}';

    protected $description = 'Génère le PDF du manuel des nouvelles fonctionnalités (juin 2026).';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        $directory = dirname($output);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdf = Pdf::loadView('docs.manuel-mises-a-jour-pdf')
            ->setPaper('a4', 'portrait');

        File::put($output, $pdf->output());

        $this->info("PDF généré : {$output}");

        return self::SUCCESS;
    }
}
