<?php

namespace App\Livewire\Admin;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use App\Models\Tenant;
use Livewire\Component;

class ProspectForm extends Component
{
    public ?int $prospectId = null;

    public string $company_name = '';
    public string $contact_name = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public string $country = '';
    public string $city = '';
    public string $source = 'manual';
    public string $stage = 'lead';
    public string $product_interest = '';
    public string $expected_value = '';
    public string $probability = '';
    public string $notes = '';
    public string $next_follow_up_at = '';
    public string $owner_user_id = '';

    public string $activity_body = '';
    public string $activity_type = 'note';

    public function mount(?PlatformProspect $platformProspect = null): void
    {
        $this->product_interest = (string) config('tenant_types.default', 'erp');
        $this->owner_user_id = (string) (auth()->id() ?? '');

        $requestedStage = request()->query('stage');
        if (is_string($requestedStage) && array_key_exists($requestedStage, PlatformProspect::stages())) {
            $this->stage = $requestedStage;
            $this->probability = (string) PlatformProspect::defaultProbabilityForStage($requestedStage);
        }

        if (! $platformProspect?->exists) {
            return;
        }

        $this->prospectId = $platformProspect->id;
        $this->company_name = $platformProspect->company_name;
        $this->contact_name = (string) $platformProspect->contact_name;
        $this->contact_email = (string) $platformProspect->contact_email;
        $this->contact_phone = (string) $platformProspect->contact_phone;
        $this->country = (string) $platformProspect->country;
        $this->city = (string) $platformProspect->city;
        $this->source = $platformProspect->source ?: 'manual';
        $this->stage = $platformProspect->stage ?: 'lead';
        $this->product_interest = Tenant::normalizeType($platformProspect->product_interest ?: config('tenant_types.default', 'erp'));
        $this->expected_value = $platformProspect->expected_value !== null ? (string) $platformProspect->expected_value : '';
        $this->probability = $platformProspect->probability !== null
            ? (string) $platformProspect->probability
            : (string) PlatformProspect::defaultProbabilityForStage($this->stage);
        $this->notes = (string) $platformProspect->notes;
        $this->next_follow_up_at = optional($platformProspect->next_follow_up_at)?->format('Y-m-d') ?? '';
        $this->owner_user_id = $platformProspect->owner_user_id ? (string) $platformProspect->owner_user_id : '';
    }

    public function updatedStage(string $value): void
    {
        if ($this->probability === '' || in_array((int) $this->probability, [10, 30, 55, 75, 100, 0], true)) {
            $this->probability = (string) PlatformProspect::defaultProbabilityForStage($value);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'source' => 'required|string|max:50',
            'stage' => 'required|string|in:' . implode(',', array_keys(PlatformProspect::stages())),
            'product_interest' => 'required|string|in:' . implode(',', array_keys(config('tenant_types.types', []))),
            'expected_value' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:5000',
            'next_follow_up_at' => 'nullable|date',
            'owner_user_id' => 'nullable|exists:users,id',
        ]);

        $ownerId = $data['owner_user_id'] !== null && $data['owner_user_id'] !== ''
            ? (int) $data['owner_user_id']
            : (auth()->id() ?: null);

        $payload = [
            ...collect($data)->except('owner_user_id')->all(),
            'expected_value' => $data['expected_value'] !== null && $data['expected_value'] !== ''
                ? $data['expected_value']
                : null,
            'probability' => $data['probability'] !== null && $data['probability'] !== ''
                ? (int) $data['probability']
                : PlatformProspect::defaultProbabilityForStage($data['stage']),
            'product_interest' => Tenant::normalizeType($data['product_interest']),
        ];

