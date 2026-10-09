<x-layouts.workspace title="My plan & usage">
    <x-page-header title="My plan & usage" :back="route('ws.settings.index')" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="eyebrow">Current plan</p>
                    <h2 class="mt-1 text-2xl font-extrabold">{{ $sub?->plan->name ?? 'No plan' }}</h2>
                    @if ($sub)
                        <p class="mt-1 text-sm text-muted">{{ ucfirst(str_replace('_', ' ', $sub->status)) }} · {{ \App\Support\Format::date($sub->starts_on) }} to {{ \App\Support\Format::date($sub->ends_on) }} · <strong class="text-navy">{{ $sub->daysLeft() }} days left</strong></p>
                    @endif
                </div>
                <span class="{{ $sub && $sub->isUsable() ? 'badge-teal' : 'badge-red' }}">{{ $sub ? ucfirst(str_replace('_', ' ', $sub->status)) : 'None' }}</span>
            </div>
            <div class="mt-5">@include('partials.usage-table')</div>
            <p class="mt-4 text-sm text-muted">This month: {{ $monthly['plots_launched'] }} plots launched · {{ $monthly['bookings'] }} bookings · {{ $monthly['sales'] }} sales.</p>
        </div>
        <div class="card card-pad">
            <h2 class="section-title">Enabled modules</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach (config('permissions.plan_modules') as $m => $label)
                    @php($on = $sub && ($sub->plan->modules === null || in_array($m, $sub->plan->modules)))
                    <li class="flex items-center gap-2 {{ $on ? '' : 'text-muted' }}">{!! \App\Support\Icons::svg($on ? 'check' : 'x', 'h-4 w-4 '.($on ? 'text-teal-700' : 'text-red-600')) !!} {{ $label }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <h2 class="mb-3 mt-8 text-xl font-extrabold">Upgrade or renew</h2>
    @if ($gateway === 'none')<p class="mb-3 text-sm text-muted">Online payment is not switched on yet. Choosing a plan creates an invoice; vector7 activates the plan when payment is received.</p>@endif
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($plans as $p)
            <div class="card card-pad flex flex-col {{ $sub?->plan_id === $p->id ? 'ring-2 ring-teal' : '' }}">
                <h3 class="text-lg font-extrabold">{{ $p->name }}</h3>
                <p class="mt-1 text-sm text-muted">{{ $p->description }}</p>
                <p class="mt-3 text-3xl font-extrabold">@inr($p->price)<span class="text-sm font-semibold text-muted">/{{ $p->billing_cycle === 'yearly' ? 'year' : 'month' }} + GST</span></p>
                <ul class="mt-3 flex-1 space-y-1 text-sm">
                    @foreach (['projects' => 'projects', 'storage_mb' => 'storage', 'ai_credits' => 'AI credits / month', 'users_tenant_sales' => 'sales users'] as $k => $l)
                        @php($v = $p->limit($k))
                        <li>{!! \App\Support\Icons::svg('check', 'h-4 w-4 inline text-teal-700') !!} {{ $v < 0 ? 'Unlimited' : ($k === 'storage_mb' ? \App\Support\Format::bytes($v * 1048576) : $v) }} {{ $l }}</li>
                    @endforeach
                </ul>
                @can('tenant_settings.update')
                    <form method="POST" action="{{ route('ws.settings.plan.upgrade', $p) }}" class="mt-4">@csrf
                        <button class="{{ $sub?->plan_id === $p->id ? 'btn-light' : 'btn-primary' }} w-full">{{ $sub?->plan_id === $p->id ? 'Renew' : 'Upgrade' }}</button>
                    </form>
                @endcan
            </div>
        @endforeach
    </div>

    <div class="card mt-8">
        <h2 class="section-title p-4">Invoices</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Invoice</th><th>Plan</th><th>Period</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr><td class="font-mono text-sm">{{ $inv->number }}</td><td>{{ $inv->plan->name }}</td><td class="text-sm">@date($inv->period_start) – @date($inv->period_end)</td>
                    <td class="num">@inr($inv->total)</td><td><span class="{{ $inv->status === 'paid' ? 'badge-teal' : 'badge-amber' }}">{{ ucfirst($inv->status) }}</span></td>
                    <td class="text-right"><a href="{{ route('ws.settings.invoice', $inv) }}" class="btn-ghost btn-sm">PDF</a></td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.workspace>
