<?php

namespace InovCom\Invoicing\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\Invoice;

class CollectionReminderService
{
    /**
     * @param  array{
     *   client_id?: int|string|null,
     *   invoice_date_from?: string|null,
     *   invoice_date_to?: string|null,
     *   due_date_from?: string|null,
     *   due_date_to?: string|null,
     *   min_days_overdue?: int,
     *   commercial_id?: int|string|null,
     *   client_search?: string|null,
     *   payment_status?: string|null,
     * }  $filters
     * @return Collection<int, array{
     *   client: \InovCom\Clients\Models\Client,
     *   invoices: Collection,
     *   total_invoiced: float,
     *   total_paid: float,
     *   total_balance: float,
     * }>
     */
    public function groupedOverdueInvoices(array $filters = []): Collection
    {
        $today = Carbon::today();

        $query = Invoice::query()
            ->with(['client', 'creator'])
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->where(function ($q) {
                $q->whereNull('document_type')
                    ->orWhereIn('document_type', ['standard', 'replacement']);
            });

        if (Schema::connection('tenant')->hasColumn('invoices', 'document_type')) {
            $query->where('document_type', '!=', 'cancellation');
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (!empty($filters['invoice_date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['invoice_date_from']);
        }

        if (!empty($filters['invoice_date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['invoice_date_to']);
        }

        if (!empty($filters['commercial_id'])) {
            $query->where('created_by', (int) $filters['commercial_id']);
        }

        $paymentStatus = $filters['payment_status'] ?? 'all';
        if (in_array($paymentStatus, ['issued', 'partial'], true)) {
            $query->where('status', $paymentStatus);
        }

        $invoices = $query->orderBy('client_id')->orderBy('invoice_date')->get();

        $rows = $invoices->map(function (Invoice $invoice) use ($today, $filters) {
            $dueDate = $invoice->due_date ?? $invoice->invoice_date;
            if (!$dueDate) {
                return null;
            }

            $due = Carbon::parse($dueDate)->startOfDay();
            if ($due->gte($today)) {
                return null;
            }

            $daysOverdue = max(0, (int) $due->diffInDays($today));

            if (!empty($filters['due_date_from']) && $due->lt(Carbon::parse($filters['due_date_from']))) {
                return null;
            }

            if (!empty($filters['due_date_to']) && $due->gt(Carbon::parse($filters['due_date_to']))) {
                return null;
            }

            $minDays = (int) ($filters['min_days_overdue'] ?? 0);
            if ($daysOverdue < $minDays) {
                return null;
            }

            return [
                'invoice' => $invoice,
                'days_overdue' => $daysOverdue,
                'due_date' => $due,
            ];
        })->filter();

        if (!empty($filters['client_search'])) {
            $search = mb_strtolower(trim($filters['client_search']));
            $rows = $rows->filter(function ($row) use ($search) {
                $client = $row['invoice']->client;
                if (!$client) {
                    return false;
                }

                return str_contains(mb_strtolower($client->name), $search)
                    || str_contains(mb_strtolower($client->code ?? ''), $search);
            });
        }

        return $rows
            ->groupBy(fn ($row) => $row['invoice']->client_id)
            ->map(function (Collection $clientRows) {
                $first = $clientRows->first();
                $client = $first['invoice']->client;

                $invoiceRows = $clientRows->map(fn ($r) => [
                    'invoice' => $r['invoice'],
                    'days_overdue' => $r['days_overdue'],
                    'due_date' => $r['due_date'],
                ])->values();

                return [
                    'client' => $client,
                    'invoices' => $invoiceRows,
                    'total_invoiced' => round($invoiceRows->sum(fn ($r) => (float) $r['invoice']->total), 2),
                    'total_paid' => round($invoiceRows->sum(fn ($r) => (float) $r['invoice']->amount_paid), 2),
                    'total_balance' => round($invoiceRows->sum(fn ($r) => (float) $r['invoice']->balance), 2),
                ];
            })
            ->filter(fn ($g) => $g['client'] !== null)
            ->sortBy(fn ($g) => $g['client']->name)
            ->values();
    }

    /**
     * @return array{total_invoiced: float, total_paid: float, total_balance: float, invoice_count: int, client_count: int}
     */
    public function globalTotals(Collection $groups): array
    {
        return [
            'total_invoiced' => round($groups->sum('total_invoiced'), 2),
            'total_paid' => round($groups->sum('total_paid'), 2),
            'total_balance' => round($groups->sum('total_balance'), 2),
            'invoice_count' => (int) $groups->sum(fn ($g) => $g['invoices']->count()),
            'client_count' => $groups->count(),
        ];
    }

    public function generateLetterReference(?int $clientId = null): string
    {
        $year = now()->year;
        $suffix = $clientId ? str_pad((string) $clientId, 4, '0', STR_PAD_LEFT) : '0000';

        return 'REL-' . $year . '-' . now()->format('md') . '-' . $suffix;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filtersFromRequest(array $input): array
    {
        return [
            'client_id' => $input['client_id'] ?? $input['clientFilter'] ?? null,
            'invoice_date_from' => $input['invoice_date_from'] ?? $input['invoiceDateFrom'] ?? null,
            'invoice_date_to' => $input['invoice_date_to'] ?? $input['invoiceDateTo'] ?? null,
            'due_date_from' => $input['due_date_from'] ?? $input['dueDateFrom'] ?? null,
            'due_date_to' => $input['due_date_to'] ?? $input['dueDateTo'] ?? null,
            'min_days_overdue' => (int) ($input['min_days_overdue'] ?? $input['minDaysOverdue'] ?? 0),
            'commercial_id' => $input['commercial_id'] ?? $input['commercialFilter'] ?? null,
            'client_search' => $input['client_search'] ?? $input['clientSearch'] ?? null,
            'payment_status' => $input['payment_status'] ?? $input['paymentStatusFilter'] ?? 'all',
        ];
    }
}
