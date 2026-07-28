<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Models\PressingOrder;
use Pressing\Services\PressingSettlementService;

class PressingSettlementServiceTest extends TestCase
{
    private PressingSettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PressingSettlementService;
    }

    public function test_fully_paid_can_deliver(): void
    {
        $order = new PressingOrder(['balance' => 0, 'credit_status' => null]);
        $this->assertTrue($this->service->canDeliver($order));
        $this->assertNull($this->service->deliveryBlockReason($order));
    }

    public function test_unpaid_without_credit_blocked(): void
    {
        $order = new PressingOrder(['balance' => 5000, 'credit_status' => null]);
        $this->assertFalse($this->service->canDeliver($order));
        $this->assertStringContainsString('Solde', (string) $this->service->deliveryBlockReason($order));
    }

    public function test_pending_credit_still_blocked(): void
    {
        $order = new PressingOrder([
            'balance' => 5000,
            'credit_status' => PressingSettlementService::CREDIT_PENDING,
            'credit_amount' => 5000,
        ]);
        $this->assertFalse($this->service->canDeliver($order));
        $this->assertStringContainsString('attente', (string) $this->service->deliveryBlockReason($order));
    }

    public function test_approved_credit_allows_delivery(): void
    {
        $order = new PressingOrder([
            'balance' => 5000,
            'credit_status' => PressingSettlementService::CREDIT_APPROVED,
            'credit_amount' => 5000,
        ]);
        $this->assertTrue($this->service->canDeliver($order));
    }

    public function test_approved_credit_insufficient_blocked(): void
    {
        $order = new PressingOrder([
            'balance' => 8000,
            'credit_status' => PressingSettlementService::CREDIT_APPROVED,
            'credit_amount' => 5000,
        ]);
        $this->assertFalse($this->service->canDeliver($order));
    }
}
