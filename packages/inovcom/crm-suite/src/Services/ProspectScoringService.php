<?php

namespace InovCom\Crm\Services;

use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;

class ProspectScoringService
{
    public const TEMP_FROID = 'froid';

    public const TEMP_TIEDE = 'tiede';

    public const TEMP_CHAUD = 'chaud';

    public static function needOptions(): array
    {
        return [
            0 => 'Aucun besoin identifié',
            10 => 'Besoin général',
            20 => 'Besoin précis',
        ];
    }

    public static function decisionOptions(): array
    {
        return [
            0 => 'Décideur inconnu',
            5 => 'Influenceur',
            15 => 'Décideur identifié',
        ];
    }

    public static function budgetOptions(): array
    {
        return [
            0 => 'Budget inconnu',
            5 => 'Budget possible',
            15 => 'Budget confirmé',
        ];
    }

    public static function timelineOptions(): array
    {
        return [
            0 => 'Échéance inconnue',
            5 => 'Plus de 6 mois',
            10 => 'Moins de 3 mois',
            15 => 'Moins d’1 mois',
        ];
    }

    public static function interactionOptions(): array
    {
        return [
            0 => 'Aucune interaction',
            5 => 'Interaction récente',
            10 => 'Forte interaction',
        ];
    }

    public function recalculate(Prospect $prospect): Prospect
    {
        $interaction = $this->computeInteractionScore($prospect);
        $need = (int) ($prospect->need_score ?? 0);
        $decision = (int) ($prospect->decision_score ?? 0);
        $budget = (int) ($prospect->budget_score ?? 0);
        $timeline = (int) ($prospect->timeline_score ?? 0);

        $score = max(0, min(100, $need + $decision + $budget + $timeline + $interaction));

        $prospect->interaction_score = $interaction;
        $prospect->score = $score;
        $prospect->save();

        return $prospect;
    }

    public function computeInteractionScore(Prospect $prospect): int
    {
        $recentCount = ProspectActivity::query()
            ->where('prospect_id', $prospect->id)
            ->where('type', '!=', ProspectActivity::TYPE_STATUS)
            ->where('created_at', '>=', now()->subDays(14))
            ->count();

        $last = $prospect->last_contacted_at
            ?: ProspectActivity::query()
                ->where('prospect_id', $prospect->id)
                ->where('type', '!=', ProspectActivity::TYPE_STATUS)
                ->max('created_at');

        if ($recentCount >= 3) {
            return 10;
        }
        if ($last && now()->parse($last)->gte(now()->subDays(7))) {
            return 5;
        }

        return 0;
    }

    public static function temperature(int $score): string
    {
        if ($score >= 60) {
            return self::TEMP_CHAUD;
        }
        if ($score >= 30) {
            return self::TEMP_TIEDE;
        }

        return self::TEMP_FROID;
    }

    public static function temperatureLabel(int $score): string
    {
        return match (self::temperature($score)) {
            self::TEMP_CHAUD => 'Chaud',
            self::TEMP_TIEDE => 'Tiède',
            default => 'Froid',
        };
    }

    public static function temperatureHint(int $score): string
    {
        return match (self::temperature($score)) {
            self::TEMP_CHAUD => 'Prospect chaud — prioriser le suivi.',
            self::TEMP_TIEDE => 'Prospect tiède — qualifier davantage.',
            default => 'Prospect froid — besoin encore flou.',
        };
    }
}
