<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateCrmScenariosGuidePdf extends Command
{
    protected $signature = 'docs:crm-scenarios-pdf
                            {--output=docs/pdf/GUIDE-CRM-SCENARIOS.pdf : Path of the generated PDF}';

    protected $description = 'Generate the Relation client CRM scenarios PDF guide.';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        $directory = dirname($output);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdf = Pdf::loadView('docs.guide-crm-scenarios-pdf')
            ->setPaper('a4', 'portrait');

        File::put($output, $pdf->output());

        $this->info("PDF generated: {$output}");

        return self::SUCCESS;
    }
}
