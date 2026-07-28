<?php

namespace App\Events;

use InovCom\Devis\Models\Quote;
use InovCom\Projets\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a Project is auto-created from an accepted Quote. */
class ProjectCreatedFromQuote
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly Quote   $quote,
    ) {}
}
