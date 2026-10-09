<x-layouts.workspace :title="$c->name">
    <x-page-header :title="$c->name" :subtitle="$c->email.' · '.($c->mobile ?: 'no mobile')" :back="route('app.customers.index')">
        @include('partials.generate-form', ['action' => route('app.customers.generate', $c), 'name' => $c->name])
        <x-confirm :action="route('app.customers.reset-link', $c)" message="Email a password-reset link to {{ $c->email }}?">Send reset link</x-confirm>
        @if ($c->isLocked())<x-confirm :action="route('app.customers.unlock', $c)" message="Unlock {{ $c->name }}?">Unlock</x-confirm>@endif
        <x-confirm :action="route('app.customers.toggle', $c)" :message="$c->is_active ? 'Disable this customer?' : 'Enable this customer?'">{{ $c->is_active ? 'Disable' : 'Enable' }}</x-confirm>
    </x-page-header>
    @include('partials.generated-password')
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Bookings" :value="$bookings->count()" icon="calendar" />
                <x-stat label="Purchases" :value="$sales->count()" icon="cart" tone="navy" />
                <x-stat label="Paid in total" :value="\App\Support\Format::inr($payments->sum('amount'))" icon="rupee" tone="gold" />
            </div>
            <div class="card">
                <h2 class="section-title p-4">Bookings</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Booking</th><th>Plot</th><th class="num">Net price</th><th>Booked</th><th>Status</th></tr></thead>
                    <tbody>@forelse ($bookings as $b)<tr><td class="font-mono text-xs">{{ $b->booking_no }}</td><td>Plot {{ $b->plot?->plot_no }} · {{ $b->project?->name }}</td><td class="num">@inr($b->net_price)</td><td>@date($b->booked_on)</td><td><span class="badge-gray">{{ \App\Models\Booking::STATUSES[$b->status] ?? $b->status }}</span></td></tr>@empty<tr><td colspan="5" class="text-sm text-muted">No bookings.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
            <div class="card">
                <h2 class="section-title p-4">Purchases</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Sale</th><th>Plot</th><th class="num">Net price</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th></tr></thead>
                    <tbody>@forelse ($sales as $s)<tr><td class="font-mono text-xs">{{ $s->sale_no }}</td><td>Plot {{ $s->plot?->plot_no }} · {{ $s->project?->name }}</td><td class="num">@inr($s->net_price)</td><td class="num">@inr($s->paid_amount)</td><td class="num">@inr($s->due_amount)</td><td><span class="badge-navy">{{ \App\Models\Sale::STATUSES[$s->status] ?? $s->status }}</span></td></tr>@empty<tr><td colspan="6" class="text-sm text-muted">No purchases.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
            <div class="card">
                <h2 class="section-title p-4">Payments</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Date</th><th>Transaction</th><th>Mode</th><th class="num">Amount</th></tr></thead>
                    <tbody>@forelse ($payments as $p)<tr><td>@date($p->paid_on)</td><td class="font-mono text-xs">{{ $p->transaction_no }}</td><td>{{ ucfirst($p->mode) }}</td><td class="num">@inr($p->amount)</td></tr>@empty<tr><td colspan="4" class="text-sm text-muted">No payments.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('app.customers.update', $c) }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="section-title">Update on request</h2>
                <p class="text-xs text-muted">Every change is recorded in the audit log with the reason.</p>
                <x-field name="name" label="Name" :value="$c->name" required />
                <x-field name="email" type="email" label="Email" :value="$c->email" required />
                <x-field name="mobile" label="Mobile" :value="$c->mobile" />
                <x-field name="address" label="Address" :value="$c->address" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="city" label="City" :value="$c->city" />
                    <x-field name="district" label="District" :value="$c->district" />
                    <x-field name="state" label="State" :value="$c->state" />
                    <x-field name="pin" label="PIN" :value="$c->pin" />
                </div>
                <x-field name="reason" label="Reason / request reference" required placeholder="Customer emailed on 09-10-2026" />
                <button class="btn-primary">Save changes</button>
            </form>
            <div class="card card-pad">
                <h2 class="section-title">Promoters</h2>
                <ul class="mt-2 space-y-1 text-sm">@forelse ($c->tenants as $t)<li>{{ $t->name }} <span class="text-xs text-muted">({{ $t->pivot->source }})</span></li>@empty<li class="text-muted">Not associated with any promoter yet.</li>@endforelse</ul>
            </div>
            <div class="card card-pad">
                <h2 class="section-title">Change history</h2>
                <ol class="mt-2 space-y-2 text-sm">
                    @forelse ($changes as $a)
                        <li><p class="font-semibold">{{ str_replace('_', ' ', ucfirst($a->action)) }}</p><p class="text-xs text-muted">{{ $a->actor_name ?? 'System' }} · {{ \App\Support\Format::datetime($a->created_at) }}</p>@if (! empty($a->after['reason']))<p class="text-xs">Reason: {{ $a->after['reason'] }}</p>@endif</li>
                    @empty
                        <li class="text-muted">No changes recorded.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
</x-layouts.workspace>
