<?php

namespace Pressing\Support;

use App\Models\Tenant;
use App\Services\TenantManager;
use Pressing\Models\PressingPayment;

class PressingSettings
{
    public const KEY_DEFAULT_DELAY_HOURS = 'pressing.default_delay_hours';
    public const KEY_TAX_RATE = 'pressing.tax_rate';
    public const KEY_TAX_ENABLED = 'pressing.tax_enabled';
    public const KEY_PAYMENT_METHODS = 'pressing.payment_methods';
    public const KEY_BILLING_DEFAULT_MODE = 'pressing.billing.default_mode';
    public const KEY_WEIGHT_PRICE_GLOBAL = 'pressing.billing.weight_price_global';
    public const KEY_MSG_ORDER_CREATED = 'pressing.messages.order_created';
    public const KEY_MSG_ORDER_READY = 'pressing.messages.order_ready';
    public const KEY_MSG_ORDER_DELIVERED = 'pressing.messages.order_delivered';
    public const KEY_MSG_PAYMENT_RECEIVED = 'pressing.messages.payment_received';
    public const KEY_MSG_PAYMENT_REMINDER = 'pressing.messages.payment_reminder';
    public const KEY_MSG_ORDER_OVERDUE = 'pressing.messages.order_overdue';

    public const KEY_NOTIF_ENABLED = 'pressing.notifications.enabled';
    public const KEY_NOTIF_IN_APP = 'pressing.notifications.in_app';
    public const KEY_NOTIF_WHATSAPP = 'pressing.notifications.whatsapp';
    public const KEY_NOTIF_SMS = 'pressing.notifications.sms';
    public const KEY_NOTIF_EMAIL = 'pressing.notifications.email';
    public const KEY_NOTIF_WHATSAPP_URL = 'pressing.notifications.whatsapp_api_url';
    public const KEY_NOTIF_WHATSAPP_KEY = 'pressing.notifications.whatsapp_api_key';
    public const KEY_NOTIF_SMS_URL = 'pressing.notifications.sms_api_url';
    public const KEY_NOTIF_SMS_KEY = 'pressing.notifications.sms_api_key';
    public const KEY_NOTIF_SMS_SENDER = 'pressing.notifications.sms_sender';
    public const KEY_NOTIF_EMAIL_FROM = 'pressing.notifications.email_from';

    public const KEY_LOYALTY_ACTIVE = 'pressing.loyalty.active';
    public const KEY_LOYALTY_POINTS_PER_ORDER = 'pressing.loyalty.points_per_order';
    public const KEY_LOYALTY_AMOUNT_PER_POINT = 'pressing.loyalty.amount_per_point';
    public const KEY_LOYALTY_THRESHOLD = 'pressing.loyalty.threshold_points';
    public const KEY_LOYALTY_REWARD_TYPE = 'pressing.loyalty.reward_type';
    public const KEY_LOYALTY_REWARD_VALUE = 'pressing.loyalty.reward_value';
    public const KEY_LOYALTY_REWARD_MAX = 'pressing.loyalty.reward_max';
    public const KEY_LOYALTY_EXPIRY_DAYS = 'pressing.loyalty.reward_expiry_days';

    public static function tenant(): ?Tenant
    {
        return app(TenantManager::class)->tenant();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::tenant()?->getSetting($key, $default) ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $tenant = self::tenant();
        if ($tenant) {
            $tenant->setSetting($key, $value);
        }
    }

    public static function defaultDelayHours(): int
    {
        return max(0, (int) self::get(self::KEY_DEFAULT_DELAY_HOURS, 24));
    }

    public static function taxEnabled(): bool
    {
        return (bool) self::get(self::KEY_TAX_ENABLED, false);
    }

    public static function taxRate(): float
    {
        return (float) self::get(self::KEY_TAX_RATE, 0);
    }

    public static function billingDefaultMode(): string
    {
        return PressingBilling::defaultMode();
    }

    public static function globalWeightPrice(?int $agenceId = null): float
    {
        return PressingBilling::globalWeightPrice($agenceId);
    }

    public static function notificationsEnabled(): bool
    {
        return (bool) self::get(self::KEY_NOTIF_ENABLED, false);
    }

    public static function channelEnabled(string $channel): bool
    {
        if (! self::notificationsEnabled()) {
            return false;
        }

        return match ($channel) {
            'in_app' => (bool) self::get(self::KEY_NOTIF_IN_APP, true),
            'whatsapp' => (bool) self::get(self::KEY_NOTIF_WHATSAPP, false),
            'sms' => (bool) self::get(self::KEY_NOTIF_SMS, false),
            'email' => (bool) self::get(self::KEY_NOTIF_EMAIL, false),
            default => false,
        };
    }

    public static function defaultPaymentMethods(): array
    {
        $methods = [];
        foreach (PressingPayment::METHODS as $key => $label) {
            $methods[] = [
                'key' => $key,
                'label' => $label,
                'is_active' => true,
            ];
        }

        return $methods;
    }

    public static function paymentMethods(bool $activeOnly = true): array
    {
        $stored = self::get(self::KEY_PAYMENT_METHODS);
        $methods = is_array($stored) && count($stored) > 0
            ? $stored
            : self::defaultPaymentMethods();

        if ($activeOnly) {
            $methods = array_values(array_filter($methods, fn ($m) => ($m['is_active'] ?? true)));
        }

        return $methods;
    }

    public static function paymentMethodsMap(bool $activeOnly = true): array
    {
        $map = [];
        foreach (self::paymentMethods($activeOnly) as $method) {
            if (! empty($method['key'])) {
                $map[$method['key']] = $method['label'] ?? $method['key'];
            }
        }

        return $map;
    }

