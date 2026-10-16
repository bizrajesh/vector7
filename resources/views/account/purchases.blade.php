<x-layouts.account :title="__('Purchases')">
    <div class="space-y-4">
        @forelse ($sales as $s)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><p class="text-lg font-extrabold">{{ __('Plot :no', ['no' => $s->plot->plot_no]) }} · {{ $s->project->name }}</p><p class="text-sm text-muted">{{ $s->sale_no }} · {{ __('started :date', ['date' => \App\Support\Format::date($s->started_on)]) }} · {{ __('complete by :date', ['date' => \App\Support\Format::date($s->window_end)]) }}</p></div>
                    <x-status :status="$s->plot->status" />
                </div>
                <div class="mt-3 flex items-center gap-2"><x-progress :pct="$s->net_price > 0 ? $s->paid_amount / $s->net_price * 100 : 0" level="teal" class="flex-1" :label="__('Paid')" /><span class="text-sm font-semibold">{{ __(':paid of :total', ['paid' => \App\Support\Format::inr($s->paid_amount), 'total' => \App\Support\Format::inr($s->net_price)]) }}</span></div>
                <div class="table-wrap mt-3"><table class="tbl">
                    <thead><tr><th>{{ __('Instalment') }}</th><th>{{ __('Due date') }}</th><th class="num">{{ __('Amount') }}</th><th class="num">{{ __('Paid') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>@foreach ($s->instalments as $i)<tr><td>{{ $i->name }} ({{ (float) $i->percent }}%)</td><td>@date($i->due_date)</td><td class="num">@inr($i->amount)</td><td class="num">@inr($i->paid_amount)</td>
                        <td><span class="{{ $i->status === 'paid' ? 'badge-teal' : ($i->due_date->isPast() ? 'badge-red' : 'badge-amber') }}">{{ $i->status === 'paid' ? __('Paid') : ($i->due_date->isPast() ? __('Overdue') : __(ucfirst($i->status))) }}</span></td></tr>@endforeach</tbody>
                </table></div>
                @if ($s->registration)<p class="mt-3 text-sm">{{ __('Registration:') }} <strong>{{ __(\App\Models\Registration::STATUSES[$s->registration->status]) }}</strong>@if ($s->registration->registration_date) · {{ __('date :date', ['date' => \App\Support\Format::date($s->registration->registration_date)]) }}@endif</p>@endif
            </div>
        @empty
            <div class="card"><x-empty :title="__('No purchases yet')" icon="cart" /></div>
        @endforelse
    </div>
</x-layouts.account>
