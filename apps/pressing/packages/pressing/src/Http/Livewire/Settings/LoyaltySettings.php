<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class LoyaltySettings extends Component
{
    use AuthorizesPressingActions;

    public bool $active = false;

    public string $points_per_order = '1';

    public string $amount_per_point = '0';

    public string $threshold_points = '10';

    public string $reward_type = 'value';

    public string $reward_value = '0';

    public string $reward_max = '';

    public string $reward_expiry_days = '0';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');

        $this->active = PressingSettings::loyaltyActive();
        $this->points_per_order = (string) PressingSettings::loyaltyPointsPerOrder();
        $this->amount_per_point = (string) (int) PressingSettings::loyaltyAmountPerPoint();
        $this->threshold_points = (string) PressingSettings::loyaltyThreshold();
        $this->reward_type = PressingSettings::loyaltyRewardType();
        $this->reward_value = (string) (int) PressingSettings::loyaltyRewardValue();
        $max = PressingSettings::loyaltyRewardMax();
        $this->reward_max = $max === null ? '' : (string) (int) $max;
        $this->reward_expiry_days = (string) PressingSettings::loyaltyExpiryDays();
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $data = $this->validate([
            'active' => ['boolean'],
            'points_per_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'amount_per_point' => ['required', 'numeric', 'min:0'],
            'threshold_points' => ['required', 'integer', 'min:1', 'max:100000'],
            'reward_type' => ['required', 'in:value,percent'],
            'reward_value' => ['required', 'numeric', 'min:0'],
            'reward_max' => ['nullable', 'numeric', 'min:0'],
            'reward_expiry_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        PressingSettings::set(PressingSettings::KEY_LOYALTY_ACTIVE, (bool) $data['active']);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_POINTS_PER_ORDER, (int) $data['points_per_order']);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_AMOUNT_PER_POINT, (float) $data['amount_per_point']);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_THRESHOLD, (int) $data['threshold_points']);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_REWARD_TYPE, $data['reward_type']);
        PressingSettings::set(PressingSettings::KEY_LOYALTY_REWARD_VALUE, (float) $data['reward_value']);
        PressingSettings::set(
            PressingSettings::KEY_LOYALTY_REWARD_MAX,
            $data['reward_max'] === null || $data['reward_max'] === '' ? '' : (float) $data['reward_max']
        );
        PressingSettings::set(PressingSettings::KEY_LOYALTY_EXPIRY_DAYS, (int) $data['reward_expiry_days']);

        session()->flash('success', __('Programme de fidélité enregistré.'));
    }

    public function render()
    {
        return view('pressing::livewire.settings.loyalty', [
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', [
            'title' => 'Fidélité',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
