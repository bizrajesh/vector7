<x-layouts.app title="Launch & Sales">
    <x-page-header title="Launch & Sales" subtitle="Sales, collections and registrations" />
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        @foreach (['' => 'All', 'ongoing' => 'Ongoing', 'overdue' => 'Overdue', 'paid' => 'Ready for registration', 'registered' => 'Registered', 'cancelled' => 'Cancelled'] as $key => $label)
            <a href="{{ route('app.sales.index', array_filter(['status' => $key])) }}" class="pill {{ (string) request('status') === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="card">
        @forelse ($sales as $sale)
            <a href="{{ route('app.sales.show', $sale) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $sale->customer->name }}</span><span class="text-xs text-ink-muted">{{ $sale->plot->layout->name }} · {{ $sale->plot->plot_no }} · {{ $sale->sale_date->format('j M Y') }}</span></span>
                <span class="num text-sm">@inr($sale->paid_amount) / @inr($sale->sale_value)</span>
                @if ($sale->isOverdue())<span class="badge-od">Overdue</span>@endif
                <x-status :status="$sale->plot->status" />
            </a>
        @empty
            <x-empty title="No sales yet" icon="tag">Open a launched layout's plot catalogue to book or sell a plot.</x-empty>
        @endforelse
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</x-layouts.app>
