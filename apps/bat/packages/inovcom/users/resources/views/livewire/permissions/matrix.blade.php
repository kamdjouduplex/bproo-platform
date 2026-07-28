@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-[13px] text-slate-500">{{ __('Cochez les permissions par rôle. Les changements sont enregistrés immédiatement.') }}</p>
        <div class="flex gap-2">
            <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">{{ __('Utilisateurs') }}</a>
            <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">{{ __('Rôles') }}</a>
        </div>
    </div>

    @if ($roles->isEmpty() || $permissions->isEmpty())
        <div class="card text-center py-10">
            <p class="text-slate-400 text-sm">{{ __('Créez des rôles et assurez-vous que le module utilisateurs est installé (rôles admin/cashier et permissions par défaut).') }}</p>
        </div>
    @else
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 sticky top-0 z-10">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5 min-w-[160px]">{{ __('Permission') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5 min-w-[140px]">{{ __('Clé') }}</th>
                        @foreach ($roles as $role)
                            <th class="text-center text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5 min-w-[100px]">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $perm)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="perm-{{ $perm->id }}">
                        <td class="px-4 py-2.5 text-slate-700 font-medium">{{ $perm->name }}</td>
                        <td class="px-4 py-2.5">
                            <code class="text-[11px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $perm->key }}</code>
                        </td>
                        @foreach ($roles as $role)
                            @php
                                $rid     = (string) $role->id;
                                $pid     = (string) $perm->id;
                                $checked = $matrix[$rid][$pid] ?? false;
                            @endphp
                            <td class="px-4 py-2.5 text-center">
                                <input
                                    type="checkbox"
                                    @checked($checked)
                                    wire:click="toggle({{ $role->id }}, {{ $perm->id }})"
                                    class="rounded border-slate-300 text-blue-600 w-4 h-4 cursor-pointer"
                                >
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
