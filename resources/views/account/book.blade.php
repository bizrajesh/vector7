<x-layouts.account title="Book a plot">
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card card-pad lg:col-span-3">
            <p class="text-sm text-muted">{{ $plot->project->name }} · {{ $plot->project->location }} · by {{ $tenant->name }}</p>
            <h2 class="mt-1 text-3xl font-extrabold">Plot {{ $plot->plot_no }}</h2>
            <p class="mt-1 text-muted">{{ \App\Support\Format::num($plot->size_sqft) }} sq ft · {{ $plot->facing }} facing · Patta {{ $plot->patta_number }}</p>
            <div class="mt-5">@include('partials.plot-price', ['plot' => $plot, 'size' => 'lg'])</div>
            @if ($plot->status !== 'available')
                <div class="flash-err mt-6">This plot is {{ strtolower($plot->statusLabel()) }} and cannot be booked. <a href="{{ $plot->project->publicUrl() }}">See other plots</a>.</div>
            @else
                <form method="POST" action="{{ route('account.book.store', $plot) }}" class="mt-6 space-y-4">
                    @csrf
                    <x-field name="promo_code" label="Promo code (optional)" class="max-w-xs" />
                    <div class="rounded-xl bg-page p-4 text-sm">
                        <p class="font-bold">Booking terms</p>
                        <p class="mt-1 whitespace-pre-line text-muted">{{ $settings->disclaimer_booking }}</p>
                    </div>
                    <x-checkbox name="disclaimer" label="I have read and accept the booking terms" required />
                    <button class="btn-primary btn-pill px-8 py-3">Confirm booking</button>
                </form>
            @endif
        </div>
        <div class="card card-pad lg:col-span-2">
            <h2 class="font-extrabold">What happens next</h2>
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm">
                <li>The plot is held for you until <strong>{{ $validTill->format('d-m-Y') }}</strong> ({{ $settings->booking_validity_days }} working days).</li>
                <li>Pay the first instalment of {{ (float) ($plan->first()?->percent ?? 30) }}% to {{ $tenant->name }} before then — by bank transfer, UPI or cheque.</li>
                <li>The remaining instalments are due within {{ $settings->sale_window_days }} working days of the sale.</li>
            </ol>
            <h3 class="mt-5 text-sm font-bold">Instalment plan</h3>
            <ul class="mt-2 space-y-1 text-sm">@foreach ($plan as $i)<li class="flex justify-between"><span>{{ $i->name }}</span><span class="font-semibold">{{ (float) $i->percent }}% · day {{ $i->due_working_days }}</span></li>@endforeach</ul>
        </div>
    </div>
</x-layouts.account>
