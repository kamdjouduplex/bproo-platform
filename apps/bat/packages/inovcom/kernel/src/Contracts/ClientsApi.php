<?php

namespace InovCom\Kernel\Contracts;

use Illuminate\Support\Collection;

/**
 * API contract for Clients module
 * 
 * This interface allows other modules to interact with Clients
 * without directly accessing Clients models, ensuring loose coupling.
 */
interface ClientsApi
{
    /**
     * Find a client by ID
     */
    public function findClient(int $id): ?object;

    /**
     * Find a client by code or identifier
     */
    public function findClientByCode(string $code): ?object;

    /**
     * Get client credit limit
     */
    public function getCreditLimit(int $clientId): float;

    /**
     * Get client current balance/debt
     */
    public function getCurrentBalance(int $clientId): float;

    /**
     * Check if client can make purchase (credit check)
     */
    public function canMakePurchase(int $clientId, float $amount): bool;

    /**
     * Get all active clients
     */
    public function getActiveClients(): Collection;

    /**
     * Check if client exists
     */
    public function clientExists(int $id): bool;
}
