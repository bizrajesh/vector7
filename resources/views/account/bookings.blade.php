<x-layouts.account title="Bookings">
    <div class="space-y-4">
        @forelse ($bookings as $b)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><p class="text-lg font-extrabold">Plot {{ $b->plot->plot_no }} · {{ $b->project->name }}</p><p class="text-sm text-muted">{{ $b->booking_no }} · booked @date($b->booked_on) · {{ $b->project->tenantRel->name }}</p></div>
                    <span class="{{ ['active' => 'badge-amber', 'converted' => 'badge-teal', 'expired' => 'badge-gray', 'cancelled' => 'badge-gray'][$b->status] }}">{{ \App\Models\Booking::STATUSES[$b->status] }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-muted">Price</dt><dd class="font-bold">@inr($b->net_price)</dd></div>
                    @if ($b->offer_price)<div><dt class="text-muted">Offer applied</dt><dd>@inr($b->actual_price) → @inr($b->offer_price)</dd></div>@endif
                    @if ($b->discount_amount > 0)<div><dt class="text-muted">Promo discount</dt><dd>@inr($b->discount_amount)</dd></div>@endif
                    <div><dt class="text-muted">Valid till</dt><dd class="font-bold">@date($b->valid_till)</dd></div>
                </dl>
                @if ($b->status === 'active')<p class="mt-3 text-sm">First instalment due: <strong>@inr($b->firstInstalmentAmount())</strong>. Pay {{ $b->project->tenantRel->name }} before @date($b->valid_till).</p>@endif
            </div>
        @empty
            <div class="card"><x-empty title="No bookings yet" icon="calendar"><a href="{{ route('market.projects') }}">Find a plot</a></x-empty></div>
        @endforelse
    </div>
</x-layouts.account>
