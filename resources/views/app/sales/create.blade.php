@php $booking = $plot->activeBooking; @endphp
<x-layouts.app title="Sell plot">
    <x-page-header :title="'Sell plot '.$plot->plot_no" :subtitle="$plot->layout->name.' · '.\App\Support\Money::inr($plot->cost)" :back="route('app.plots.show', $plot)" />
    <form method="POST" action="{{ route('app.sales.store', $plot) }}" class="mx-auto flex max-w-xl flex-col gap-4 pb-24 lg:pb-0">
        @csrf
        @if ($booking)
            <div class="card-pad">
                <p class="text-sm">Booked by <strong>{{ $booking->customer->name }}</strong> on {{ $booking->booked_at->format('j M') }} · advance @inr($booking->amount) is adjusted against the first instalment.</p>
                <p class="help">Leave the customer fields empty to sell to the booking customer. Selling to someone else needs an Admin and a reason.</p>
            </div>
        @endif
        <div class="card-pad flex flex-col gap-4">
            @include('app._customer-picker')
            @if ($booking && auth()->user()->hasRole('admin'))
                <x-field name="override_reason" label="Reason (only if buyer differs from the booking customer)" />
            @endif
        </div>
        <div class="card-pad flex flex-col gap-4">
            <h2 class="section-title">Instalment plan</h2>
            <p class="text-sm text-ink-2">30% · 60% · 10% within 15 working days (from Settings). Any balance keeps the plot in <span class="badge-os">Ongoing-Sale</span>; 100% paid moves it to <span class="badge-ror">ROR</span>.</p>
            <x-select name="broker_id" label="Broker (optional)" :options="$brokers->pluck('name', 'id')" placeholder="No broker" />
        </div>
        <div class="card-pad flex flex-col gap-4">
            <h2 class="section-title">First payment (optional)</h2>
            @include('app._payment-fields', ['amountLabel' => 'Amount received now (₹)', 'required' => false])
        </div>
        <div class="fixed inset-x-0 bottom-[72px] z-10 flex items-center gap-3 border-t border-line bg-white px-4 py-3 lg:static lg:rounded-2xl lg:border">
            <span class="num text-sm font-bold">@inr($plot->cost)</span>
            <button type="submit" class="btn-primary ml-auto h-[52px] px-6">Create sale</button>
        </div>
    </form>
</x-layouts.app>
