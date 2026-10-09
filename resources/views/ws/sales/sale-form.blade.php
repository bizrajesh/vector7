<x-layouts.workspace title="Start a sale">
    <x-page-header title="Start a sale" :subtitle="'The initial payment must be at least the 1st instalment ('.(float) ($plan->first()?->percent ?? 30).'%). The plot then becomes Sale Init.'" :back="route('ws.sales.index')" />
    <form method="POST" action="{{ route('ws.sales.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2">
        @csrf
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Plot & customer</h2>
            @if ($booking)
                <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                <div class="rounded-xl bg-page p-4 text-sm">
                    <p class="font-bold">Booking {{ $booking->booking_no }} — Plot {{ $booking->plot->plot_no }}, {{ $booking->plot->project->name }}</p>
                    <p class="mt-1">Customer: <strong>{{ $booking->customer->name }}</strong> (only this customer can buy this plot)</p>
                    <p class="mt-1">Net price: <strong>@inr($booking->net_price)</strong> @if ($booking->offer_price)(offer price locked on the booking date)@endif · 1st instalment: <strong>@inr($booking->firstInstalmentAmount())</strong></p>
                </div>
            @else
                <x-select name="project" label="Project" :options="$projects" placeholder="Choose a project" data-plot-picker="f_plot_id" data-url="{{ route('ws.api.plots') }}" data-statuses="available" data-info="plot-info" />
                <div><label for="f_plot_id" class="label">Available plot <span class="text-red-700">*</span></label>
                    <select id="f_plot_id" name="plot_id" class="input" required data-current="{{ old('plot_id') }}"><option value="">Choose a project first</option></select><p id="plot-info" class="hint" aria-live="polite"></p></div>
                <div class="relative">
                    <label for="cust_search" class="label">Customer <span class="text-red-700">*</span></label>
                    <input id="cust_search" class="input" placeholder="Type name, mobile or email" autocomplete="off" data-lookup="{{ route('ws.api.customers') }}" data-lookup-list="cust_list" data-lookup-target="customer_id">
                    <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
                    <ul id="cust_list" class="absolute z-20 mt-1 hidden w-full overflow-hidden rounded-lg bg-white shadow-lift ring-1 ring-navy-50"></ul>
                    <p class="hint">New customer? Create a booking for them first.</p>
                </div>
                <x-field name="promo_code" label="Promo code (optional)" />
            @endif
            <x-select name="broker_id" label="Broker (optional)" :options="$brokers" placeholder="No broker" :hint="'Commission '.(float) $settings->broker_commission_pct.'% of sale value unless the broker has their own rate'" />
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Initial payment</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="amount" type="number" step="0.01" label="Amount (₹)" required :value="$booking?->firstInstalmentAmount()" />
                <x-field name="paid_on" type="date" label="Paid on" required :value="today()->toDateString()" />
                <x-select name="mode" label="Mode" :options="\App\Models\Payment::MODES" required />
                <x-field name="reference_no" label="Reference no." placeholder="UTR / cheque no." />
            </div>
            <x-field name="proof" type="file" label="Proof (optional)" accept=".pdf,.jpg,.jpeg,.png,.webp" />
            <x-field name="notes" label="Notes" />
            <div class="rounded-xl bg-page p-3 text-xs">
                <p class="font-bold">Instalment plan</p>
                @foreach ($plan as $i)<p>{{ $i->name }}: {{ (float) $i->percent }}% due {{ $i->due_working_days }} working days after the sale</p>@endforeach
                <p class="mt-1">Sale completion window: {{ $settings->sale_window_days }} working days.</p>
            </div>
        </div>
        <div class="card card-pad space-y-3 lg:col-span-2">
            <h2 class="section-title">Sale disclaimer</h2>
            <p class="whitespace-pre-line rounded-xl bg-page p-4 text-sm">{{ $settings->disclaimer_sale }}</p>
            <x-checkbox name="disclaimer" label="The customer has read and accepted the sale disclaimer" />
            <button class="btn-primary">Start sale & record payment</button>
        </div>
    </form>
</x-layouts.workspace>
