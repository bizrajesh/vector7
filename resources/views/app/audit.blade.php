<x-layouts.workspace title="Audit log">
    <x-page-header title="Audit log" subtitle="Who did what and when, with before/after values. Passwords are never logged."><x-export-buttons /></x-page-header>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" />
        <x-select name="tenant" label="Tenant" :options="$tenants" :value="request('tenant')" placeholder="All" />
        <x-select name="action" label="Action" :options="$actions->combine($actions)" :value="request('action')" placeholder="All" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Changes</th><th>IP</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr>
                <td class="whitespace-nowrap text-xs">{{ \App\Support\Format::datetime($l->created_at) }}</td>
                <td class="text-sm">{{ $l->actor_name }}<p class="text-xs text-muted">{{ $l->actor_type }}{{ $l->tenant_id ? ' · tenant '.$l->tenant_id : '' }}</p></td>
                <td><span class="badge-navy">{{ $l->action }}</span></td>
                <td class="text-sm">{{ $l->auditable_type }} {{ $l->auditable_id }}</td>
                <td class="max-w-md text-xs">
                    @if ($l->before || $l->after)
                        <details><summary class="cursor-pointer text-teal-700">{{ count($l->after ?? $l->before ?? []) }} field(s)</summary>
                            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-2">
                                @foreach (($l->after ?? $l->before ?? []) as $k => $v)
                                    <dt class="font-semibold">{{ $k }}</dt><dd class="break-all">@if ($l->before && array_key_exists($k, $l->before))<span class="text-red-700 line-through">{{ is_scalar($l->before[$k]) ? $l->before[$k] : json_encode($l->before[$k]) }}</span> → @endif{{ is_scalar($v) ? $v : json_encode($v) }}</dd>
                                @endforeach
                            </dl></details>
                    @endif
                </td>
                <td class="text-xs">{{ $l->ip }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div><div class="p-4">{{ $logs->links() }}</div></div>
</x-layouts.workspace>
