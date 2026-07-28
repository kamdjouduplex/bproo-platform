<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\DB;

class ReturnNumberGenerator
{
    public function nextReturnNumber(): string
    {
        return $this->next('returns', 'return_number', 'RET');
    }

    public function nextCreditNoteNumber(): string
    {
        return $this->next('credit_notes', 'credit_note_number', 'AV');
    }

    public function nextRefundNumber(): string
    {
        return $this->next('refunds', 'refund_number', 'RB');
    }

    private function next(string $table, string $column, string $prefix): string
    {
        $year = now()->year;
        $like = $prefix . '-' . $year . '-%';

        $last = DB::connection('tenant')->table($table)
            ->where($column, 'like', $like)
            ->orderByDesc('id')
            ->value($column);

        $next = 1;
        if ($last && preg_match('/-(\d{6})$/', (string) $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . '-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
