<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->phone.($customer->email ? ' · '.$customer->email : '')" :back="route('app.customers.index')" />
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card-pad text-sm">
            <h2 class="section-title mb-2">Details</h2>
            <p><span class="text-ink-muted">Aadhaar:</span> {{ $customer->maskedAadhaar() }}</p>
            <p><span class="text-ink-muted">PAN:</span> {{ $customer->maskedPan() }}</p>
            <p><span class="text-ink-muted">Address:</span> {{ $customer->address ?? '—' }}</p>
        </section>
        <section class="card-pad lg:col-span-2">
            <h2 class="section-title mb-2">Plots</h2>
            @forelse ($customer->sales as $sale)
                <a href="{{ route('app.sales.show', $sale) }}" class="flex items-center gap-3 border-t border-line-soft py-3 text-ink no-underline first:border-t-0 hover:text-ink">
                    <span class="flex-1 font-semibold">Plot {{ $sale->plot->plot_no }}</span>
                    <span class="num text-sm">@inr($sale->payments->sum('amount')) / @inr($sale->sale_value)</span>
                    <x-status :status="$sale->plot->status" />
                </a>
            @empty
                <p class="text-sm text-ink-muted">No sales yet.</p>
            @endforelse
            @foreach ($customer->bookings->where('status', 'active') as $b)
                <p class="mt-2 text-sm"><span class="badge-bk">Booked</span> Plot {{ $b->plot->plot_no }} until {{ $b->expires_at->format('j M Y') }}</p>
            @endforeach
        </section>
    </div>
</x-layouts.app>
