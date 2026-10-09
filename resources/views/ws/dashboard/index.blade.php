@php($F = \App\Support\Format::class)
@php($hour = (int) now()->format('G'))
<x-layouts.workspace title="Dashboard">
    <x-page-header :title="($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening')).', '.\Illuminate\Support\Str::of($user->name)->before(' ')" :subtitle="$tenant->name.' · '.now()->format('l, d M Y')" />

    @if (count($alerts))
        <div class="mb-6 card divide-y divide-navy-50">
            @foreach (array_slice($alerts, 0, 4) as $a)
                <a href="{{ $a['link'] ?? route('ws.notifications') }}" class="flex items-center gap-3 px-4 py-3 text-sm text-navy no-underline hover:bg-page"><span class="dot {{ $a['level'] === 'red' ? 'bg-red-600' : ($a['level'] === 'amber' ? 'bg-amber-500' : 'bg-teal') }}"></span>{{ $a['text'] }}</a>
            @endforeach
        </div>
    @endif

    @if (empty($sections))
        <div class="card"><x-empty title="Nothing to show yet" icon="home">Your role has no dashboard sections. Ask your admin for access.</x-empty></div>
    @endif

    @foreach ($sections as $section)
        @if ($section === 'admin')
            @php($a = $d['admin'])
            <section class="mb-8" aria-labelledby="dash-admin">
                <h2 id="dash-admin" class="section-title mb-3">Business overview</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <x-stat label="Projects running" :value="$a['stats']['active']" icon="folder" tone="navy" />
                    <x-stat label="Launched" :value="$a['stats']['launched']" icon="rocket" />
                    <x-stat label="Plots available" :value="$F::num($a['stats']['available'])" icon="grid" />
                    <x-stat label="Sales value" :value="$F::inrShort($a['stats']['salesValue'])" icon="cart" tone="navy" />
                    <x-stat label="Collected" :value="$F::inrShort($a['stats']['collected'])" icon="rupee" />
                    <x-stat label="Overdue" :value="$F::inrShort($a['stats']['overdue'])" icon="alert" :tone="$a['stats']['overdue'] > 0 ? 'red' : 'teal'" />
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="card card-pad"><h3 class="font-bold">Projects by status</h3>
                        @if (array_sum($a['statusChart']['datasets'][0]['data']))<x-chart id="ch-status" :config="$a['statusChart']" label="Projects by status" />@else<p class="mt-6 text-sm text-muted">No projects yet. <a href="{{ route('ws.projects.create') }}">Create the first one</a>.</p>@endif
                    </div>
                    <div class="card card-pad lg:col-span-2"><h3 class="font-bold">Budget vs actual</h3>
                        @if (count($a['budgetChart']['labels']))<x-chart id="ch-budget" :config="$a['budgetChart']" label="Budget against spend by project" />@else<p class="mt-6 text-sm text-muted">Budgets appear once a project has an estimate.</p>@endif
                    </div>
                    <div class="card card-pad lg:col-span-2"><h3 class="font-bold">Plot inventory</h3>
                        @if (count($a['inventoryChart']['labels']))<x-chart id="ch-inv" :config="$a['inventoryChart']" label="Plots by status for each launched layout" />@else<p class="mt-6 text-sm text-muted">Launch a layout to see its plots here.</p>@endif
                    </div>
                    <div class="card card-pad"><h3 class="font-bold">Collections, last 6 months</h3><x-chart id="ch-coll" :config="$a['collectionChart']" label="Money collected per month" /></div>
                </div>
            </section>
        @elseif ($section === 'manager')
            @php($m = $d['manager'])
            <section class="mb-8" aria-labelledby="dash-manager">
                <h2 id="dash-manager" class="section-title mb-3">Projects in progress</h2>
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="card lg:col-span-2">
                        <ul class="divide-y divide-navy-50">
                            @forelse ($m['running'] as $p)
                                <li class="p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2"><a href="{{ route('ws.tracking.show', $p) }}" class="font-semibold">{{ $p->name }}</a><span class="text-sm tabular-nums text-muted">{{ number_format((float) $p->progress_pct, 0) }}% · ends @date($p->est_end_date)</span></div>
                                    <x-progress class="mt-2" :pct="$p->progress_pct" level="teal" :label="$p->name.' progress'" />
                                    <div class="mt-2 flex gap-0.5" aria-hidden="true">@foreach ($p->stages->sortBy('stage_no') as $s)<span class="h-1.5 flex-1 rounded-full {{ $s->status === 'done' ? 'bg-teal-600' : ($s->status === 'in_progress' ? 'bg-amber-400' : 'bg-navy-50') }}" title="{{ $s->name }}"></span>@endforeach</div>
                                </li>
                            @empty
                                <li><x-empty title="No projects in progress" icon="folder" /></li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="space-y-4">
                        <div class="card">
                            <h3 class="p-4 font-bold">Delayed sub-tasks <span class="{{ $m['delayedCount'] ? 'badge-red' : 'badge-teal' }}">{{ $m['delayedCount'] }}</span></h3>
                            <ul class="divide-y divide-navy-50 text-sm">
                                @forelse ($m['delayed'] as $t)
                                    <li class="px-4 py-2.5"><a href="{{ route('ws.tracking.show', $t->project) }}" class="font-semibold">{{ $t->short_name }}</a><p class="text-xs text-muted">{{ $t->project->name }} · due @date($t->planned_end) · {{ (int) $t->planned_end->diffInDays(today()) }} days late</p></li>
                                @empty
                                    <li class="px-4 pb-4 text-muted">Nothing is running late.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="card">
                            <h3 class="p-4 font-bold">Budget alerts</h3>
                            <ul class="divide-y divide-navy-50 text-sm">
                                @forelse ($m['budgetAlerts'] as $r)
                                    @php($pct = $r['spent'] / $r['budget'] * 100)
                                    <li class="px-4 py-2.5"><div class="flex justify-between"><span class="font-semibold">{{ $r['project']->name }}</span><span class="tabular-nums {{ $pct >= 100 ? 'text-red-700' : 'text-amber-800' }}">{{ number_format($pct, 0) }}%</span></div><p class="text-xs text-muted">@inr($r['spent']) of @inr($r['budget'])</p></li>
                                @empty
                                    <li class="px-4 pb-4 text-muted">Every project is under {{ (float) $m['alertPct'] }}% of budget.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        @elseif ($section === 'sales')
            @php($s = $d['sales'])
            <section class="mb-8" aria-labelledby="dash-sales">
                <h2 id="dash-sales" class="section-title mb-3">Sales desk</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <x-stat label="Active bookings" :value="$s['activeBookings']" icon="calendar" tone="gold" />
                    <x-stat label="Expiring in 3 days" :value="$s['expiring']->count()" icon="clock" :tone="$s['expiring']->count() ? 'red' : 'teal'" />
                    <x-stat label="Open enquiries" :value="$s['enquiries']" icon="chat" tone="navy" :sub="$s['newEnquiries'].' new'" />
                    <x-stat label="Booking → sale (90 days)" :value="$s['conversion'].'%'" icon="refresh" />
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="card">
                        <h3 class="p-4 font-bold">Bookings about to expire</h3>
                        <ul class="divide-y divide-navy-50 text-sm">
                            @forelse ($s['expiring'] as $b)
                                <li class="px-4 py-2.5"><a href="{{ route('ws.bookings.show', $b) }}" class="font-semibold">Plot {{ $b->plot->plot_no }} · {{ $b->customer->name }}</a><p class="text-xs {{ $b->valid_till->lte(today()) ? 'font-bold text-red-700' : 'text-muted' }}">{{ $b->project->name }} · held till @date($b->valid_till)</p></li>
                            @empty
                                <li class="px-4 pb-4 text-muted">No bookings expire in the next 3 days.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card">
                        <h3 class="p-4 font-bold">Instalments due this week</h3>
                        <ul class="divide-y divide-navy-50 text-sm">
                            @forelse ($s['due'] as $i)
                                <li class="flex items-start justify-between gap-3 px-4 py-2.5"><span><span class="font-semibold">{{ $i->sale->customer->name }}</span><span class="block text-xs {{ $i->due_date->lt(today()) ? 'font-bold text-red-700' : 'text-muted' }}">Plot {{ $i->sale->plot->plot_no }} · {{ $i->due_date->lt(today()) ? 'overdue since' : 'due' }} @date($i->due_date)</span></span><span class="tabular-nums font-semibold">@inr($i->balance())</span></li>
                            @empty
                                <li class="px-4 pb-4 text-muted">Nothing due this week.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card card-pad"><h3 class="font-bold">Bookings per month</h3><x-chart id="ch-book" :config="$s['bookingChart']" label="Bookings and conversions per month" /></div>
                </div>
            </section>
        @elseif ($section === 'account')
            @php($c = $d['account'])
            <section class="mb-8" aria-labelledby="dash-account">
                <h2 id="dash-account" class="section-title mb-3">Money</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <x-stat label="Collected today" :value="$F::inrShort($c['today'])" icon="rupee" />
                    <x-stat label="Collected this month" :value="$F::inrShort($c['month'])" icon="receipt" tone="navy" />
                    <x-stat label="Spent this month" :value="$F::inrShort($c['expMonth'])" icon="doc" tone="gold" />
                    <x-stat label="Receivable" :value="$F::inrShort($c['receivable'])" icon="clock" tone="navy" />
                    <x-stat label="Overdue" :value="$F::inrShort($c['overdue'])" icon="alert" :tone="$c['overdue'] > 0 ? 'red' : 'teal'" />
                </div>
                <div class="mt-4 card card-pad"><div class="flex items-center justify-between"><h3 class="font-bold">Received vs spent</h3><a href="{{ route('ws.accounts.daybook') }}" class="text-sm font-semibold">Day book</a></div><x-chart id="ch-cash" :config="$c['cashChart']" label="Money received and spent per month" /></div>
            </section>
        @elseif ($section === 'support')
            @php($h = $d['support'])
            <section class="mb-8" aria-labelledby="dash-support">
                <h2 id="dash-support" class="section-title mb-3">Enquiries & help desk</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card">
                        <div class="flex items-center justify-between p-4"><h3 class="font-bold">Open enquiries <span class="badge-navy">{{ $h['enquiryCount'] }}</span></h3>@can('enquiries.view')<a href="{{ route('ws.enquiries.index') }}" class="text-sm font-semibold">All</a>@endcan</div>
                        <ul class="divide-y divide-navy-50 text-sm">
                            @forelse ($h['enquiries'] as $e)
                                <li class="px-4 py-2.5"><span class="font-semibold">{{ $e->name }}</span> <span class="{{ $e->status === 'new' ? 'badge-amber' : 'badge-gray' }}">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span><p class="text-xs text-muted">{{ $e->project?->name ?? 'General' }} · {{ $e->created_at->diffForHumans() }}{{ $e->follow_up_on ? ' · follow up '.$F::date($e->follow_up_on) : '' }}</p></li>
                            @empty
                                <li class="px-4 pb-4 text-muted">No open enquiries.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card">
                        <div class="flex items-center justify-between p-4"><h3 class="font-bold">Open tickets <span class="badge-navy">{{ $h['ticketCount'] }}</span> @if ($h['breached'])<span class="badge-red">{{ $h['breached'] }} past SLA</span>@endif</h3>@can('tickets.view')<a href="{{ route('ws.tickets.index') }}" class="text-sm font-semibold">All</a>@endcan</div>
                        <ul class="divide-y divide-navy-50 text-sm">
                            @forelse ($h['tickets'] as $t)
                                <li class="px-4 py-2.5"><a href="{{ route('ws.tickets.show', $t) }}" class="font-semibold">{{ $t->subject }}</a><p class="text-xs {{ $t->sla_due_at && $t->sla_due_at->isPast() ? 'font-bold text-red-700' : 'text-muted' }}">{{ $t->number }} · {{ \App\Models\Ticket::PRIORITIES[$t->priority] ?? $t->priority }} · {{ $t->sla_due_at ? 'reply by '.$t->sla_due_at->format('d M, h:i A') : '' }}</p></li>
                            @empty
                                <li class="px-4 pb-4 text-muted">No open tickets.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </section>
        @endif
    @endforeach
</x-layouts.workspace>
