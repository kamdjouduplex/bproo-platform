<?php

namespace InovCom\Items\Http\Livewire;

use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\Services\ItemsImportService;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemsImport extends Component
{
    use AuthorizesItemAccess;
    use WithFileUploads;

    public $importFile = null;

    /** @var list<array<string,mixed>> */
    public array $previewRows = [];

    /** @var list<string> */
    public array $parseErrors = [];

    public bool $showPreview = false;

    public function mount(): void
    {
        if (! $this->canItem('items.create')) {
            abort(403);
        }
    }

    public function downloadTemplate()
    {
        $service = app(ItemsImportService::class);
        $headers = $service->templateHeaders();
        $examples = $service->templateExampleRows();
        $filename = 'modele-import-'.(items_is_pharmacy_catalog() ? 'medicaments' : 'articles').'.xlsx';

        if (class_exists(Spreadsheet::class)) {
            return $this->downloadXlsx($headers, $examples, $filename);
        }

        return $this->downloadCsv($headers, $examples, str_replace('.xlsx', '.csv', $filename));
    }

    public function analyze(): void
    {
        if (! $this->canItem('items.create')) {
            notify()->error('Permission refusée.');

            return;
        }

        $this->validate([
            'importFile' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/excel,application/octet-stream,application/zip,text/csv,text/plain,application/csv,text/comma-separated-values',
            ],
        ], [
            'importFile.required' => 'Choisissez un fichier Excel ou CSV.',
            'importFile.file' => 'Choisissez un fichier Excel ou CSV.',
            'importFile.mimetypes' => 'Formats acceptés : .xlsx, .xls, .csv',
        ]);

        $file = $this->importFile;
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            $this->addError('importFile', 'Formats acceptés : .xlsx, .xls, .csv');

            return;
        }
        $path = $file->getRealPath();

        try {
            $parsed = app(ItemsImportService::class)->parse($path, $ext);
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
            $this->showPreview = false;
            $this->previewRows = [];
            $this->parseErrors = [$e->getMessage()];

            return;
        }

        $this->previewRows = $parsed['rows'];
        $this->parseErrors = $parsed['errors'];
        $this->showPreview = true;

        $ok = collect($this->previewRows)->where('status', 'ok')->count();
        $err = collect($this->previewRows)->where('status', 'error')->count();
        notify()->success("Analyse terminée : {$ok} ligne(s) prête(s), {$err} en erreur.");
    }

    public function commitImport(): void
    {
        if (! $this->canItem('items.create')) {
            notify()->error('Permission refusée.');

            return;
        }

        if ($this->previewRows === []) {
            notify()->error('Analysez d’abord un fichier.');

            return;
        }

        $okRows = array_values(array_filter(
            $this->previewRows,
            fn ($r) => ($r['status'] ?? '') === 'ok'
        ));

        if ($okRows === []) {
            notify()->error('Aucune ligne valide à importer.');

            return;
        }

        $result = app(ItemsImportService::class)->import($okRows, auth('tenant')->id());

        $msg = $result['created'].' créé(s)';
        if ($result['stocked'] > 0) {
            $msg .= ', '.$result['stocked'].' avec stock';
        }
        if ($result['skipped'] > 0) {
            $msg .= ', '.$result['skipped'].' ignoré(s)';
        }
        notify()->success('Import terminé : '.$msg.'.');

        if ($result['errors'] !== []) {
            notify()->error(implode(' | ', array_slice($result['errors'], 0, 3)));
        }

        $this->resetImportState();
        $this->redirect(route('tenant.items.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function resetImportState(): void
    {
        $this->importFile = null;
        $this->previewRows = [];
        $this->parseErrors = [];
        $this->showPreview = false;
    }

    public function render()
    {
        $noun = items_catalog_noun();
        $okCount = collect($this->previewRows)->where('status', 'ok')->count();
        $errorCount = collect($this->previewRows)->where('status', 'error')->count();

        return view('inovcom-items::livewire.items.import', [
            'catalogNoun' => $noun,
            'okCount' => $okCount,
            'errorCount' => $errorCount,
            'headers' => app(ItemsImportService::class)->templateHeaders(),
        ])->layout('layouts.app', [
            'title' => 'Import '.$noun['plural'],
            'subtitle' => 'Charger un fichier Excel / CSV',
        ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string,mixed>>  $examples
     */
    private function downloadXlsx(array $headers, array $examples, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        foreach ($examples as $r => $example) {
            foreach ($headers as $i => $header) {
                $sheet->setCellValue([$i + 1, $r + 2], $example[$header] ?? '');
            }
        }

        // Legend sheet
        $legend = $spreadsheet->createSheet();
        $legend->setTitle('Aide');
        $lines = [
            ['Colonne', 'Obligatoire', 'Description', 'Alias acceptés (FR)'],
            ['name', 'Oui', 'Nom du produit / médicament', 'PRODUITS, produit, désignation'],
            ['sku', 'Non', 'Référence unique (auto MED-###### si vide)', 'reference, code'],
            ['quantity', 'Non', 'Stock initial (Qté)', 'Qté, qte, quantite'],
            ['cost', 'Non', 'Prix d’achat unitaire (P.U)', 'P.U, PU, prix_achat'],
            ['price', 'Non*', 'Prix de vente unitaire (P.V.U) — 0 si vide', 'P.V.U, PVU, prix_vente'],
            ['expiry_date', 'Non', 'Date de péremption', 'DATE DE P, date_peremption (AAAA-MM-JJ)'],
            ['batch_number', 'Non', 'Numéro de lot', 'LOT, lot, n_lot'],
            ['unit', 'Non', 'Unité (créée si absente)', 'unité, Boîte, Flacon…'],
            ['barcode', 'Non', 'Code-barres', 'code_barres, EAN'],
            ['', '', '', ''],
            ['Notes', '', 'P.T et P.V.T du tableau Word ne sont pas nécessaires (calculables).', ''],
            ['', '', 'Une ligne = un article. Pas de conversion de devise.', ''],
            ['', '', 'Conservez la ligne d’en-têtes. Formats : .xlsx / .xls / .csv', ''],
        ];
        foreach ($lines as $r => $cols) {
            foreach ($cols as $c => $val) {
                $legend->setCellValue([$c + 1, $r + 1], $val);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string,mixed>>  $examples
     */
    private function downloadCsv(array $headers, array $examples, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $examples) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($examples as $example) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $example[$h] ?? '';
                }
                fputcsv($out, $line, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
