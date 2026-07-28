<?php

namespace App\Http\Controllers\Admin;

use App\Models\ModuleEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleEventsExportController
{
    public function __invoke(): StreamedResponse
    {
        $tenantId = request('tenantId');
        $moduleKey = request('moduleKey');
        $action = request('action');
        $search = request('search', '');

        $events = ModuleEvent::query()
            ->with(['tenant', 'performer'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($moduleKey, fn ($q) => $q->where('module_key', $moduleKey))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($search !== '', function ($q) use ($search) {
                $q->where('module_key', 'like', '%' . $search . '%');
            })
            ->orderByDesc('created_at')
            ->get();

        $filename = 'module-events.csv';

        return response()->streamDownload(function () use ($events) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'tenant', 'module', 'action', 'performed_by']);

            foreach ($events as $event) {
                fputcsv($handle, [
                    $event->created_at,
                    $event->tenant?->name ?? '',
                    $event->module_key,
                    $event->action,
                    $event->performer?->email ?? '',
                ]);
            }

            fclose($handle);
        }, $filename);
    }
}
