<?php

namespace InovCom\Providers\Services;

use InovCom\Providers\Models\Provider;
use Illuminate\Support\Collection;

/**
 * Simple API service for Providers module
 * Used by other modules (like Purchases) to interact with providers
 */
class ProvidersApiService
{
    public function findProvider(int $id): ?object
    {
        return Provider::on('tenant')->find($id);
    }

    public function findProviderByCode(string $code): ?object
    {
        return Provider::on('tenant')
            ->where('code', $code)
            ->first();
    }

    public function getActiveProviders(): Collection
    {
        return Provider::on('tenant')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function providerExists(int $id): bool
    {
        return Provider::on('tenant')
            ->where('id', $id)
            ->exists();
    }

    public function getPaymentTerm(int $providerId): ?object
    {
        $provider = $this->findProvider($providerId);
        return $provider?->paymentTerm;
    }
}
