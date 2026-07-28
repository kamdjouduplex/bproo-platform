<?php

namespace InovCom\Purchases\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Models\ForeignReceiptNote;
use InovCom\Purchases\Services\ForeignPurchasesService;

class ForeignPurchasePrintController
{
    /**
     * GET /app/purchases/foreign/{foreignPurchase}/print?type=order|receipt&receipt_id=
     */
    public function __invoke(Request $request, ForeignPurchaseOrder $foreignPurchase): View
    {
        $type = $request->query('type', 'order');
        if (!in_array($type, ['order', 'receipt'], true)) {
            $type = 'order';
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        if ($type === 'receipt') {
            $receiptId = (int) $request->query('receipt_id');
            $receipt = ForeignReceiptNote::with(['lines.foreignPurchaseLine.item', 'foreignPurchaseOrder.provider'])
                ->where('foreign_purchase_order_id', $foreignPurchase->id)
                ->findOrFail($receiptId);

            $foreignPurchase->load(['provider', 'lines']);

            return view('inovcom-purchases::print.foreign-receipt', array_merge([
                'order' => $foreignPurchase,
                'receipt' => $receipt,
                'settings' => $settings,
            ], PrintDocument::context(
                $request,
                'bon-reception-etranger',
                $receipt->receipt_number,
                'tenant.foreign_purchases.show',
                ['foreignPurchase' => $foreignPurchase->id]
            )));
        }

        $foreignPurchase->load(['provider', 'lines.item', 'creator']);

        return view('inovcom-purchases::print.foreign-order', array_merge([
            'order' => $foreignPurchase,
            'settings' => $settings,
            'statusLabel' => ForeignPurchasesService::statusLabel($foreignPurchase->status),
        ], PrintDocument::context(
            $request,
            'achat-etranger',
            $foreignPurchase->order_number,
            'tenant.foreign_purchases.index'
        )));
    }
}
