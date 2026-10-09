<x-layouts.workspace title="AI usage">
    <x-page-header title="AI usage log" :subtitle="'Monthly cap: '.number_format($cap).' credits (1 credit = 1 request)'" :back="route('app.settings.index', ['tab' => 'ai'])" />
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($byFeature as $f)
            <x-stat :label="str_replace('_', ' ', $f->feature)" :value="number_format($f->c)" icon="sparkles" :sub="number_format($f->i).' in · '.number_format($f->o).' out tokens'" />
        @empty
            <div class="card card-pad text-sm text-muted sm:col-span-4">No AI requests this month.</div>
        @endforelse
    </div>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>When</th><th>Tenant</th><th>Feature</th><th>Model</th><th class="num">In</th><th class="num">Out</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr><td class="whitespace-nowrap text-xs">{{ \App\Support\Format::datetime($l->created_at) }}</td><td>{{ $l->tenant_id ?? 'App' }}</td><td>{{ $l->feature }}</td><td class="text-xs">{{ $l->model }}</td>
                <td class="num">{{ number_format($l->input_tokens) }}</td><td class="num">{{ number_format($l->output_tokens) }}</td>
                <td><span class="{{ $l->status === 'ok' ? 'badge-teal' : 'badge-red' }}" @if ($l->error) title="{{ $l->error }}" @endif>{{ $l->status }}</span></td></tr>
        @endforeach
        </tbody>
    </table></div><div class="p-4">{{ $logs->links() }}</div></div>
</x-layouts.workspace>
