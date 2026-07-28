<?php

namespace Pressing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pressing\Models\PressingClient;
use Pressing\Models\PressingLoyaltyEntry;
use Pressing\Models\PressingLoyaltyReward;
use Pressing\Models\PressingOrder;
use Pressing\Support\PressingSettings;

class PressingLoyaltyService
{
    public function active(): bool
    {
        return PressingSettings::loyaltyActive();
    }

    /**
     * Award loyalty points for an order once it is fully paid.
     * Idempotent: a given order can only earn points once.
     */
    public function syncOrderPoints(PressingOrder $order): void
    {
        if (! $this->active()) {
            return;
        }

        if (! $order->client_id) {
            return;
        }

        if ((float) $order->total <= 0 || (float) $order->balance > 0) {
            return; // not fully paid yet
        }

        $alreadyEarned = PressingLoyaltyEntry::query()
            ->where('order_id', $order->id)
            ->where('type', PressingLoyaltyEntry::TYPE_EARN)
            ->exists();

        if ($alreadyEarned) {
            return;
        }

        $points = $this->pointsForOrder($order);
        if ($points <= 0) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($order, $points) {
            $client = PressingClient::query()->lockForUpdate()->find($order->client_id);
            if (! $client) {
                return;
            }

            $this->recordEntry(
                $client,
                PressingLoyaltyEntry::TYPE_EARN,
                $points,
                __('Commande :number payée', ['number' => $order->number]),
                $order->id
            );

            $client->increment('loyalty_orders_count');

            $this->issueRewardsIfEligible($client);
        });
    }

    public function pointsForOrder(PressingOrder $order): int
    {
        $points = PressingSettings::loyaltyPointsPerOrder();

        $amountPerPoint = PressingSettings::loyaltyAmountPerPoint();
        if ($amountPerPoint > 0) {
            $points += (int) floor((float) $order->total / $amountPerPoint);
        }

        return max(0, $points);
    }

    /**
     * Convert accumulated points into reward vouchers while the balance
     * covers the configured threshold.
     *
     * @return list<PressingLoyaltyReward>
     */
    public function issueRewardsIfEligible(PressingClient $client): array
    {
        $threshold = PressingSettings::loyaltyThreshold();
        $rewardValue = PressingSettings::loyaltyRewardValue();

        if ($rewardValue <= 0) {
            return []; // no reward configured
        }

        $issued = [];
        $guard = 0;

        while ((int) $client->loyalty_points >= $threshold && $guard < 50) {
            $guard++;

            $reward = PressingLoyaltyReward::create([
                'client_id' => $client->id,
                'code' => $this->generateCode(),
                'reward_type' => PressingSettings::loyaltyRewardType(),
                'reward_value' => $rewardValue,
                'reward_max' => PressingSettings::loyaltyRewardMax(),
                'points_spent' => $threshold,
                'status' => PressingLoyaltyReward::STATUS_AVAILABLE,
                'issued_by' => Auth::guard('tenant')->id(),
                'expires_at' => PressingSettings::loyaltyExpiryDays() > 0
                    ? now()->addDays(PressingSettings::loyaltyExpiryDays())
                    : null,
            ]);

            $this->recordEntry(
                $client,
                PressingLoyaltyEntry::TYPE_REDEEM,
                -$threshold,
                __('Récompense générée :code', ['code' => $reward->code]),
                null
            );

            $issued[] = $reward;
        }

        return $issued;
    }

    /** Manual points adjustment by staff. */
    public function adjust(PressingClient $client, int $points, ?string $reason = null): PressingLoyaltyEntry
    {
        return DB::connection('tenant')->transaction(function () use ($client, $points, $reason) {
            $client = PressingClient::query()->lockForUpdate()->findOrFail($client->id);

            $entry = $this->recordEntry(
                $client,
                PressingLoyaltyEntry::TYPE_ADJUST,
                $points,
                $reason ?: __('Ajustement manuel'),
                null
            );

            if ($points > 0) {
                $this->issueRewardsIfEligible($client);
            }

            return $entry;
        });
    }

    /** Rewards a client can use right now (cleans up expired ones). */
    public function availableRewards(PressingClient $client): Collection
    {
        $rewards = $client->availableRewards()->latest('id')->get();

        $expired = $rewards->filter(fn (PressingLoyaltyReward $r) => $r->expires_at && $r->expires_at->isPast());
        if ($expired->isNotEmpty()) {
            PressingLoyaltyReward::query()
                ->whereIn('id', $expired->pluck('id'))
                ->update(['status' => PressingLoyaltyReward::STATUS_EXPIRED]);
        }

        return $rewards->reject(fn (PressingLoyaltyReward $r) => $r->expires_at && $r->expires_at->isPast())->values();
    }

    /** Mark a reward as consumed by an order. */
    public function redeemReward(PressingLoyaltyReward $reward, PressingOrder $order, float $discountAmount): void
    {
        $reward->update([
            'status' => PressingLoyaltyReward::STATUS_USED,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'used_by' => Auth::guard('tenant')->id(),
            'used_at' => now(),
        ]);
    }

    /** Release a reward previously attached to an order (e.g. on edit). */
    public function releaseRewardForOrder(int $orderId): void
    {
        PressingLoyaltyReward::query()
            ->where('order_id', $orderId)
            ->where('status', PressingLoyaltyReward::STATUS_USED)
            ->update([
                'status' => PressingLoyaltyReward::STATUS_AVAILABLE,
                'order_id' => null,
                'discount_amount' => 0,
                'used_by' => null,
                'used_at' => null,
            ]);
    }

    private function recordEntry(PressingClient $client, string $type, int $points, ?string $reason, ?int $orderId): PressingLoyaltyEntry
    {
        $client->loyalty_points = max(0, (int) $client->loyalty_points + $points);
        $client->save();

        return PressingLoyaltyEntry::create([
            'client_id' => $client->id,
            'order_id' => $orderId,
            'type' => $type,
            'points' => $points,
            'balance_after' => (int) $client->loyalty_points,
            'reason' => $reason,
            'created_by' => Auth::guard('tenant')->id(),
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'LOY-'.strtoupper(Str::random(6));
        } while (PressingLoyaltyReward::query()->where('code', $code)->exists());

        return $code;
    }
}
