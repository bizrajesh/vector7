<x-layouts.account :title="__('My account')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Active bookings')" :value="$bookings->count()" icon="calendar" />
        <x-stat :label="__('Purchases')" :value="$sales->count()" icon="cart" tone="navy" />
        <x-stat :label="__('Balance due')" :value="\App\Support\Format::inr($sales->where('status', 'sale_init')->sum('due_amount'))" icon="rupee" tone="gold" />
    </div>
    @foreach ($bookings as $b)
        <div class="card card-pad mt-6 ring-2 ring-amber-300">
            <p class="font-bold">{{ __('Booking :no', ['no' => $b->booking_no]) }} — {{ __('Plot :no', ['no' => $b->plot->plot_no]) }}, {{ $b->project->name }}</p>
            <p class="mt-1 text-sm">{!! __('Pay the first instalment of :amount by :date to keep this plot.', ['amount' => '<strong>'.e(\App\Support\Format::inr($b->firstInstalmentAmount())).'</strong>', 'date' => '<strong>'.e(\App\Support\Format::date($b->valid_till)).'</strong>']) !!}</p>
        </div>
    @endforeach
    <div class="card mt-6">
        <h2 class="section-title p-4">{{ __('My plots') }}</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Plot') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Paid') }}</th><th class="num">{{ __('Due') }}</th><th>{{ __('Next due') }}</th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                @php($next = $s->nextDue())
                <tr><td><p class="font-semibold">{{ __('Plot :no', ['no' => $s->plot->plot_no]) }}</p><p class="text-xs text-muted">{{ $s->project->name }}</p></td><td><x-status :status="$s->plot->status" /></td>
                    <td class="num">@inr($s->net_price)</td><td class="num">@inr($s->paid_amount)</td><td class="num">@inr($s->due_amount)</td>
                    <td class="text-sm">{{ $next ? __(':amount by :date', ['amount' => \App\Support\Format::inr($next->balance()), 'date' => \App\Support\Format::date($next->due_date)]) : '—' }}</td></tr>
            @empty
                <tr><td colspan="6"><x-empty :title="__('No purchases yet')" icon="cart"><a href="{{ route('market.projects') }}">{{ __('Browse plots') }}</a></x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($recentPayments->isNotEmpty())
        <div class="card mt-6"><h2 class="section-title p-4">{{ __('Recent payments') }}</h2>
            <ul class="divide-y divide-navy-50">@foreach ($recentPayments as $p)<li class="flex items-center justify-between gap-3 p-4 text-sm"><span>@date($p->paid_on) · {{ __('Plot :no', ['no' => $p->plot->plot_no]) }} · {{ __(\App\Models\Payment::MODES[$p->mode]) }}</span><span class="font-bold">@inr($p->amount)</span><a href="{{ route('account.receipt', $p->id) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">{{ __('Receipt') }}</a></li>@endforeach</ul>
        </div>
    @endif
</x-layouts.account>
