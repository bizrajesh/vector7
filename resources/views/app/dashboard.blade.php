@php($F = \App\Support\Format::class)
<x-layouts.workspace title="Dashboards">
    <x-page-header title="Dashboards" subtitle="Across every tenant on vector7. Filter by tenant, location or period." />
    <x-filters>
        <x-select name="tenant" label="Tenant" :options="$tenants" :value="request('tenant')" placeholder="All tenants" />
        <x-select name="location" label="District" :options="$locations" :value="request('location')" placeholder="All districts" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
    </x-filters>
    <nav class="tabs mb-6" aria-label="Dashboard sections">
        @foreach (['op' => 'Operational', 'sub' => 'Subscription', 'cust' => 'Customers', 'proj' => 'Projects', 'sales' => 'Bookings & sales'] as $k => $l)
            <a href="#{{ $k }}" class="tab">{{ $l }}</a>
        @endforeach
    </nav>

    <section id="op" class="mb-10 scroll-mt-20" aria-labelledby="h-op">
        <h2 id="h-op" class="section-title mb-3">Operational</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Tenants" :value="$d['op']['tenants']" icon="building" tone="navy" :sub="$d['op']['activeTenants'].' active'" />
            <x-stat label="Projects" :value="$d['op']['projects']" icon="folder" :sub="$d['op']['launched'].' launched'" />
            <x-stat label="Open tickets" :value="$d['op']['openTickets']" icon="lifebuoy" tone="gold" :sub="$d['op']['newEnquiries'].' new enquiries'" />
            <x-stat label="Background jobs" :value="$d['op']['queued'].' queued'" icon="database" :tone="$d['op']['failedJobs'] ? 'red' : 'teal'" :sub="$d['op']['failedJobs'].' failed'" />
        </div>
    </section>

    <section id="sub" class="mb-10 scroll-mt-20" aria-labelledby="h-sub">
        <h2 id="h-sub" class="section-title mb-3">Subscription</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Monthly recurring revenue" :value="$F::inr($d['sub']['mrr'], 0)" icon="rupee" />
            <x-stat label="Paying tenants" :value="$d['sub']['active']" icon="building" tone="navy" :sub="$d['sub']['trial'].' on trial'" />
            <x-stat label="Renewals in 30 days" :value="$d['sub']['renewals']" icon="calendar" tone="gold" />
            <x-stat label="Storage used" :value="$F::bytes($d['sub']['storageUsed'] * 1048576)" icon="database" :sub="$d['sub']['storageLimit'] ? 'of '.$F::bytes($d['sub']['storageLimit'] * 1048576).' allowed ('.round($d['sub']['storageUsed'] / max(1, $d['sub']['storageLimit']) * 100, 1).'%)' : null" />
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="card card-pad"><h3 class="font-bold">Tenants by plan</h3><x-chart id="ch-plan" :config="$d['sub']['planChart']" label="Tenants by plan" /></div>
            <div class="card lg:col-span-2">
                <h3 class="p-4 font-bold">Near or over a plan limit</h3>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Tenant</th><th>Plan</th><th>Closest limit</th><th class="w-40">Used</th></tr></thead>
                    <tbody>
                    @forelse ($d['sub']['near'] as $u)
                        @php($row = $u['rows']->sortByDesc('pct')->first())
                        <tr>
                            <td>@can('subscriptions.view')<a href="{{ route('app.subscriptions.show', $u['tenant']) }}" class="font-semibold">{{ $u['tenant']->name }}</a>@else{{ $u['tenant']->name }}@endcan</td>
                            <td>{{ $u['tenant']->subscription?->plan?->name }}</td>
                            <td>{{ $row['label'] }}<p class="text-xs text-muted">{{ $row['display']['used'] }} of {{ $row['display']['limit'] }} · {{ $row['display']['left'] }} left</p></td>
                            <td><x-progress :pct="$row['pct']" :label="$row['label']" /><p class="mt-1 text-xs tabular-nums">{{ $row['pct'] }}%</p></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-sm text-muted">Every tenant is under 80% of every limit.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </section>

    <section id="cust" class="mb-10 scroll-mt-20" aria-labelledby="h-cust">
        <h2 id="h-cust" class="section-title mb-3">Customers</h2>
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="grid gap-3">
                <x-stat label="Customer accounts" :value="$F::num($d['cust']['total'])" icon="users" tone="navy" />
                <x-stat label="Signed in, last 30 days" :value="$F::num($d['cust']['active'])" icon="user" />
                <x-stat label="Sign-ups in period" :value="$F::num($d['cust']['new'])" icon="plus" tone="gold" />
            </div>
            <div class="card card-pad lg:col-span-2"><h3 class="font-bold">Sign-ups per month</h3><x-chart id="ch-cust" :config="$d['cust']['chart']" label="Customer sign-ups per month" /></div>
        </div>
    </section>

    <section id="proj" class="mb-10 scroll-mt-20" aria-labelledby="h-proj">
        <h2 id="h-proj" class="section-title mb-3">Projects by stage</h2>
        <div class="card card-pad"><x-chart id="ch-proj" :config="$d['proj']['chart']" label="Projects by status" /></div>
    </section>

    <section id="sales" class="mb-10 scroll-mt-20" aria-labelledby="h-sales">
        <h2 id="h-sales" class="section-title mb-3">Bookings & sales</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat label="Bookings" :value="$F::num($d['sales']['bookings'])" icon="calendar" tone="gold" :sub="$F::inrShort($d['sales']['bookingValue'])" />
            <x-stat label="Sales" :value="$F::num($d['sales']['sales'])" icon="cart" tone="navy" />
            <x-stat label="Sales value" :value="$F::inrShort($d['sales']['salesValue'])" icon="rupee" />
            <x-stat label="Collected" :value="$F::inrShort($d['sales']['collected'])" icon="receipt" />
            <x-stat label="Booking → sale" :value="$d['sales']['conversion'].'%'" icon="refresh" tone="navy" />
        </div>
        <div class="mt-4 card card-pad"><h3 class="font-bold">Value per month</h3><x-chart id="ch-sales" :config="$d['sales']['chart']" label="Booking and sales value per month" /></div>
    </section>
</x-layouts.workspace>
