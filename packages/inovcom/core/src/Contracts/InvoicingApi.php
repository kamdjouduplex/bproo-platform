<?php

namespace InovCom\Kernel\Contracts;

/**
 * API contract for Invoicing module
 *
 * Lets reports and other modules read invoice identity, totals, receivables,
 * payments, tax, and deliveries without depending on ERP vs BAT schemas.
 */
interface InvoicingApi
{
    /**
     * Find an invoice by ID.
     */
    public function findInvoice(int $id): ?object;

    /**
     * Find an invoice by business number (ERP: invoice_number, BAT: code).
     */
    public function findInvoiceByNumber(string $number): ?object;

    /**
     * Check if an invoice exists.
     */
    public function invoiceExists(int $id): bool;

    /**
     * Remaining balance due on an invoice (0 when fully paid / cancelled).
     */
    public function getBalance(int $invoiceId): float;

    /**
     * Invoice grand total (ERP: total, BAT: total_ttc).
     */
    public function getTotal(int $invoiceId): float;

    /**
     * Sum of open balances for a client.
     */
    public function getClientOutstandingBalance(int $clientId): float;

    /**
     * Period invoice KPIs for reporting.
     *
     * @return array{
     *   issued_count: int,
     *   issued_total: float,
     *   draft_count: int,
     *   paid_count: int,
     *   paid_total: float,
     *   partial_count: int,
     *   partial_balance: float,
     *   outstanding_balance: float,
     *   cancelled_count: int,
     *   by_status: array<string, array{count: int, total: float}>
     * }
     */
    public function getPeriodSummary(string $startDate, string $endDate): array;

    /**
     * Top open invoices by balance.
     *
     * @return array<int, array{
     *   invoice_number: string,
     *   client_name: string,
     *   balance: float,
     *   due_date: string|null,
     *   status: string
     * }>
     */
    public function getTopOutstandingInvoices(int $limit = 10): array;

    /**
     * Per-client issued invoice revenue in a period.
     *
     * @return array<int, array{
     *   client_id: int,
     *   client_name: string,
     *   invoice_revenue: float,
     *   invoice_count: int
     * }>
     */
    public function getClientRevenueInPeriod(string $startDate, string $endDate): array;

    /**
     * Tax / HT / TTC breakdown for issued invoices in a period.
     *
     * @return array{
     *   ca_ht: float,
     *   tva_total: float,
     *   other_taxes_total: float,
     *   ca_ttc: float,
     *   by_tax: array<int, array{name: string, amount: float}>
     * }
     */
    public function getTaxBreakdown(string $startDate, string $endDate): array;

    /**
     * Sum of invoice payments recorded in a period.
     */
    public function getPaymentsTotal(string $startDate, string $endDate): float;

    /**
     * Invoice payments grouped by payment method.
     *
     * @return array<int, array{method: string, method_label: string, total: float, count: int}>
     */
    public function getPaymentsByMethod(string $startDate, string $endDate): array;

    /**
     * Delivery notes summary for a period (ERP). BAT returns zeros when N/A.
     *
     * @return array{confirmed_count: int, draft_count: int}
     */
    public function getDeliveriesSummary(string $startDate, string $endDate): array;

    /**
     * Issued invoices for explorer / listing UIs.
     *
     * @return array<int, array{
     *   invoice_number: string,
     *   invoice_date: string|null,
     *   status: string,
     *   client_name: string|null,
     *   subtotal: float,
     *   discount_amount: float,
     *   tax_amount: float,
     *   total: float,
     *   balance: float
     * }>
     */
    public function listIssuedInvoices(
        string $startDate,
        string $endDate,
        ?int $clientId = null,
        int $limit = 100
    ): array;

    /**
     * Distinct quotes/quotations converted to invoices in the period.
     */
    public function countConvertedQuotesInPeriod(string $startDate, string $endDate): int;
}
