<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use InovCom\Maintenance\Models\MaintenanceOrder;

class SlaBreachWarning extends Notification
{
    use Queueable;

    public function __construct(
        public readonly MaintenanceOrder $order,
        public readonly float $hoursRemaining
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $hours = round($this->hoursRemaining, 1);
        return [
            'type'    => 'sla_breach_warning',
            'title'   => __('Alerte SLA imminente'),
            'message' => __('L\'ordre :code « :title » dépasse son SLA dans :h heure(s).', [
                'code'  => $this->order->code,
                'title' => $this->order->title,
                'h'     => $hours,
            ]),
            'priority' => $this->order->priority,
            'url'      => route('tenant.maintenance.orders.edit', [
                'tenant'            => session('tenant_code'),
                'maintenance_order' => $this->order->id,
            ]),
        ];
    }
}
