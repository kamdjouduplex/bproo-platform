<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolPublicationRule;
use School\Models\SchoolResultPublication;
use School\Support\PublicationEngine;

class SchoolPublicationsDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $publicationId;
    public string $mode = 'show';

    public string $title = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $publicationRuleId = null;
    public ?string $notes = null;
    public string $approverName = '';

    public function mount(int $id): void
    {
        $this->publicationId = $id;
        SchoolResultPublication::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $p = $this->entity();
        if (! $p->isEditable()) {
            notify()->error('Publication non modifiable dans cet état.');

            return;
        }
        $this->title = $p->title;
        $this->academicYearId = $p->academic_year_id;
        $this->classId = $p->class_id;
        $this->publicationRuleId = $p->publication_rule_id;
        $this->notes = $p->notes;
        $this->openEditForm($p->id);
    }

    protected function resetFormFields(): void
    {
        $this->title = '';
        $this->academicYearId = $this->classId = $this->publicationRuleId = null;
        $this->notes = null;
    }

    public function save(): void
    {
        $p = $this->entity();
        if (! $p->isEditable()) {
            notify()->error('Publication non modifiable.');

            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'publicationRuleId' => ['nullable', 'integer', Rule::exists(SchoolPublicationRule::class, 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $p->update([
            'title' => $this->title,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'publication_rule_id' => $this->publicationRuleId,
            'notes' => filled($this->notes) ? $this->notes : null,
            'status' => $p->status === 'rejected' ? 'draft' : $p->status,
        ]);
        notify()->success('Publication mise à jour.');
        $this->cancel();
    }

    public function syncRoster(): void
    {
        $p = $this->entity();
        if ($p->status === 'published') {
            notify()->error('Déjà publiée.');

            return;
        }
        $count = app(PublicationEngine::class)->syncLines($p);
        notify()->success("{$count} élève(s) évalué(s) selon les règles.");
    }

    public function submitForApproval(): void
    {
        $p = $this->entity();
        $rule = $p->rule;
        if ($rule && ! $rule->require_director_approval) {
            notify()->info('Cette règle ne demande pas d’approbation — publiez directement.');

            return;
        }
        if ($p->lines()->where('eligible', true)->count() === 0) {
            notify()->error('Aucun élève éligible. Synchronisez d’abord la feuille.');

            return;
        }
        $p->update(['status' => 'pending_approval', 'submitted_at' => now()]);
        notify()->success('Soumis pour approbation directeur.');
    }

    public function approve(): void
    {
        if (! $this->authorizeSchool('school_publications.approve')) {
            return;
        }
        $this->validate([
            'approverName' => ['required', 'string', 'max:255'],
        ]);
        $p = $this->entity();
        if ($p->status !== 'pending_approval' && $p->status !== 'draft') {
            notify()->error('Statut invalide pour approbation.');

            return;
        }
        $p->update([
            'status' => 'pending_approval',
            'approved_at' => now(),
            'approved_by_name' => $this->approverName,
        ]);
        // Mark as approved waiting publish — use a distinct feel: store approved and keep pending until publish
        // Better: set an intermediate - we'll treat approved_at set + pending_approval as "approved"
        notify()->success('Approuvé par '.$this->approverName.'. Vous pouvez publier.');
    }

    public function reject(): void
    {
        if (! $this->authorizeSchool('school_publications.approve')) {
            return;
        }
        $p = $this->entity();
        $p->update(['status' => 'rejected', 'approved_at' => null, 'approved_by_name' => null]);
        notify()->success('Publication rejetée — retour en correction.');
    }

    public function publish(): void
    {
        if (! $this->authorizeSchool('school_publications.publish')) {
            return;
        }
        $p = $this->entity();
        $rule = $p->rule;

        if ($rule?->require_director_approval && ! $p->approved_at) {
            notify()->error('Approbation directeur requise avant publication.');

            return;
        }

        $n = app(PublicationEngine::class)->publishEligible($p);

        $p->loadMissing(['academicYear', 'schoolClass']);
        $dispatcher = app(\School\Support\SchoolNotificationDispatcher::class);
        $lines = $p->lines()->with('student')->where('eligible', true)->where('is_published', true)->get();
        foreach ($lines as $line) {
            if ($line->student) {
                $dispatcher->dispatch('results', $line->student, [
                    'year' => $p->academicYear?->name,
                    'class' => $p->schoolClass?->name,
                    'average' => $line->average !== null ? number_format((float) $line->average, 2) : '',
                    'mention' => $line->grade_label ?? '',
                ]);
            }
        }

        notify()->success("{$n} résultat(s) publié(s).");
    }

    protected function entity(): SchoolResultPublication
    {
        return SchoolResultPublication::query()
            ->with(['academicYear', 'schoolClass', 'rule', 'lines.student'])
            ->findOrFail($this->publicationId);
    }

    public function render()
    {
        $publication = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $rules = SchoolPublicationRule::query()->where('is_active', true)->orderBy('name')->get();

        $statusLabels = [
            'draft' => 'Brouillon',
            'pending_approval' => 'En attente d’approbation',
            'published' => 'Publié',
            'rejected' => 'Rejeté',
        ];

        return view('school::livewire.school.publications.detail', [
            'publication' => $publication,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'rules' => $rules,
            'statusLabels' => $statusLabels,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$publication->title,
            'subtitle' => $isManage ? 'Contrôles, approbation et publication.' : 'Détail de la publication.',
        ]);
    }
}
