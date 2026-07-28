<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use InovCom\Offres\Models\Offer;

class OfferAccepted extends Notification
{
    use Queueable;

    public function __construct(public readonly Offer $offer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'offer_accepted',
            'title'   => __('Offre acceptée'),
            'message' => __('L\'offre :code « :title » a été acceptée.', [
                'code'  => $this->offer->code,
                'title' => $this->offer->title,
            ]),
            'url'     => route('tenant.offres.edit', [
                'tenant' => session('tenant_code'),
                'offer'  => $this->offer->id,
            ]),
        ];
    }
}