        if ($this->prospectId) {
            $prospect = PlatformProspect::findOrFail($this->prospectId);
            $oldStage = $prospect->stage;
            $prospect->update($payload);
            $prospect->assignOwner($ownerId, auth()->id());
            if ($oldStage !== $prospect->fresh()->stage) {
                PlatformProspectActivity::create([
                    'platform_prospect_id' => $prospect->id,
                    'user_id' => auth()->id(),
                    'type' => 'stage_change',
                    'subject' => 'Étape',
                    'body' => ($prospect::stages()[$oldStage] ?? $oldStage) . ' → ' . $prospect->fresh()->stageLabel(),
                ]);
            }
            notify()->success('Enregistré.');
        } else {
            $payload['owner_user_id'] = $ownerId;
            $prospect = PlatformProspect::create($payload);
            $prospect->load('owner');
            PlatformProspectActivity::create([
                'platform_prospect_id' => $prospect->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'subject' => 'Création',
                'body' => 'Fiche créée'
                    . ($prospect->owner ? ' · Commercial : '.$prospect->owner->name : '')
                    . '.',
            ]);
            notify()->success('Créé.');
            $target = array_key_exists($prospect->stage, PlatformProspect::opportunityStages())
                ? route('system.opportunities')
                : route('system.prospects.edit', $prospect);
            $this->redirect($target, navigate: true);
        }
    }

    public function markWon(): void
    {
        $this->stage = PlatformProspect::STAGE_WON;
        $this->probability = '100';
        $this->save();
    }

    public function promoteToOpportunity(): void
    {
        if (! $this->prospectId) {
            return;
        }

        $prospect = PlatformProspect::find($this->prospectId);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }

        if (array_key_exists($prospect->stage, PlatformProspect::opportunityStages())
            && $prospect->stage !== PlatformProspect::STAGE_WON) {
            notify()->info('Déjà dans le pipeline.');
            $this->redirect(route('system.opportunities'), navigate: true);

            return;
        }

        $this->stage = PlatformProspect::STAGE_QUALIFIED;
        $this->probability = (string) PlatformProspect::defaultProbabilityForStage(PlatformProspect::STAGE_QUALIFIED);
        $this->save();

        PlatformProspectActivity::create([
            'platform_prospect_id' => $prospect->id,
            'user_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => 'Passage en opportunité',
            'body' => 'Prospect promu dans le pipeline.',
        ]);

        notify()->success('Opportunité créée.');
        $this->redirect(route('system.opportunities'), navigate: true);
    }

    public function markLost(): void
    {
        $this->stage = PlatformProspect::STAGE_LOST;
        $this->probability = '0';
        $this->save();
    }

    public function addNote(): void
    {
        if (! $this->prospectId) {
            return;
        }
        $this->validate([
            'activity_body' => 'required|string|max:5000',
            'activity_type' => 'required|string|in:note,call,email,meeting,follow_up',
        ]);
        PlatformProspectActivity::create([
            'platform_prospect_id' => $this->prospectId,
            'user_id' => auth()->id(),
            'type' => $this->activity_type,
            'subject' => PlatformProspectActivity::types()[$this->activity_type] ?? 'Activité',
            'body' => $this->activity_body,
        ]);
        $this->activity_body = '';
        notify()->success('Activité ajoutée.');
    }

    public function render()
    {
        $prospect = $this->prospectId ? PlatformProspect::with(['activities.user', 'convertedTenant'])->find($this->prospectId) : null;

        return view('livewire.admin.prospect-form', [
            'prospect' => $prospect,
            'stages' => PlatformProspect::stages(),
            'sources' => PlatformProspect::sources(),
            'productTypes' => config('tenant_types.types', []),
            'activityTypes' => PlatformProspectActivity::types(),
            'salespeople' => PlatformProspect::salespeople(),
            'canConvert' => $prospect
                && ! $prospect->converted_tenant_id
                && $prospect->stage === PlatformProspect::STAGE_WON,
        ])->layout('layouts.app', [
            'title' => $this->prospectId ? ($this->company_name ?: 'Fiche') : 'Nouveau',
            'subtitle' => 'Relation client',
        ]);
    }
}
