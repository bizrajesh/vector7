<x-layouts.workspace title="Budget vs actual">
    <x-page-header title="Budget vs actual" subtitle="Estimate total against money actually spent. You get an alert when a project crosses {{ (float) $alertPct }}% of its budget." />
    @include('ws.accounts._nav')
    @if ($rows->isEmpty())
        <div class="card"><x-empty title="No estimates yet" icon="clipboard">Budgets come from a project's estimate. Create an estimate first.</x-empty></div>
    @else
        <div class="card mb-6"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Project</th><th class="num">Budget</th><th class="num">Spent</th><th class="num">Left</th><th class="w-48">Used</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                @php($pct = $r['budget'] > 0 ? $r['spent'] / $r['budget'] * 100 : 0)
                <tr class="{{ $selected && $selected->id === $r['project']->id ? 'bg-teal-50/50' : '' }}">
                    <td><a href="{{ route('ws.accounts.budget', ['project' => $r['project']->id]) }}" class="font-semibold">{{ $r['project']->name }}</a><p class="text-xs text-muted">{{ $r['project']->location }}</p></td>
                    <td class="num">@inr($r['budget'])</td>
                    <td class="num">@inr($r['spent'])</td>
                    <td class="num {{ $r['budget'] - $r['spent'] < 0 ? 'font-bold text-red-700' : '' }}">@inr($r['budget'] - $r['spent'])</td>
                    <td><x-progress :pct="$pct" :level="$pct >= 100 ? 'red' : ($pct >= $alertPct ? 'amber' : 'teal')" label="Budget used" /><p class="mt-1 text-xs tabular-nums">{{ number_format($pct, 1) }}%</p></td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
        @if ($selected)
            <section class="card">
                <h2 class="section-title p-4">{{ $selected->name }} — by stage and facility</h2>
                @php($byStage = \App\Models\Expense::where('project_id', $selected->id)->whereNotNull('project_stage_id')->groupBy('project_stage_id')->selectRaw('project_stage_id, SUM(amount) s')->pluck('s', 'project_stage_id'))
                @php($byLine = \App\Models\Expense::where('project_id', $selected->id)->whereNotNull('estimate_line_id')->groupBy('estimate_line_id')->selectRaw('estimate_line_id, SUM(amount) s')->pluck('s', 'estimate_line_id'))
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Item</th><th class="num">Budget</th><th class="num">Spent</th><th class="num">Variance</th></tr></thead>
                    <tbody>
                    @foreach ($selected->stages->sortBy('stage_no') as $s)
                        @php($spent = (float) ($byStage[$s->id] ?? 0))
                        @continue($s->budget <= 0 && $spent <= 0)
                        <tr><td>{{ $s->stage_no }}. {{ $s->name }}</td><td class="num">@inr($s->budget)</td><td class="num">@inr($spent)</td><td class="num {{ $s->budget - $spent < 0 ? 'text-red-700 font-semibold' : '' }}">@inr($s->budget - $spent)</td></tr>
                    @endforeach
                    @foreach ($selected->estimate->lines->where('line_type', 'facility') as $l)
                        @php($spent = (float) ($byLine[$l->id] ?? 0))
                        <tr><td>{{ $l->description }} <span class="badge-gray">Facility</span></td><td class="num">@inr($l->amount)</td><td class="num">@inr($spent)</td><td class="num {{ $l->amount - $spent < 0 ? 'text-red-700 font-semibold' : '' }}">@inr($l->amount - $spent)</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif
    @endif
</x-layouts.workspace>
