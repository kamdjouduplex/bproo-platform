<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\InvoicingService;
use InovCom\Items\Models\Item;
use InovCom\Providers\Models\Provider;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchaseDocumentNumberService;
use InovCom\Purchases\Services\PurchasesService;
use InovCom\Quotations\Models\Quotation;
use InovCom\Quotations\Services\QuotationsService;
use InovCom\Users\Models\User;

/**
 * Documents de démo YouTube : devis, factures, encaissements (dont retenues), achats.
 *
 * Idempotent : si des factures DEMO-YTB-* existent déjà, ne recrée pas les documents.
 */
class DemoErpYoutubeSeeder extends Seeder
{
    private const VAT_RATE = 19.25;

    private const IS_RATE = 5.5;

    private ?int $userId = null;

    public function run(): void
    {
        $this->userId = User::query()->orderBy('id')->value('id');

        if (class_exists(WithholdingSchema::class)) {
            WithholdingSchema::ensure();
        }

        if ($this->alreadySeeded()) {
            $this->command?->warn('Documents démo déjà présents (références DEMO-YTB-*). Catalogue / clients mis à jour uniquement.');

            return;
        }

        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () {
            $this->seedQuotationsAndInvoices();
            $this->seedPurchases();
        });
    }

    public function resetDocuments(): void
    {
        if (Schema::connection('tenant')->hasTable('invoices')) {
            $invoices = Invoice::query()->where('customer_reference', 'like', 'DEMO-YTB-%')->get();
            foreach ($invoices as $invoice) {
                foreach ($invoice->payments as $payment) {
                    if (method_exists($payment, 'withholdings')) {
                        $payment->withholdings()->delete();
                    }
                    if (method_exists($payment, 'attachments')) {
                        $payment->attachments()->delete();
                    }
                    $payment->delete();
                }
                $invoice->lines()->delete();
                if (method_exists($invoice, 'taxLines')) {
                    $invoice->taxLines()->delete();
                }
                $invoice->delete();
            }
        }

        if (Schema::connection('tenant')->hasTable('quotations')) {
            $quotes = Quotation::query()->where('customer_purchase_order', 'like', 'BC-DEMO-%')->get();
            foreach ($quotes as $quote) {
                $quote->lines()->delete();
                if (method_exists($quote, 'taxLines')) {
                    $quote->taxLines()->delete();
                }
                $quote->delete();
            }
        }

        if (class_exists(PurchaseOrder::class) && Schema::connection('tenant')->hasTable('purchase_orders')) {
            $orders = PurchaseOrder::query()->where('notes', 'like', '%[DEMO-SEED]%')->get();
            foreach ($orders as $order) {
                $order->lines()->delete();
                $order->delete();
            }
        }
    }

    private function alreadySeeded(): bool
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return false;
        }

        return Invoice::query()->where('customer_reference', 'like', 'DEMO-YTB-%')->exists();
    }

    private function seedQuotationsAndInvoices(): void
    {
        if (! Schema::connection('tenant')->hasTable('quotations')
            || ! Schema::connection('tenant')->hasTable('invoices')) {
            $this->command?->warn('Tables devis/factures absentes — documents commerciaux ignorés.');

            return;
        }

        $quotes = app(QuotationsService::class);
        $invoices = app(InvoicingService::class);
        $payments = class_exists(InvoicePaymentsService::class)
            ? app(InvoicePaymentsService::class)
            : null;

        $kamfo = Client::query()->where('code', 'CLI-DEMO01')->first();
        $btp = Client::query()->where('code', 'CLI-DEMO02')->first();
        $garage = Client::query()->where('code', 'CLI-DEMO03')->first();
        $mines = Client::query()->where('code', 'CLI-DEMO04')->first();
        $agro = Client::query()->where('code', 'CLI-DEMO05')->first();
        $batir = Client::query()->where('code', 'CLI-DEMO06')->first();

        if (! $kamfo || ! $btp || ! $garage) {
            throw new \RuntimeException('Clients démo introuvables. Lancez d’abord le catalogue.');
        }

        $prev = now()->subMonth();
        $curr = now();

        $draft = $quotes->create([
            'client_id' => $garage->id,
            'quote_date' => $curr->copy()->subDays(2)->toDateString(),
            'valid_until' => $curr->copy()->addDays(20)->toDateString(),
            'notes' => '[DEMO-SEED] Devis en cours de rédaction',
            'customer_purchase_order' => 'BC-DEMO-BROUILLON',
            'discount_mode' => 'percent',
            'discount_percent' => 0,
            'apply_tax' => true,
            'tax_rate' => self::VAT_RATE,
            'tax_lines' => $this->vatTaxLines(),
        ], $this->lines([
            'FIL-GASOIL-PL' => 8,
            'DISQ-FR-430' => 2,
        ]), $this->userId);

        $sent = $quotes->create([
            'client_id' => $agro?->id ?? $garage->id,
            'quote_date' => $curr->copy()->subDays(6)->toDateString(),
            'valid_until' => $curr->copy()->addDays(10)->toDateString(),
            'notes' => '[DEMO-SEED] Envoyé — en attente de réponse',
            'customer_purchase_order' => 'BC-DEMO-ENVOYE',
            'discount_mode' => 'percent',
            'discount_percent' => 0,
            'apply_tax' => true,
            'tax_rate' => self::VAT_RATE,
            'tax_lines' => $this->vatTaxLines(),
        ], $this->lines([
            'KIT-EMB-PL' => 1,
            'DISQ-FR-430' => 4,
        ]), $this->userId);
        $quotes->setStatus($sent, 'sent', $this->userId);

        $rejected = $quotes->create([
            'client_id' => $btp->id,
            'quote_date' => $prev->copy()->day(18)->toDateString(),
            'valid_until' => $prev->copy()->day(28)->toDateString(),
            'notes' => '[DEMO-SEED] Client a choisi un autre fournisseur',
            'customer_purchase_order' => 'BC-DEMO-REFUS',
            'discount_mode' => 'percent',
            'discount_percent' => 0,
            'apply_tax' => true,
            'tax_rate' => self::VAT_RATE,
            'tax_lines' => $this->vatTaxLines(),
        ], $this->lines([
            'TURBO-DIESEL-6C' => 1,
        ]), $this->userId);
        $quotes->setStatus($rejected, 'sent', $this->userId);
        $quotes->setStatus($rejected, 'rejected', $this->userId);

        $acceptedOpen = $quotes->create([
            'client_id' => $batir?->id ?? $kamfo->id,
            'quote_date' => $curr->copy()->subDays(4)->toDateString(),
            'valid_until' => $curr->copy()->addDays(15)->toDateString(),
            'notes' => '[DEMO-SEED] Accepté — à facturer en démo live',
            'customer_purchase_order' => 'BC-DEMO-A-FACTURER',
            'discount_mode' => 'percent',
            'discount_percent' => 0,
            'apply_tax' => true,
            'tax_rate' => self::VAT_RATE,
            'tax_lines' => $this->vatTaxLines(),
        ], $this->lines([
            'FIL-HUI-CAT15' => 12,
            'FIL-AIR-QSX' => 6,
            'COUR-DIST-EC' => 4,
        ]), $this->userId);
        $quotes->setStatus($acceptedOpen, 'sent', $this->userId);
        $quotes->setStatus($acceptedOpen, 'accepted', $this->userId);

        $acceptedInvoiced = $quotes->create([
            'client_id' => $kamfo->id,
            'quote_date' => $prev->copy()->day(3)->toDateString(),
            'valid_until' => $prev->copy()->day(20)->toDateString(),
            'notes' => '[DEMO-SEED] Accepté puis facturé',
            'customer_purchase_order' => 'BC-DEMO-45001',
            'discount_mode' => 'percent',
            'discount_percent' => 0,
            'apply_tax' => true,
            'tax_rate' => self::VAT_RATE,
            'tax_lines' => $this->vatTaxLines(),
        ], $this->lines([
            'FIL-HUI-CAT15' => 10,
            'FIL-AIR-QSX' => 6,
            'POMPE-EAU-ISX' => 2,
        ]), $this->userId);
        $quotes->setStatus($acceptedInvoiced, 'sent', $this->userId);
        $quotes->setStatus($acceptedInvoiced, 'accepted', $this->userId);

        $invFromQuote = $this->snapToFrancs($invoices->createFromQuotation($acceptedInvoiced, [
            'declaration_type' => 'declared',
            'invoice_date' => $prev->copy()->day(8)->toDateString(),
            'due_date' => $prev->copy()->day(28)->toDateString(),
            'customer_reference' => 'DEMO-YTB-01',
            'delivery_note_number' => 'BL-DEMO-01',
            'notes' => '[DEMO-SEED] Facture depuis devis — payée avec TVA retenue',
            'payment_mode' => 'bank_transfer',
            'issue' => true,
        ], [], true, $this->userId));
        $this->pay($payments, $invFromQuote, $prev->copy()->day(12)->toDateString(), 'bank_transfer', 'VIR-KAMFO-0812', true, false);

        $invCash = $this->issueInvoice($invoices, [
            'client' => $garage,
            'declaration' => 'non_declared',
            'date' => $prev->copy()->day(16)->toDateString(),
            'due' => $prev->copy()->day(16)->toDateString(),
            'ref' => 'DEMO-YTB-02',
            'bl' => 'BL-DEMO-02',
            'notes' => 'Règlement comptant garage',
            'mode' => 'cash',
            'skus' => ['FIL-GASOIL-PL' => 10, 'COUR-DIST-EC' => 3],
            'withVat' => true,
        ]);
        $this->pay($payments, $invCash, $prev->copy()->day(16)->toDateString(), 'cash', null, false, false);

        $invIs = $this->issueInvoice($invoices, [
            'client' => $mines ?? $btp,
            'declaration' => 'declared',
            'date' => $prev->copy()->day(20)->toDateString(),
            'due' => $prev->copy()->addMonth()->day(5)->toDateString(),
            'ref' => 'DEMO-YTB-03',
            'bl' => 'BL-DEMO-03',
            'notes' => 'Gros chantier — TVA + IS sur facture, retenus à l’encaissement',
            'mode' => 'bank_transfer',
            'skus' => ['TURBO-DIESEL-6C' => 1, 'RAD-DIESEL-EC' => 2, 'JOINT-CUL-6C' => 2],
            'withVat' => true,
            'withIs' => true,
        ]);
        $this->pay($payments, $invIs, $prev->copy()->day(27)->toDateString(), 'bank_transfer', 'VIR-MINES-0827', true, true);

        $this->issueInvoice($invoices, [
            'client' => $btp,
            'declaration' => 'declared',
            'date' => $prev->copy()->day(10)->toDateString(),
            'due' => $prev->copy()->day(25)->toDateString(),
            'ref' => 'DEMO-YTB-04',
            'bl' => 'BL-DEMO-04',
            'notes' => 'Échue — à relancer en démo',
            'mode' => 'bank_transfer',
            'skus' => ['POMPE-INJ-PL' => 1, 'FIL-HUI-CAT15' => 4],
            'withVat' => true,
        ]);

        $invPartial = $this->issueInvoice($invoices, [
            'client' => $batir ?? $kamfo,
            'declaration' => 'declared',
            'date' => $curr->copy()->day(min(2, $curr->day))->toDateString(),
            'due' => $curr->copy()->addDays(20)->toDateString(),
            'ref' => 'DEMO-YTB-05',
            'bl' => 'BL-DEMO-05',
            'notes' => 'Acompte reçu — solde à encaisser',
            'mode' => 'bank_transfer',
            'skus' => ['KIT-EMB-PL' => 2, 'DISQ-FR-430' => 6],
            'withVat' => true,
        ]);
        if ($payments && $invPartial) {
            $acompte = max(50000, round(((float) $invPartial->total) * 0.35, 0));
            $payments->recordPayment(
                (int) $invPartial->id,
                $acompte,
                $curr->copy()->day(min(3, $curr->day))->toDateString(),
                'bank_transfer',
                'Acompte 35 % [DEMO-SEED]',
                'VIR-ACOMPTE-05',
                $this->userId,
                []
            );
        }

        $this->issueInvoice($invoices, [
            'client' => $agro ?? $garage,
            'declaration' => 'declared',
            'date' => $curr->copy()->day(min(5, $curr->day))->toDateString(),
            'due' => $curr->copy()->addDays(15)->toDateString(),
            'ref' => 'DEMO-YTB-06',
            'bl' => 'BL-DEMO-06',
            'notes' => 'À encaisser en live pendant la vidéo',
            'mode' => 'mobile_money',
            'skus' => ['FIL-AIR-QSX' => 8, 'FIL-GASOIL-PL' => 12],
            'withVat' => true,
        ]);

        $invoices->create([
            'declaration_type' => 'non_declared',
            'client_id' => $garage->id,
            'invoice_date' => $curr->copy()->toDateString(),
            'due_date' => $curr->copy()->addDays(7)->toDateString(),
            'customer_reference' => 'DEMO-YTB-07',
            'notes' => '[DEMO-SEED] Brouillon — à émettre en live',
            'payment_mode' => 'cash',
            'issue' => false,
            'tax_lines' => $this->computedTaxLines($this->lines([
                'COUR-DIST-EC' => 2,
                'FIL-GASOIL-PL' => 4,
            ]), false),
        ], $this->lines([
            'COUR-DIST-EC' => 2,
            'FIL-GASOIL-PL' => 4,
        ]), $this->userId);

        $invSeptPaid = $this->issueInvoice($invoices, [
            'client' => $kamfo,
            'declaration' => 'declared',
            'date' => $curr->copy()->day(min(4, $curr->day))->toDateString(),
            'due' => $curr->copy()->addDays(10)->toDateString(),
            'ref' => 'DEMO-YTB-08',
            'bl' => 'BL-DEMO-08',
            'notes' => 'Réglée ce mois (recap en cours)',
            'mode' => 'mobile_money',
            'skus' => ['POMPE-EAU-ISX' => 3, 'FIL-HUI-CAT15' => 6],
            'withVat' => true,
        ]);
        $this->pay($payments, $invSeptPaid, $curr->copy()->day(min(6, $curr->day))->toDateString(), 'mobile_money', 'OM-KAMFO-06', false, false);

        unset($draft);
    }

    /**
     * @param  array{
     *     client: Client,
     *     declaration: string,
     *     date: string,
     *     due: string,
     *     ref: string,
     *     bl: string,
     *     notes: string,
     *     mode: string,
     *     skus: array<string, float|int>,
     *     withVat?: bool,
     *     withIs?: bool
     * }  $spec
     */
    private function issueInvoice(InvoicingService $invoices, array $spec): Invoice
    {
        $lines = $this->lines($spec['skus']);
        $withVat = (bool) ($spec['withVat'] ?? true);
        $withIs = (bool) ($spec['withIs'] ?? false);

        return $this->snapToFrancs($invoices->create([
            'declaration_type' => $spec['declaration'],
            'client_id' => $spec['client']->id,
            'invoice_date' => $spec['date'],
            'due_date' => $spec['due'],
            'customer_reference' => $spec['ref'],
            'delivery_note_number' => $spec['bl'],
            'notes' => '[DEMO-SEED] '.$spec['notes'],
            'payment_mode' => $spec['mode'],
            'issue' => true,
            'tax_lines' => $this->computedTaxLines($lines, $withVat, $withIs),
        ], $lines, $this->userId));
    }

    private function pay(
        ?InvoicePaymentsService $payments,
        Invoice $invoice,
        string $date,
        string $method,
        ?string $externalRef,
        bool $withholdVat,
        bool $withholdIs
    ): void {
        if (! $payments) {
            return;
        }

        $invoice->refresh();
        $withholdings = [];
        if ($withholdVat) {
            $type = FiscalWithholdingType::query()->where('code', 'tva_retenue')->first();
            if ($type) {
                $withholdings[] = [
                    'type_id' => $type->id,
                    'type_name' => $type->name,
                    'amount' => 1,
                ];
            }
        }
        if ($withholdIs) {
            $type = FiscalWithholdingType::query()->where('code', 'is_retenu')->first();
            if ($type) {
                $withholdings[] = [
                    'type_id' => $type->id,
                    'type_name' => $type->name,
                    'amount' => 1,
                ];
            }
        }

        $payments->recordPayment(
            (int) $invoice->id,
            (float) $invoice->balance,
            $date,
            $method,
            'Règlement démo [DEMO-SEED]',
            $externalRef,
            $this->userId,
            $withholdings
        );
    }

    private function seedPurchases(): void
    {
        if (! class_exists(PurchasesService::class)
            || ! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return;
        }

        if (PurchaseOrder::query()->where('notes', 'like', '%[DEMO-SEED]%')->exists()) {
            return;
        }

        $local = Provider::query()->where('code', 'FOUR-DEMO02')->first();
        $cat = Provider::query()->where('code', 'FOUR-DEMO03')->first();
        if (! $local || ! $cat) {
            return;
        }

        $purchases = app(PurchasesService::class);
        $numbers = app(PurchaseDocumentNumberService::class);
        $prev = now()->subMonth();

        $confirmed = $purchases->createPurchaseOrder([
            'order_number' => $numbers->nextOrderNumber(),
            'order_date' => $prev->copy()->day(6)->toDateString(),
            'expected_date' => $prev->copy()->day(14)->toDateString(),
            'provider_id' => $local->id,
            'status' => PurchasesService::STATUS_DRAFT,
            'notes' => '[DEMO-SEED] Réassort filtres août',
            'created_by' => $this->userId,
        ]);
        foreach ($this->purchaseLines(['FIL-HUI-CAT15' => 20, 'FIL-GASOIL-PL' => 30, 'FIL-AIR-QSX' => 15]) as $line) {
            $purchases->addLineToOrder((int) $confirmed->id, $line);
        }
        $purchases->confirmOrder((int) $confirmed->id);

        $draft = $purchases->createPurchaseOrder([
            'order_number' => $numbers->nextOrderNumber(),
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(10)->toDateString(),
            'provider_id' => $cat->id,
            'status' => PurchasesService::STATUS_DRAFT,
            'notes' => '[DEMO-SEED] Commande CAT en préparation',
            'created_by' => $this->userId,
        ]);
        foreach ($this->purchaseLines(['RAD-DIESEL-EC' => 2, 'FIL-HUI-CAT15' => 10]) as $line) {
            $purchases->addLineToOrder((int) $draft->id, $line);
        }
    }

    /**
     * @param  array<string, float|int>  $skuQty
     * @return list<array<string, mixed>>
     */
    private function lines(array $skuQty): array
    {
        $rows = [];
        foreach ($skuQty as $sku => $qty) {
            $item = Item::query()->where('sku', $sku)->first();
            if (! $item) {
                continue;
            }
            $qty = (float) $qty;
            $price = (float) $item->price;
            $rows[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_sku' => $item->sku,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_discount' => 0,
                'line_total' => round($qty * $price, 2),
            ];
        }

        if ($rows === []) {
            throw new \RuntimeException('Aucun article démo trouvé pour les lignes.');
        }

        return $rows;
    }

    /**
     * @param  array<string, float|int>  $skuQty
     * @return list<array<string, mixed>>
     */
    private function purchaseLines(array $skuQty): array
    {
        $rows = [];
        foreach ($skuQty as $sku => $qty) {
            $item = Item::query()->where('sku', $sku)->first();
            if (! $item) {
                continue;
            }
            $qty = (float) $qty;
            $cost = (float) $item->cost;
            $rows[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => $qty,
                'unit_price' => $cost,
                'unit_price_ht' => $cost,
                'line_total' => round($qty * $cost, 2),
                'line_total_ht' => round($qty * $cost, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function computedTaxLines(array $lines, bool $withVat, bool $withIs = false): array
    {
        $ht = 0.0;
        foreach ($lines as $line) {
            $ht += (float) ($line['line_total'] ?? 0);
        }
        $ht = round($ht, 2);
        $taxes = [];
        if ($withVat) {
            $taxes[] = [
                'tax_name' => 'TVA',
                'tax_mode' => 'amount',
                'tax_rate' => self::VAT_RATE,
                'tax_amount' => round($ht * self::VAT_RATE / 100, 0),
                'tax_effect' => 'add',
            ];
        }
        if ($withIs) {
            $taxes[] = [
                'tax_name' => 'IS',
                'tax_mode' => 'amount',
                'tax_rate' => self::IS_RATE,
                'tax_amount' => round($ht * self::IS_RATE / 100, 0),
                'tax_effect' => 'add',
            ];
        }

        return $taxes;
    }

    private function snapToFrancs(Invoice $invoice): Invoice
    {
        $invoice->refresh()->loadMissing('taxLines');
        $taxTotal = 0.0;
        foreach ($invoice->taxLines as $line) {
            $line->tax_mode = 'amount';
            $line->tax_amount = round((float) $line->tax_amount, 0);
            $line->save();
            $taxTotal += (float) $line->tax_amount;
        }

        $ht = round(max(0, (float) $invoice->subtotal - (float) $invoice->discount_amount), 0);
        $invoice->tax_amount = $taxTotal;
        $invoice->total = $ht + $taxTotal;
        $invoice->balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        $invoice->save();

        return $invoice->fresh(['taxLines', 'client']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vatTaxLines(): array
    {
        return [[
            'tax_name' => 'TVA',
            'tax_mode' => 'percent',
            'tax_rate' => self::VAT_RATE,
            'tax_amount' => 0,
            'tax_effect' => 'add',
        ]];
    }
}
