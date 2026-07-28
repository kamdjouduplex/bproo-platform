<?php

namespace InovCom\Invoicing\Exports;

use Illuminate\Support\Collection;
use InovCom\Caisse\Exports\CaisseExcelExporter;

class CollectionReminderExcelExporter
{
    /**
     * @param  Collection<int, array{client: mixed, invoices: Collection, total_balance: float}>  $groups
     */
    public static function download(string $filename, Collection $groups, array $globalTotals): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Client',
            'N° facture',
            'Date facture',
            'Date échéance',
            'Jours retard',
            'Montant TTC',
            'Encaissé',
            'Solde',
            'Réf. client',
            'BL',
        ];

        $rows = [];
        foreach ($groups as $group) {
            $client = $group['client'];
            foreach ($group['invoices'] as $row) {
                $inv = $row['invoice'];
                $rows[] = [
                    $client->name ?? '—',
                    $inv->invoice_number,
                    $inv->invoice_date?->format('d/m/Y'),
                    ($row['due_date'] ?? $inv->due_date)?->format('d/m/Y') ?? '—',
                    'Echue depuis ' . $row['days_overdue'] . ' jours',
                    fmt_money((float) $inv->total),
                    fmt_money((float) $inv->amount_paid),
                    fmt_money((float) $inv->balance),
                    $inv->customer_reference ?? '',
                    $inv->delivery_note_number ?? '',
                ];
            }
            $rows[] = [
                $client->name ?? '—',
                'TOTAL CLIENT',
                '',
                '',
                '',
                '',
                '',
                fmt_money((float) $group['total_balance']),
                '',
                '',
            ];
        }

        $rows[] = [
            'TOTAL GÉNÉRAL',
            '',
            '',
            '',
            '',
            fmt_money($globalTotals['total_invoiced']),
            fmt_money($globalTotals['total_paid']),
            fmt_money($globalTotals['total_balance']),
            '',
            '',
        ];

        return CaisseExcelExporter::download(
            $filename,
            $headers,
            $rows,
            'Relance factures impayées — ' . now()->format('d/m/Y')
        );
    }
}
