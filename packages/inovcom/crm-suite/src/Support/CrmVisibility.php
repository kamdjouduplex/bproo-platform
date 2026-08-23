<?php

namespace InovCom\Crm\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InovCom\Crm\Concerns\AuthorizesCrmActions;

class CrmVisibility
{
    use AuthorizesCrmActions;

    public function seesAll(): bool
    {
        return $this->canCrm('crm.manage');
    }

    public function currentUserId(): ?int
    {
        $id = Auth::guard('tenant')->id();

        return $id ? (int) $id : null;
    }

    public function restrictOwner(Builder $query, string $column = 'owner_id'): Builder
    {
        if ($this->seesAll()) {
            return $query;
        }

        $uid = $this->currentUserId();
        if (! $uid) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $uid);
    }
}