    public static function loyaltyActive(): bool
    {
        return (bool) self::get(self::KEY_LOYALTY_ACTIVE, false);
    }

    public static function loyaltyPointsPerOrder(): int
    {
        return max(0, (int) self::get(self::KEY_LOYALTY_POINTS_PER_ORDER, 1));
    }

    /** Amount (FCFA) that grants 1 extra point. 0 = disabled. */
    public static function loyaltyAmountPerPoint(): float
    {
        return max(0, (float) self::get(self::KEY_LOYALTY_AMOUNT_PER_POINT, 0));
    }

    public static function loyaltyThreshold(): int
    {
        return max(1, (int) self::get(self::KEY_LOYALTY_THRESHOLD, 10));
    }

    public static function loyaltyRewardType(): string
    {
        $type = (string) self::get(self::KEY_LOYALTY_REWARD_TYPE, 'value');

        return in_array($type, ['value', 'percent'], true) ? $type : 'value';
    }

    public static function loyaltyRewardValue(): float
    {
        return max(0, (float) self::get(self::KEY_LOYALTY_REWARD_VALUE, 0));
    }

    public static function loyaltyRewardMax(): ?float
    {
        $max = self::get(self::KEY_LOYALTY_REWARD_MAX);

        return $max === null || $max === '' ? null : max(0, (float) $max);
    }

    /** Reward validity in days. 0 = never expires. */
    public static function loyaltyExpiryDays(): int
    {
        return max(0, (int) self::get(self::KEY_LOYALTY_EXPIRY_DAYS, 0));
    }

    public static function defaultMessages(): array
    {
        return [
            self::KEY_MSG_ORDER_CREATED => "Bonjour {{client}},\nVotre commande {{number}} a bien été enregistrée.\nMerci pour votre confiance.",
            self::KEY_MSG_ORDER_READY => "Bonjour {{client}},\nVos vêtements sont prêts ({{number}}).\nVous pouvez passer les récupérer.\nMerci pour votre confiance.",
            self::KEY_MSG_ORDER_DELIVERED => "Bonjour {{client}},\nVotre commande {{number}} a été livrée.\nÀ bientôt.",
            self::KEY_MSG_PAYMENT_RECEIVED => "Bonjour {{client}},\nNous avons reçu votre paiement de {{amount}} pour la commande {{number}}.\nMerci.",
            self::KEY_MSG_PAYMENT_REMINDER => "Bonjour {{client}},\nUn solde de {{balance}} reste dû sur la commande {{number}}.\nMerci de régulariser.",
            self::KEY_MSG_ORDER_OVERDUE => "Bonjour {{client}},\nVotre commande {{number}} dépasse le délai prévu.\nContactez votre agence pour le suivi.",
        ];
    }

    public static function message(string $key): string
    {
        $defaults = self::defaultMessages();

        return (string) self::get($key, $defaults[$key] ?? '');
    }

    public static function eventMessageKey(string $event): ?string
    {
        return match ($event) {
            'order_created' => self::KEY_MSG_ORDER_CREATED,
            'order_ready' => self::KEY_MSG_ORDER_READY,
            'order_delivered' => self::KEY_MSG_ORDER_DELIVERED,
            'payment_received' => self::KEY_MSG_PAYMENT_RECEIVED,
            'payment_reminder' => self::KEY_MSG_PAYMENT_REMINDER,
            'order_overdue' => self::KEY_MSG_ORDER_OVERDUE,
            default => null,
        };
    }

    public static function seedDefaults(?object $tenant = null): void
    {
        $tenant = $tenant instanceof Tenant ? $tenant : self::tenant();
        if (! $tenant) {
            return;
        }

        $defaults = [
            self::KEY_DEFAULT_DELAY_HOURS => 24,
            self::KEY_TAX_ENABLED => false,
            self::KEY_TAX_RATE => 0,
            self::KEY_PAYMENT_METHODS => self::defaultPaymentMethods(),
            self::KEY_BILLING_DEFAULT_MODE => PressingBilling::MODE_MIXED,
            self::KEY_WEIGHT_PRICE_GLOBAL => 0,
            self::KEY_NOTIF_ENABLED => false,
            self::KEY_NOTIF_IN_APP => true,
            self::KEY_NOTIF_WHATSAPP => false,
            self::KEY_NOTIF_SMS => false,
            self::KEY_NOTIF_EMAIL => false,
            self::KEY_NOTIF_WHATSAPP_URL => '',
            self::KEY_NOTIF_WHATSAPP_KEY => '',
            self::KEY_NOTIF_SMS_URL => '',
            self::KEY_NOTIF_SMS_KEY => '',
            self::KEY_NOTIF_SMS_SENDER => '',
            self::KEY_NOTIF_EMAIL_FROM => '',
            self::KEY_LOYALTY_ACTIVE => false,
            self::KEY_LOYALTY_POINTS_PER_ORDER => 1,
            self::KEY_LOYALTY_AMOUNT_PER_POINT => 0,
            self::KEY_LOYALTY_THRESHOLD => 10,
            self::KEY_LOYALTY_REWARD_TYPE => 'value',
            self::KEY_LOYALTY_REWARD_VALUE => 0,
            self::KEY_LOYALTY_REWARD_MAX => '',
            self::KEY_LOYALTY_EXPIRY_DAYS => 0,
        ] + self::defaultMessages();

        foreach ($defaults as $key => $value) {
            if ($tenant->getSetting($key) === null) {
                $tenant->setSetting($key, $value);
            }
        }
    }
}
