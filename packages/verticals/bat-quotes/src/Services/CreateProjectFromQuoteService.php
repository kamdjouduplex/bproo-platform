<?php

namespace InovCom\Devis\Services;

use App\Events\ProjectCreatedFromQuote;
use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Projets\Models\Project;
use Illuminate\Support\Facades\Log;

class CreateProjectFromQuoteService
{
    /**
     * Create a project from an accepted quote (idempotent).
     */
    public function create(Quote $quote, bool $dispatchEvent = true): Project
    {
        if (!class_exists(Project::class)) {
            throw new \RuntimeException(__('Le module projets n\'est pas disponible.'));
        }

        if ($quote->status !== 'accepted') {
            throw new \RuntimeException(__('Seuls les devis acceptés peuvent générer un projet.'));
        }

        $existing = Project::on('tenant')->where('quote_id', $quote->id)->first();
        if ($existing) {
            return $existing;
        }

        $quote->loadMissing(['client', 'offer']);

        if (($quote->offer?->category ?? null) === ServiceCatalog::OFFER_MAINTENANCE) {
            throw new \RuntimeException(
                __('Ce devis maintenance doit générer un contrat, pas un projet.')
            );
        }

        $projectType = ServiceCatalog::offerToExecutionType($quote->offer?->category);

        $project = Project::create([
            'code'         => $this->generateNextCode($projectType),
            'quote_id'     => $quote->id,
            'client_id'    => $quote->client_id,
            'title'        => $quote->title,
            'status'       => 'planned',
            'project_type' => $projectType,
            'budget'       => $quote->total_ttc,
            'assigned_to' => null,
            'notes'       => __('Projet créé automatiquement depuis le devis :code.', ['code' => $quote->code]),
        ]);

        Log::info('CreateProjectFromQuote: project created.', [
            'project_code' => $project->code,
            'quote_code'   => $quote->code,
        ]);

        if ($dispatchEvent) {
            ProjectCreatedFromQuote::dispatch($project, $quote);
        }

        return $project;
    }

    private function generateNextCode(string $projectType = 'construction'): string
    {
        $prefix = $projectType === ServiceCatalog::EXEC_SERVICE ? 'PST' : 'PRJ';
        $max = Project::on('tenant')
            ->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, strlen($prefix)))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return $prefix . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
