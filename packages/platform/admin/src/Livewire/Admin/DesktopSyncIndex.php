<?php

namespace App\Livewire\Admin;

use App\Models\DesktopSyncBatch;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Ops view of recent desktop OUT sync batches — additive, read-only.
 */
class DesktopSyncIndex extends Component
{
    public string $filter_product = '';

    public function render()
    {
        $batches = collect();
        if (Schema::hasTable('desktop_sync_batches')) {
            $query = DesktopSyncBatch::query()
                ->with(['install', 'tenant'])
                ->orderByDesc('id')
                ->limit(100);

            if ($this->filter_product !== '') {
                $query->where('product_key', $this->filter_product);
            }

            $batches = $query->get();
        }

        return view('livewire.admin.desktop-sync-index', [
            'batches' => $batches,
        ])->layout('layouts.app', [
            'title' => 'Sync desktop OUT',
            'subtitle' => 'Backup cloud Phase 3 — événements reçus',
        ]);
    }
}
