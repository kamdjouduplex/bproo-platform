<?php

namespace InovCom\Prospects\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Prospects\Concerns\AuthorizesProspectActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Prospects\Services\ProspectsService;
use Livewire\Component;

class ProspectShow extends Component
{
    use AuthorizesProspectActions;

    public Prospect $prospect;

    public string $newStatus = '';

    public string $lostReason = '';

    public string $activityType = 'note';

    public string $activityBody = '';

    public bool $createQuotationAfterConvert = false;

    public function mount(Prospect $prospect): void
    {
        $this->authorizeProspectAction('prospects.view');
        $this->prospect = $prospect->load(['owner', 'creator', 'convertedClient', 'activities.user']);
        $this->newStatus = $prospect->status;
    }

    public function changeStatus(): void
    {
        $this->authorizeProspectAction('prospects.update');

        try {
            $this->prospect = app(ProspectsService::class)->changeStatus(
                $this->prospect,
                $this->newStatus,
                $this->lostReason ?: null,
                null,
                Auth::guard('tenant')->id()
            );
            $this->lostReason = '';
            session()->flash('success', 'Statut mis à jour.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addActivity(): void
    {
        $this->authorizeProspectAction('prospects.update');

        $this->validate([
            'activityType' => 'required|in:note,call,meeting,email',
            'activityBody' => 'required|string|max:5000',
        ]);

        try {
            app(ProspectsService::class)->addActivity(
                $this->prospect,
                $this->activityType,
                $this->activityBody,
                Auth::guard('tenant')->id()
            );
            $this->activityBody = '';
            $this->activityType = 'note';
            $this->prospect = $this->prospect->fresh(['owner', 'creator', 'convertedClient', 'activities.user']);
            session()->flash('success', 'Activité ajoutée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function convert(): void
    {
        $this->authorizeProspectAction('prospects.convert');

        try {
            $result = app(ProspectsService::class)->convertToClient(
                $this->prospect,
                Auth::guard('tenant')->id()
            );
            $client = $result['client'];
            $this->prospect = $result['prospect'];

            session()->flash('success', 'Prospect converti en client ' . $client->code . '.');

            if ($this->createQuotationAfterConvert && Route::has('tenant.quotations.create')) {
                $this->redirect(route('tenant.quotations.create', [
                    'tenant' => $this->tenantCode(),
                    'client_id' => $client->id,
                ]), navigate: true);

                return;
            }

            if (Route::has('tenant.clients.show')) {
                $this->redirect(route('tenant.clients.show', [
                    'client' => $client->id,
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(): void
    {
        $this->authorizeProspectAction('prospects.delete');

        try {
            app(ProspectsService::class)->delete($this->prospect);
            session()->flash('success', 'Prospect supprimé.');
            $this->redirect(route('tenant.prospects.index', [
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('inovcom-prospects::livewire.prospects.show')
            ->layout('layouts.app', [
                'title' => $this->prospect->reference,
                'subtitle' => $this->prospect->name,
            ])
            ->with([
                'tenantCode' => $this->tenantCode(),
                'canUpdate' => $this->canProspect('prospects.update') && $this->prospect->isEditable(),
                'canConvert' => $this->canProspect('prospects.convert') && $this->prospect->canConvert(),
                'conversionGaps' => $this->prospect->conversionGaps(),
                'readyToConvert' => $this->prospect->isReadyToConvert(),
                'canDelete' => $this->canProspect('prospects.delete') && ! $this->prospect->isConverted(),
                'activityTypes' => collect(ProspectActivity::typeOptions())
                    ->except(ProspectActivity::TYPE_STATUS)
                    ->all(),
            ]);
    }
}
