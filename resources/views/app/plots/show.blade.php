@php
    $user = auth()->user();
    $launched = $plot->layout->status->value === 'launched';
    $sale = $plot->activeSale;
    $booking = $plot->activeBooking;
@endphp
<x-layouts.app :title="'Plot '.$plot->plot_no">
    <x-page-header :title="'Plot '.$plot->plot_no" :subtitle="$plot->layout->name.' · Survey No. '.($plot->survey_no ?? '—')" :back="route('app.plots.index', $plot->layout)">
        <x-slot:actions><x-status :status="$plot->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card-pad lg:col-span-2">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-ground p-3"><p class="text-xs text-ink-muted">Size</p><p class="num font-bold">{{ number_format((float) $plot->size_sqft) }} ft²</p></div>
                <div class="rounded-xl bg-ground p-3"><p class="text-xs text-ink-muted">Facing</p><p class="font-bold">{{ $plot->facing ? ucwords(str_replace('_', ' ', $plot->facing)) : '—' }}</p></div>
                <div class="rounded-xl bg-ground p-3"><p class="text-xs text-ink-muted">Rate</p><p class="num font-bold">@inr($plot->rate_sqft)/ft²</p></div>
                <div class="rounded-xl bg-teal-50 p-3"><p class="text-xs text-teal">Plot cost</p><p class="num font-extrabold text-navy">@inr($plot->cost)</p></div>
            </div>
            <h2 class="mt-5 text-sm font-bold">Boundaries {{ $plot->dimensions ? '· '.$plot->dimensions.' ft' : '' }}</h2>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                @foreach (['north' => 'North', 'south' => 'South', 'east' => 'East', 'west' => 'West'] as $k => $label)
                    <div><dt class="inline font-semibold">{{ $label }}:</dt> <dd class="inline text-ink-2">{{ $plot->{'boundary_'.$k} ?? '—' }}</dd></div>
                @endforeach
            </dl>

            <div class="mt-5 flex flex-wrap gap-2">
                @if ($launched && $plot->status->value === 'available' && $user->can('bookings.create'))
                    <a href="{{ route('app.bookings.create', $plot) }}" class="btn-outline">Book · 15 days</a>
                @endif
                @if ($launched && in_array($plot->status->value, ['available', 'booked']) && $user->can('sales.create'))
                    <a href="{{ route('app.sales.create', $plot) }}" class="btn-primary">Buy now</a>
                @endif
                @if ($plot->status->value === 'ror' && $user->can('registrations.manage'))
                    <details class="w-full rounded-xl border border-line p-3">
                        <summary class="cursor-pointer font-semibold text-teal">Initiate registration</summary>
                        <form method="POST" action="{{ route('app.registrations.store', $plot) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
                            <x-select name="document_writer_id" label="Document writer" :options="\App\Models\DocumentWriter::query()->where('is_active', true)->pluck('name', 'id')" placeholder="— enter a name instead —" />
                            <x-field name="document_writer_name" label="Or writer name" />
                            <x-select name="sub_registrar_office_id" label="Sub-Registrar office" :options="\App\Models\SubRegistrarOffice::query()->pluck('name', 'id')" placeholder="Choose office" />
                            <x-field name="planned_date" type="date" label="Planned registration date" />
                            <div class="sm:col-span-2"><button class="btn-primary">Start registration</button></div>
                        </form>
                    </details>
                @endif
                @if ($sale?->registration)
                    <a href="{{ route('app.registrations.show', $sale->registration) }}" class="btn-ghost">Registration</a>
                @endif
                @if (in_array($plot->status->value, ['available', 'reserved']) && $user->can('plots.manage'))
                    <form method="POST" action="{{ route('app.plots.reserve', $plot) }}" class="flex gap-2">@csrf
                        <input name="reason" class="input min-h-[44px] w-48" placeholder="Reason" required aria-label="Reason">
                        <button class="btn-ghost">{{ $plot->status->value === 'reserved' ? 'Release to available' : 'Reserve (hold)' }}</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="flex flex-col gap-4">
            @if ($booking)
                <div class="card-pad">
                    <h2 class="section-title">Booking</h2>
                    <p class="mt-2 text-sm"><a href="{{ route('app.customers.show', $booking->customer) }}" class="font-semibold">{{ $booking->customer->name }}</a> · @inr($booking->amount)</p>
                    <p class="text-sm text-ink-muted" title="Expires {{ $booking->expires_at->format('j M Y H:i') }}">
                        @if ($booking->isPending())<span class="badge-bk">Website hold</span> {{ $booking->customer->phone }} · expires {{ $booking->expires_at->format('j M, h:i A') }} ({{ $booking->hoursLeft() }} h left)
                        @else Expires {{ $booking->expires_at->format('j M Y') }} ({{ $booking->daysLeft() }} days left) @endif
                    </p>
                    @if ($booking->isPending() && $user->can('payments.record'))
                        <form method="POST" action="{{ route('app.bookings.confirm', $booking) }}" class="mt-3 flex flex-col gap-3 rounded-xl bg-cream-100 p-3" data-confirm="Record the advance and confirm this booking?">@csrf
                            <p class="text-sm font-bold">Confirm with booking advance</p>
                            @include('app._payment-fields')
                            <button class="btn-primary btn-sm">Confirm booking</button>
                        </form>
                    @endif
                    @can('bookings.create')
                        <form method="POST" action="{{ route('app.bookings.cancel', $booking) }}" class="mt-3 flex gap-2" data-confirm="Cancel this booking and release the plot?">@csrf
                            <input name="reason" class="input min-h-[40px]" placeholder="Reason" required aria-label="Cancellation reason"><button class="btn-danger btn-sm">Cancel</button>
                        </form>
                    @endcan
                </div>
            @endif
            @if ($sale)
                <div class="card-pad">
                    <h2 class="section-title">Sale</h2>
                    <p class="mt-2 text-sm"><a href="{{ route('app.customers.show', $sale->customer) }}" class="font-semibold">{{ $sale->customer->name }}</a></p>
                    <p class="num text-sm">Paid @inr($sale->paid_amount) of @inr($sale->sale_value)</p>
                    <div class="progress mt-2"><span style="width: {{ $sale->sale_value > 0 ? min(100, round($sale->paid_amount / $sale->sale_value * 100)) : 0 }}%"></span></div>
                    <a href="{{ route('app.sales.show', $sale) }}" class="btn-ghost btn-sm mt-3 w-full">Open sale</a>
                </div>
            @endif
            <div class="card-pad">
                <h2 class="section-title mb-2">History</h2>
                @forelse ($plot->history as $h)
                    <div class="border-t border-line-soft py-2 text-sm first:border-t-0">
                        <p><span class="font-semibold">{{ \App\Enums\PlotStatus::from($h->to_status)->label() }}</span> <span class="text-ink-muted">· {{ $h->created_at->format('j M Y H:i') }}</span></p>
                        <p class="text-xs text-ink-muted">{{ $h->reason }} {{ $h->user ? '· '.$h->user->name : '· system' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">No changes yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
