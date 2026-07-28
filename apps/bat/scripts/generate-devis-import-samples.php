<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$dir = __DIR__ . '/../storage/app/samples/devis-import';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// ── Exemple 1 : format BTP classique (colonnes standard FR) ─────────────────
$s1 = new Spreadsheet();
$sheet = $s1->getActiveSheet();
$sheet->setTitle('Devis');

$sheet->setCellValue('A1', 'DEVIS CLIENT — Construction Résidence Akwa');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$headers1 = ['Désignation', 'Qté', 'Unité', 'PU HT', 'Remise %', 'Montant HT'];
foreach ($headers1 as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . '3', $h);
}
$sheet->getStyle('A3:F3')->getFont()->setBold(true);
$sheet->getStyle('A3:F3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

$rows1 = [
    ['LOT 1 — GROS ŒUVRE', '', '', '', '', ''],
    ['Fondations en béton armé', 1, 'Forfait', 4500000, 0, 4500000],
    ['Élévation murs RDC (parpaing 15)', 320, 'm²', 35000, 0, 11200000],
    ['Dalle de compression RDC', 180, 'm²', 28000, 0, 5040000],
    ['Élévation murs R+1', 320, 'm²', 35000, 5, 10640000],
    ['Dalle R+1', 180, 'm²', 28000, 0, 5040000],
    ['LOT 2 — TOITURE', '', '', '', '', ''],
    ['Charpente métallique', 1, 'Forfait', 3200000, 0, 3200000],
    ['Couverture tôle bac acier', 245, 'm²', 18500, 0, 4532500],
    ['Zinguerie et évacuations EP', 1, 'Forfait', 850000, 0, 850000],
    ['LOT 3 — SECOND ŒUVRE', '', '', '', '', ''],
    ['Menuiseries aluminium', 28, 'u', 285000, 0, 7980000],
    ['Plomberie sanitaire complète', 1, 'Forfait', 2100000, 0, 2100000],
    ['Installation électrique bâtiment', 1, 'Forfait', 1850000, 0, 1850000],
    ['', '', '', '', '', ''],
    ['TOTAL HT', '', '', '', '', 57942500],
    ['TVA 19,25%', '', '', '', '', 11153931],
    ['TOTAL TTC', '', '', '', '', 69096431],
];

foreach ($rows1 as $r => $row) {
    foreach ($row as $c => $val) {
        $sheet->setCellValue([$c + 1, $r + 4], $val);
    }
}

foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

(new Xlsx($s1))->save($dir . '/exemple-1-format-btp-classique.xlsx');

// ── Exemple 2 : format client atypique (colonnes différentes) ───────────────
$s2 = new Spreadsheet();
$sheet2 = $s2->getActiveSheet();
$sheet2->setTitle('Proposition');

$sheet2->setCellValue('A1', 'Société');
$sheet2->setCellValue('B1', 'BIMEX SARL');
$sheet2->setCellValue('A2', 'Projet');
$sheet2->setCellValue('B2', 'Rénovation bureaux Bonanjo');

$headers2 = ['Libellé prestation', 'Nombre', 'U', 'Tarif unitaire', 'Prix total'];
foreach ($headers2 as $i => $h) {
    $sheet2->setCellValue([$i + 1, 5], $h);
}
$sheet2->getStyle('A5:E5')->getFont()->setBold(true);

$rows2 = [
    ['Dépose cloisonnement existant', 120, 'm²', '8 500', '1 020 000'],
    ['Cloisons BA13 sur ossature métallique', 95, 'm²', '42 000', '3 990 000'],
    ['Faux plafond dalles minérales', 180, 'm²', '28 500', '5 130 000'],
    ['Peinture murs et plafonds (2 couches)', 420, 'm²', '6 200', '2 604 000'],
    ['Sol PVC commercial', 165, 'm²', '35 000', '5 775 000'],
    ['Mise aux normes électrique (prises RJ45)', 45, 'u', '125 000', '5 625 000'],
    ['Climatisation split 3.5 kW', 8, 'u', '485 000', '3 880 000'],
    ['Nettoyage fin de chantier', 1, 'forfait', '350 000', '350 000'],
    ['', '', '', '', ''],
    ['SOUS-TOTAL', '', '', '', '28 374 000'],
    ['NET À PAYER HT', '', '', '', '28 374 000'],
];

foreach ($rows2 as $r => $row) {
    foreach ($row as $c => $val) {
        $sheet2->setCellValue([$c + 1, $r + 6], $val);
    }
}

foreach (range('A', 'E') as $col) {
    $sheet2->getColumnDimension($col)->setAutoSize(true);
}

(new Xlsx($s2))->save($dir . '/exemple-2-format-client-atypique.xlsx');

echo "Fichiers générés dans :\n  {$dir}\n";
echo "  - exemple-1-format-btp-classique.xlsx\n";
echo "  - exemple-2-format-client-atypique.xlsx\n";
