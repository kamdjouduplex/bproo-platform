<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSuperAdminGuidePdf extends Command
{
    protected $signature = 'docs:super-admin-pdf
                            {--output=docs/pdf/GUIDE-SUPER-ADMIN.pdf : Chemin du PDF généré}';

    protected $description = 'Génère le PDF du Guide Super Admin depuis la vue Blade.';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        $directory = dirname($output);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdf = Pdf::loadView('docs.guide-super-admin-pdf')
            ->setPaper('a4', 'portrait');

        File::put($output, $pdf->output());

        $this->info("PDF généré : {$output}");

        return self::SUCCESS;
    }
}
