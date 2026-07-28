<?php

namespace InovCom\Items\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;

class ItemsDeleteService
{
    /**
     * @return list<string> Human-readable blocking contexts (e.g. "des achats (2 ligne(s))")
     */
    public function blockingReasons(Item $item): array
    {
        $reasons = [];
        $db = DB::connection('tenant');

        if (Schema::connection('tenant')->hasTable('purchase_lines')) {
            $count = $db->table('purchase_lines')->where('item_id', $item->id)->count();
            if ($count > 0) {
                $reasons[] = "des achats ({$count} ligne(s))";
            }
        }

        if (Schema::connection('tenant')->hasTable('sale_lines')) {
            $count = $db->table('sale_lines')->where('item_id', $item->id)->count();
            if ($count > 0) {
                $reasons[] = "des ventes ({$count} ligne(s))";
            }
        }

        return $reasons;
    }

    public function canDelete(Item $item): bool
    {
        return $this->blockingReasons($item) === [];
    }

    public function deleteBlockedMessage(Item $item): ?string
    {
        $reasons = $this->blockingReasons($item);
        if ($reasons === []) {
            return null;
        }

        return 'Impossible de supprimer cet article : il est référencé dans '
            . implode(' et ', $reasons)
            . '. Désactivez l\'article pour le retirer du catalogue.';
    }

    public function delete(Item $item): void
    {
        $blocked = $this->deleteBlockedMessage($item);
        if ($blocked !== null) {
            throw new \RuntimeException($blocked);
        }

        try {
            DB::connection('tenant')->transaction(fn () => $item->delete());
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new \RuntimeException(
                    'Impossible de supprimer cet article : il est utilisé par d\'autres enregistrements. Désactivez l\'article à la place.',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return $code === '23503'
            || str_contains($e->getMessage(), '23503')
            || str_contains($e->getMessage(), 'Foreign key violation');
    }
}
