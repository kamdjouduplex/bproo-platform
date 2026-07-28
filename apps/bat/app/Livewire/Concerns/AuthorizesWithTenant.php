<?php

namespace App\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Drop-in replacement for Livewire's built-in authorize() that works with
 * the 'tenant' authentication guard.
 *
 * Usage in any Livewire component:
 *
 *   use App\Livewire\Concerns\AuthorizesWithTenant;
 *
 *   class QuoteForm extends Component
 *   {
 *       use AuthorizesWithTenant;
 *
 *       public function mount(): void
 *       {
 *           $this->tenantAuthorize('devis.view');
 *       }
 *
 *       public function save(): void
 *       {
 *           $this->tenantAuthorize($this->quoteId ? 'devis.edit' : 'devis.create');
 *       }
 *   }
 */
trait AuthorizesWithTenant
{
    /**
     * Authorize the current tenant user for the given ability.
     * Throws AuthorizationException (HTTP 403) if denied.
     */
    protected function tenantAuthorize(string $ability): void
    {
        $user = auth('tenant')->user() ?? auth()->user();

        if (!$user) {
            throw new AuthorizationException("Non authentifié.");
        }

        if (!Gate::forUser($user)->allows($ability)) {
            throw new AuthorizationException(
                "Action non autorisée : {$ability}"
            );
        }
    }

    /**
     * Check if the current tenant user has the given ability (returns bool).
     */
    protected function tenantCan(string $ability): bool
    {
        $user = auth('tenant')->user() ?? auth()->user();
        if (!$user) {
            return false;
        }
        return Gate::forUser($user)->allows($ability);
    }
}
