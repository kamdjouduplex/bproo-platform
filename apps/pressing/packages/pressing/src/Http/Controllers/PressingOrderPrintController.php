<?php

namespace Pressing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pressing\Models\PressingOrder;
use Pressing\Support\PressingQrCode;

class PressingOrderPrintController
{
    public function __invoke(Request $request, PressingOrder $pressingOrder): View
    {
        $type = $request->query('type', 'deposit');
        if (! in_array($type, ['deposit', 'ticket', 'invoice', 'label'], true)) {
            $type = 'deposit';
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $pressingOrder->loadMissing(['client', 'agence', 'items.articleType', 'payments', 'currentStage', 'constitutionLines.articleType']);

        $scanUrl = route('tenant.pressing_orders.qr', [
            'tenant' => $tenant?->code ?? request()->query('tenant'),
            'token' => $pressingOrder->qr_token,
        ]);

        $views = [
            'deposit' => 'pressing::print.deposit',
            'ticket' => 'pressing::print.ticket',
            'invoice' => 'pressing::print.invoice',
            'label' => 'pressing::print.label',
        ];

        $labels = [
            'deposit' => 'recu-depot',
            'ticket' => 'ticket-pressing',
            'invoice' => 'facture-pressing',
            'label' => 'etiquette-qr',
        ];

        $printContext = PrintDocument::context(
            $request,
            $labels[$type],
            $pressingOrder->number,
            'tenant.pressing_orders.index'
        );

        return view($views[$type], array_merge([
            'order' => $pressingOrder,
            'settings' => $settings,
            'currency' => $settings['currency'] ?? 'XOF',
            'scanUrl' => $scanUrl,
            'qrImageUrl' => PressingQrCode::dataUri($scanUrl, $type === 'label' ? 160 : 180),
            'printPageSize' => in_array($type, ['ticket', 'label'], true) ? '80mm auto' : 'A4',
        ], $printContext));
    }
}
