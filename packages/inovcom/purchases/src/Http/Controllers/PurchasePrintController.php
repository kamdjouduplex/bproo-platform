<?php

namespace InovCom\Purchases\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Models\ReceiptNote;
use InovCom\Purchases\Services\PurchasesService;

class PurchasePrintController
{
    /**
     * GET /app/purchases/{purchase}/print?type=order|receipt&receipt_id=
     */
    public function __invoke(Request $request, PurchaseOrder $purchase): View
    {
        $type = $request->query('type', 'order');
        if (!in_array($type, ['order', 'receipt'], true)) {
            $type = 'order';
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        if ($type === 'receipt') {
            $receiptId = (int) $request->query('receipt_id');
            $receipt = ReceiptNote::with(['lines.purchaseLine', 'purchaseOrder.provider', 'purchaseOrder.lines'])
                ->where('purchase_order_id', $purchase->id)
                ->findOrFail($receiptId);

            return view('inovcom-purchases::print.receipt', array_merge([
                'purchase' => $purchase->load(['provider', 'lines']),
                'receipt' => $receipt,
                'settings' => $settings,
            ], PrintDocument::context(
                $request,
                'bon-reception',
                $receipt->receipt_number,
                'tenant.purchases.index'
            )));
        }

        $purchase->load(['provider', 'lines.item', 'creator']);

        return view('inovcom-purchases::print.order', array_merge([
            'purchase' => $purchase,
            'settings' => $settings,
            'statusLabel' => PurchasesService::statusLabel($purchase->status),
        ], PrintDocument::context(
            $request,
            'bon-achat',
            $purchase->order_number,
            'tenant.purchases.index'
        )));
    }
}
