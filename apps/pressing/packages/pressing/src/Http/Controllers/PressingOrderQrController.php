<?php

namespace Pressing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Pressing\Models\PressingOrder;
use Pressing\Support\PressingWorkflow;

class PressingOrderQrController
{
    /**
     * Public read-only order tracking page (QR scan). No authentication required.
     */
    public function __invoke(string $token): View|Response
    {
        $order = PressingOrder::query()
            ->with(['client', 'agence', 'items.articleType', 'currentStage', 'stageHistory'])
            ->where('qr_token', $token)
            ->firstOrFail();

        $tenant = app(TenantManager::class)->tenant();
        $branding = app(TenantBrandingService::class);
        $settings = $tenant ? $branding->documentSettings($tenant) : [];
        $shopName = $tenant
            ? (string) ($tenant->getSetting('shop_name', $tenant->name) ?: $tenant->name)
            : (string) config('app.name', 'Pressing');

        $pipeline = $this->trackingPipeline();
        $currentIndex = $this->currentPipelineIndex($order);
        $statusLabel = $this->publicStatusLabel($order);

        return view('pressing::print.qr-show', [
            'order' => $order,
            'shopName' => $shopName,
            'logoUrl' => $tenant ? $branding->url($tenant, 'icon') ?: $branding->url($tenant, 'main') : null,
            'currency' => $settings['currency'] ?? ($tenant?->getSetting('currency', 'XAF') ?? 'XAF'),
            'pipeline' => $pipeline,
            'currentIndex' => $currentIndex,
            'statusLabel' => $statusLabel,
            'phone' => $tenant?->getSetting('phone') ?: $order->agence?->phone,
            'locale' => app()->getLocale(),
        ]);
    }

    /**
     * Customer-facing progress steps (ordered).
     *
     * @return list<array{key: string, label: string}>
     */
    private function trackingPipeline(): array
    {
        return [
            ['key' => 'received', 'label' => __('Réception')],
            ['key' => 'tri', 'label' => __(PressingWorkflow::STAGE_TRI)],
            ['key' => 'production', 'label' => __('En production')],
            ['key' => 'ready', 'label' => __(PressingWorkflow::STAGE_PRET)],
            ['key' => 'delivered', 'label' => __(PressingWorkflow::STAGE_LIVRE)],
        ];
    }

    private function currentPipelineIndex(PressingOrder $order): int
    {
        $status = (string) $order->status;
        $stageName = $order->currentStage?->name;

        if ($status === 'delivered' || $stageName === PressingWorkflow::STAGE_LIVRE) {
            return 4;
        }

        if ($status === 'ready' || $stageName === PressingWorkflow::STAGE_PRET) {
            return 3;
        }

        if ($stageName && in_array($stageName, PressingWorkflow::kanbanStageNames(), true)) {
            return 2;
        }

        if ($stageName === PressingWorkflow::STAGE_TRI
            || in_array((string) $order->sorting_status, ['pending', 'in_progress'], true)) {
            return 1;
        }

        // Fallback from history / reception
        if ($order->received_at) {
            return 0;
        }

        return 0;
    }

    private function publicStatusLabel(PressingOrder $order): string
    {
        $stageName = $order->currentStage?->name;

        return match (true) {
            $order->status === 'delivered', $stageName === PressingWorkflow::STAGE_LIVRE => __('Livrée'),
            $order->status === 'ready', $stageName === PressingWorkflow::STAGE_PRET => __('Prête à retirer'),
            $stageName === PressingWorkflow::STAGE_FIN_PRODUCTION => __('Fin de production'),
            $stageName === PressingWorkflow::STAGE_REPASSAGE => __('Repassage'),
            $stageName === PressingWorkflow::STAGE_SECHAGE => __('Séchage'),
            $stageName === PressingWorkflow::STAGE_LAVAGE => __('Lavage'),
            $stageName === PressingWorkflow::STAGE_MISE_EN_PRODUCTION => __('Mise en production'),
            $stageName === PressingWorkflow::STAGE_TRI => __('Tri en cours'),
            default => $stageName ? __($stageName) : __('En cours de traitement'),
        };
    }
}
