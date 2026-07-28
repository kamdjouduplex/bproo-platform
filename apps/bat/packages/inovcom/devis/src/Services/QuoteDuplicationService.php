<?php

namespace InovCom\Devis\Services;

use Illuminate\Support\Facades\DB;
use InovCom\Devis\Models\Quote;
use InovCom\Devis\Models\QuoteLine;

class QuoteDuplicationService
{
    /**
     * New version in the same quote family (same code, parent_id chain).
     */
    public function revise(Quote $source): Quote
    {
        return $this->duplicateRevision($source);
    }

    /** @deprecated Use revise() — kept for backward compatibility */
    public function duplicate(Quote $source): Quote
    {
        return $this->revise($source);
    }

    /**
     * Independent copy: new code, new quote family (quick template duplicate).
     */
    public function copyAsNew(Quote $source): Quote
    {
        return DB::connection('tenant')->transaction(function () use ($source) {
            $source->loadMissing('lines');

            $newQuote = $source->replicate([
                'code',
                'status',
                'sent_at',
                'accepted_at',
                'refused_at',
                'refuse_reason',
                'expired_at',
                'last_reminder_at',
                'parent_id',
                'version',
            ]);

            $newQuote->code = $this->generateNextCode();
            $newQuote->status = 'draft';
            $newQuote->parent_id = null;
            $newQuote->version = 1;
            $newQuote->sent_at = null;
            $newQuote->accepted_at = null;
            $newQuote->refused_at = null;
            $newQuote->refuse_reason = null;
            $newQuote->expired_at = null;
            $newQuote->last_reminder_at = null;
            $newQuote->save();

            $this->copyLines($source, $newQuote);

            return $newQuote->fresh(['lines', 'client', 'offer']);
        });
    }

    private function duplicateRevision(Quote $source): Quote
    {
        return DB::connection('tenant')->transaction(function () use ($source) {
            $source->loadMissing('lines');

            $rootId = $source->familyRootId();
            $root = Quote::on('tenant')->findOrFail($rootId);

            $maxVersion = (int) Quote::on('tenant')
                ->where(function ($q) use ($rootId) {
                    $q->where('id', $rootId)->orWhere('parent_id', $rootId);
                })
                ->max('version');
            $newVersion = $maxVersion + 1;

            $newQuote = $source->replicate([
                'code',
                'status',
                'sent_at',
                'accepted_at',
                'refused_at',
                'refuse_reason',
                'expired_at',
                'last_reminder_at',
            ]);

            $newQuote->code = $root->code;
            $newQuote->status = 'draft';
            $newQuote->parent_id = $rootId;
            $newQuote->version = $newVersion;
            $newQuote->sent_at = null;
            $newQuote->accepted_at = null;
            $newQuote->refused_at = null;
            $newQuote->refuse_reason = null;
            $newQuote->expired_at = null;
            $newQuote->last_reminder_at = null;
            $newQuote->save();

            $this->copyLines($source, $newQuote);

            return $newQuote->fresh(['lines', 'client', 'offer']);
        });
    }

    private function copyLines(Quote $source, Quote $newQuote): void
    {
        foreach ($source->lines as $position => $line) {
            QuoteLine::create([
                'quote_id'         => $newQuote->id,
                'position'         => $position,
                'item_id'          => $line->item_id,
                'description'      => $line->description,
                'quantity'         => $line->quantity,
                'unit'             => $line->unit,
                'unit_price'       => $line->unit_price,
                'discount_percent' => $line->discount_percent,
                'cost'             => $line->cost,
                'amount'           => $line->amount,
                'line_type'        => $line->line_type,
            ]);
        }
    }

    private function generateNextCode(): string
    {
        $max = Quote::on('tenant')
            ->where('code', 'like', 'DEV%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'DEV' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
