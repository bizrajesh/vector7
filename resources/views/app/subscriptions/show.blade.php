<x-layouts.workspace :title="$tenant->name">
    <x-page-header :title="$tenant->name" :subtitle="$tenant->code.' · '.$tenant->email.' · '.$tenant->contact" :back="route('app.subscriptions.index')">
        @can('subscriptions.update')
            <x-confirm :action="route('app.subscriptions.status', $tenant)" :message="$tenant->status === 'active' ? 'Suspend this workspace? Its users cannot sign in until you activate it again.' : 'Activate this workspace?'" class="{{ $tenant->status === 'active' ? 'btn-danger' : 'btn-teal' }}">
                <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}">{{ $tenant->status === 'active' ? 'Suspend workspace' : 'Activate workspace' }}
            </x-confirm>
        @endcan
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <h2 class="section-title mb-3">Usage</h2>
            @include('partials.usage-table')
            <p class="mt-3 text-sm text-muted">This month: {{ $monthly['plots_launched'] }} plots launched · {{ $monthly['bookings'] }} bookings · {{ $monthly['sales'] }} sales.</p>
        </div>
        @can('subscriptions.update')
            <form method="POST" action="{{ route('app.subscriptions.update', $tenant) }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="section-title">Subscription</h2>
                <x-select name="plan_id" label="Plan" :options="$plans->pluck('name', 'id')" :value="$tenant->subscription?->plan_id" />
                <x-select name="status" label="Status" :options="['trial' => 'Trial', 'active' => 'Active', 'past_due' => 'Past due', 'expired' => 'Expired', 'cancelled' => 'Cancelled']" :value="$tenant->subscription?->status" />
                <x-field name="starts_on" type="date" label="Start" :value="$tenant->subscription?->starts_on?->toDateString()" required />
                <x-field name="ends_on" type="date" label="End" :value="$tenant->subscription?->ends_on?->toDateString()" required />
                <button class="btn-primary">Save</button>
            </form>
        @endcan
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="section-title">Usage history</h2>
            <div class="mt-3 h-64"><canvas data-chart="usage-chart" aria-label="Monthly usage chart" role="img"></canvas></div>
            <script type="application/json" id="usage-chart" nonce="{{ $cspNonce }}">{!! json_encode(['type' => 'line', 'labels' => $history->pluck('month'), 'datasets' => [
                ['label' => 'Storage (MB)', 'data' => $history->map(fn ($h) => round($h->storage_bytes / 1048576, 1)), 'borderColor' => '#0F8F84', 'backgroundColor' => '#0F8F84'],
                ['label' => 'Bookings', 'data' => $history->pluck('bookings'), 'borderColor' => '#D97706', 'backgroundColor' => '#D97706'],
                ['label' => 'Sales', 'data' => $history->pluck('sales'), 'borderColor' => '#0B1B33', 'backgroundColor' => '#0B1B33'],
                ['label' => 'AI credits', 'data' => $history->pluck('ai_credits'), 'borderColor' => '#7C3AED', 'backgroundColor' => '#7C3AED'],
            ]]) !!}</script>
        </div>
        <div class="card">
            <h2 class="section-title p-4">Largest files</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>File</th><th>Type</th><th class="num">Size</th><th>Uploaded</th></tr></thead>
                <tbody>
                @forelse ($largest as $f)
                    <tr><td class="max-w-[14rem] truncate"><a href="{{ route('files.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a></td><td class="text-xs">{{ $f->category }}</td><td class="num">{{ $f->sizeLabel() }}</td><td class="text-xs">@date($f->created_at)</td></tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No files.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="card mt-6">
        <h2 class="section-title p-4">Invoices</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Invoice</th><th>Plan</th><th>Period</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr><td class="font-mono text-sm">{{ $inv->number }}</td><td>{{ $inv->plan->name }}</td><td class="text-sm">@date($inv->period_start) – @date($inv->period_end)</td><td class="num">@inr($inv->total)</td>
                    <td><span class="{{ $inv->status === 'paid' ? 'badge-teal' : 'badge-amber' }}">{{ ucfirst($inv->status) }}</span> @if ($inv->method)<span class="text-xs text-muted">{{ $inv->method }}</span>@endif</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('app.invoices.show', $inv) }}" class="btn-ghost btn-sm">PDF</a>
                        @if ($inv->status !== 'paid')@can('subscriptions.approve')<x-confirm :action="route('app.invoices.paid', $inv)" message="Mark {{ $inv->number }} as paid and activate the plan?" class="btn-teal btn-sm">Mark paid</x-confirm>@endcan @endif
                    </td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">No invoices.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.workspace>
