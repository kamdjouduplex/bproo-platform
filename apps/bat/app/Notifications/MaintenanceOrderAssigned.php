<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use InovCom\Maintenance\Models\MaintenanceOrder;

class MaintenanceOrderAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly MaintenanceOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'maintenance_order_assigned',
            'title'    => __('Ordre de maintenance assigné'),
            'message'  => __('L\'ordre :code « :title » vous a été assigné.', [
                'code'  => $this->order->code,
                'title' => $this->order->title,
            ]),
            'priority' => $this->order->priority,
            'url'      => route('tenant.maintenance.orders.edit', [
                'tenant'            => session('tenant_code'),
                'maintenance_order' => $this->order->id,
            ]),
        ];
    }
}
