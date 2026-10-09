<x-layouts.workspace :title="'Booking '.$booking->booking_no">
    <x-page-header :title="'Booking '.$booking->booking_no" :subtitle="'Plot '.$booking->plot->plot_no.' · '.$booking->project->name" :back="route('ws.bookings.index')">
        @if ($booking->status === 'active')
            @can('sales.create')<a href="{{ route('ws.sales.create', ['booking' => $booking->id]) }}" class="btn-teal">Start sale (1st instalment)</a>@endcan
            @can('bookings.delete')<x-confirm :action="route('ws.bookings.cancel', $booking)" message="Cancel this booking and release the plot?" class="btn-light">Cancel booking</x-confirm>@endcan
        @elseif ($booking->sale)
            <a href="{{ route('ws.sales.show', $booking->sale) }}" class="btn-primary">Open sale</a>
        @endif
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-muted">Status</dt><dd class="font-bold">{{ \App\Models\Booking::STATUSES[$booking->status] }}</dd></div>
                <div><dt class="text-muted">Valid till</dt><dd class="font-bold">@date($booking->valid_till)</dd></div>
                <div><dt class="text-muted">Actual price</dt><dd>@inr($booking->actual_price)</dd></div>
                <div><dt class="text-muted">Offer price on booking date</dt><dd>{{ $booking->offer_price ? \App\Support\Format::inr($booking->offer_price).' ('.$booking->offer_text.')' : '—' }}</dd></div>
                <div><dt class="text-muted">Promo code</dt><dd>{{ $booking->promoCode?->code ?? '—' }} @if ($booking->discount_amount > 0)(−@inr($booking->discount_amount))@endif</dd></div>
                <div><dt class="text-muted">Net price</dt><dd class="text-lg font-extrabold">@inr($booking->net_price)</dd></div>
                <div><dt class="text-muted">1st instalment needed</dt><dd class="font-bold">@inr($booking->firstInstalmentAmount())</dd></div>
                <div><dt class="text-muted">Disclaimer accepted</dt><dd>{{ \App\Support\Format::datetime($booking->disclaimer_accepted_at) }} · {{ $booking->disclaimer_ip }}</dd></div>
            </dl>
        </div>
        <div class="card card-pad">
            <h2 class="section-title">Customer</h2>
            <p class="mt-2 font-bold">{{ $booking->customer->name }}</p>
            <p class="text-sm">{{ $booking->customer->mobile }} · {{ $booking->customer->email }}</p>
            @can('tenant_customers.view')<a href="{{ route('ws.customers.show', $booking->customer) }}" class="btn-light btn-sm mt-3">Customer history</a>@endcan
        </div>
    </div>
</x-layouts.workspace>
