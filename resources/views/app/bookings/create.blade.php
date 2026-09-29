<x-layouts.app title="New booking">
    <x-page-header title="New booking" subtitle="Hold a plot for the customer" :back="route('app.plots.show', $plot)" />
    <form method="POST" action="{{ route('app.bookings.store', $plot) }}" class="mx-auto flex max-w-xl flex-col gap-4 pb-24 lg:pb-0">
        @csrf
        <div class="card flex items-center gap-3 px-4 py-3">
            <span class="tile-av h-11 w-11 shrink-0"><strong>{{ $plot->plot_no }}</strong></span>
            <span class="flex-1"><span class="block font-bold">{{ $plot->layout->name }} · {{ $plot->plot_no }}</span><span class="num text-[12.5px] text-ink-muted">{{ number_format((float) $plot->size_sqft) }} sqft · {{ $plot->facing ? ucwords(str_replace('_', ' ', $plot->facing)) : '' }} · @inr($plot->cost)</span></span>
            <a href="{{ route('app.plots.index', $plot->layout) }}" class="text-[13px] font-semibold">Change</a>
        </div>
        <div class="card-pad flex flex-col gap-4">
            @include('app._customer-picker')
        </div>
        <div class="card-pad flex flex-col gap-4">
            <h2 class="section-title">Booking payment</h2>
            @include('app._payment-fields')
            <div class="flex items-start gap-2.5 rounded-xl bg-[#FBEFD5] px-3.5 py-3 text-[13px] text-[#5E3D00]">
                <x-icon name="clock" class="mt-0.5 h-[18px] w-[18px] shrink-0" stroke="2" />
                <span>Booking valid till <strong>{{ $expires->format('j M Y') }}</strong>. If not converted to a sale by then, the plot returns to Available.</span>
            </div>
        </div>
        <div class="fixed inset-x-0 bottom-[72px] z-10 flex items-center gap-3 border-t border-line bg-white px-4 py-3 lg:static lg:rounded-2xl lg:border">
            <span class="text-sm text-ink-muted">Receipt is issued automatically</span>
            <button type="submit" class="btn-primary ml-auto h-[52px] px-6">Confirm booking</button>
        </div>
    </form>
</x-layouts.app>
