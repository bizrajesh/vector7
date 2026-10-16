<x-layouts.account :title="__('Book a plot')">
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card card-pad lg:col-span-3">
            <p class="text-sm text-muted">{{ $plot->project->name }} · {{ $plot->project->location }} · {{ __('by :name', ['name' => $tenant->name]) }}</p>
            <h2 class="mt-1 text-3xl font-extrabold">{{ __('Plot :no', ['no' => $plot->plot_no]) }}</h2>
            <p class="mt-1 text-muted">{{ \App\Support\Format::num($plot->size_sqft) }} {{ __('sq ft') }} · {{ __(':facing facing', ['facing' => __($plot->facing)]) }} · {{ __('Patta :no', ['no' => $plot->patta_number]) }}</p>
            <div class="mt-5">@include('partials.plot-price', ['plot' => $plot, 'size' => 'lg'])</div>
            @if ($plot->status !== 'available')
                <div class="flash-err mt-6">{{ __('This plot is :status and cannot be booked.', ['status' => __($plot->statusLabel())]) }} <a href="{{ $plot->project->publicUrl() }}">{{ __('See other plots') }}</a>.</div>
            @else
                <form method="POST" action="{{ route('account.book.store', $plot) }}" class="mt-6 space-y-4">
                    @csrf
                    <x-field name="promo_code" :label="__('Promo code (optional)')" class="max-w-xs" />
                    <div class="rounded-xl bg-page p-4 text-sm">
                        <p class="font-bold">{{ __('Booking terms') }}</p>
                        <p class="mt-1 whitespace-pre-line text-muted">{{ $settings->disclaimer_booking }}</p>
                    </div>
                    <x-checkbox name="disclaimer" :label="__('I have read and accept the booking terms')" required />
                    <button class="btn-primary btn-pill px-8 py-3">{{ __('Confirm booking') }}</button>
                </form>
            @endif
        </div>
        <div class="card card-pad lg:col-span-2">
            <h2 class="font-extrabold">{{ __('What happens next') }}</h2>
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm">
                <li>{!! __('The plot is held for you until :date (:n working days).', ['date' => '<strong>'.e($validTill->format('d-m-Y')).'</strong>', 'n' => (int) $settings->booking_validity_days]) !!}</li>
                <li>{{ __('Pay the first instalment of :pct% to :name before then — by bank transfer, UPI or cheque.', ['pct' => (float) ($plan->first()?->percent ?? 30), 'name' => $tenant->name]) }}</li>
                <li>{{ __('The remaining instalments are due within :n working days of the sale.', ['n' => $settings->sale_window_days]) }}</li>
            </ol>
            <h3 class="mt-5 text-sm font-bold">{{ __('Instalment plan') }}</h3>
            <ul class="mt-2 space-y-1 text-sm">@foreach ($plan as $i)<li class="flex justify-between"><span>{{ $i->name }}</span><span class="font-semibold">{{ (float) $i->percent }}% · {{ __('day :n', ['n' => $i->due_working_days]) }}</span></li>@endforeach</ul>
        </div>
    </div>
</x-layouts.account>
