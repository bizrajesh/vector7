<x-layouts.workspace title="New booking">
    <x-page-header title="New booking" subtitle="Pick a plot and a customer. New customers get an email to set their password." :back="route('ws.bookings.index')" />
    <form method="POST" action="{{ route('ws.bookings.store') }}" class="grid gap-6 lg:grid-cols-2">
        @csrf
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Plot</h2>
            <x-select name="project" label="Project" :options="$projects" :value="$plot?->project_id" placeholder="Choose a project" data-plot-picker="f_plot_id" data-url="{{ route('ws.api.plots') }}" data-statuses="available" data-info="plot-info" />
            <div>
                <label for="f_plot_id" class="label">Available plot <span class="text-red-700">*</span></label>
                <select id="f_plot_id" name="plot_id" class="input" required data-current="{{ old('plot_id', $plot?->id) }}"><option value="">Choose a project first</option></select>
                <p id="plot-info" class="hint" aria-live="polite"></p>
            </div>
            <x-field name="promo_code" label="Promo code (optional)" />
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Customer</h2>
            <div class="relative">
                <label for="cust_search" class="label">Existing customer</label>
                <input id="cust_search" class="input" placeholder="Type name, mobile or email" autocomplete="off" data-lookup="{{ route('ws.api.customers') }}" data-lookup-list="cust_list" data-lookup-target="customer_id">
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
                <ul id="cust_list" class="absolute z-20 mt-1 hidden w-full overflow-hidden rounded-lg bg-white shadow-lift ring-1 ring-navy-50"></ul>
                <p class="hint">Only customers who already deal with your workspace are listed.</p>
            </div>
            <p class="text-sm font-semibold text-muted">— or register a new customer —</p>
            <x-field name="new_name" label="Full name" />
            <x-field name="new_email" type="email" label="Email" hint="If this email already has a vector7 account, the booking is added to it." />
            <x-field name="new_mobile" label="Mobile" inputmode="numeric" maxlength="10" />
        </div>
        <div class="card card-pad space-y-3 lg:col-span-2">
            <h2 class="section-title">Booking disclaimer</h2>
            <p class="whitespace-pre-line rounded-xl bg-page p-4 text-sm">{{ $disclaimer }}</p>
            <x-checkbox name="disclaimer" label="The customer has read and accepted the booking disclaimer" />
            <button class="btn-primary">Create booking</button>
        </div>
    </form>
</x-layouts.workspace>
