<x-layouts.workspace title="Contact view charges">
    <x-page-header title="Contact view charges" subtitle="Buyers browse every listing free. To see an owner's phone number they pay this small fee — it goes to the App owner. “Request a call back” always stays free." />
    @include('app.plans.tabs')
    @php($canEdit = auth()->user()->hasPerm('plans.update'))

    @unless ($gatewayOn)
        <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <strong>No online payment gateway is set up</strong>, so contact numbers are shown free for now. Set up Razorpay or Swipe under <a href="{{ route('app.settings.index') }}" class="font-semibold underline">Settings → Payments</a> and the charge starts at once.
        </div>
    @endunless

    <form method="POST" action="{{ route('app.contact-charges.update') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf @method('PUT')
        <div class="card card-pad space-y-4 lg:col-span-2">
            <h2 class="section-title">Charges (₹, GST included)</h2>
            <input type="hidden" name="contact_charge_enabled" value="0">
            <x-checkbox name="contact_charge_enabled" label="Charge buyers to view contact numbers" :checked="\App\Services\AppSettings::get('contact.charge_enabled', '1') === '1'" hint="Applies to every tenant's listings and layouts. Set a price to 0 to make that kind free." />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="contact_charge_listing" type="number" step="1" min="0" label="Listings (default)" :value="\App\Services\AppSettings::get('contact.charge_listing', '49')" required />
                <x-field name="contact_charge_layout" type="number" step="1" min="0" label="Layouts (plot projects)" :value="\App\Services\AppSettings::get('contact.charge_layout', '49')" required />
                <x-field name="contact_validity_days" type="number" min="0" label="Unlock lasts (days)" :value="\App\Services\AppSettings::get('contact.validity_days', '30')" required hint="0 = for ever" />
            </div>
            <div>
                <p class="label">Price by listing type <span class="font-normal text-muted">(blank = use the default)</span></p>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($types as $key => $label)
                        <x-field name="contact_charge_type_{{ $key }}" type="number" step="1" min="0" :label="$label" :value="\App\Services\AppSettings::get('contact.charge_type_'.$key, '')" />
                    @endforeach
                </div>
            </div>
        </div>
        <div class="card card-pad space-y-4">
            <h2 class="section-title">Listings for every promoter</h2>
            <x-field name="listings_free" type="number" min="0" label="Free active listings" :value="\App\Services\AppSettings::get('listings.free', '5')" required hint="Every tenant keeps this many listings live, whatever the plan and even when the subscription lapses." />
            <x-field name="listings_image_max_mb" type="number" min="1" max="20" label="Largest photo (MB each)" :value="\App\Services\AppSettings::get('listings.image_max_mb', '5')" required />
            @if ($canEdit)<button class="btn-primary w-full">Save</button>@endif
        </div>
    </form>

    <h2 class="mb-3 mt-8 text-xl font-extrabold">Contact view payments</h2>
    <x-filters>
        <x-field name="from" type="date" label="From" :value="$from->toDateString()" />
        <x-field name="to" type="date" label="To" :value="$to->toDateString()" />
        <x-select name="status" label="Status" :options="['paid' => 'Paid', 'due' => 'Not paid']" :value="request('status')" placeholder="Any" />
    </x-filters>
    <div class="mb-4 grid gap-4 sm:grid-cols-4">
        <x-stat label="Paid views" :value="\App\Support\Format::num($totals['count'])" />
        <x-stat label="Revenue (excl. GST)" :value="\App\Support\Format::inr($totals['amount'], 2)" />
        <x-stat label="GST" :value="\App\Support\Format::inr($totals['tax'], 2)" />
        <x-stat label="Collected" :value="\App\Support\Format::inr($totals['total'], 2)" />
    </div>
    <div class="card">
        <div class="flex justify-end border-b border-navy-50 p-3"><a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-light btn-sm"><x-icon name="download" class="h-4 w-4" /> Excel</a></div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Receipt</th><th>Date</th><th>Buyer</th><th>Property</th><th>Owner</th><th class="num">Total</th><th>Status</th><th>Valid until</th></tr></thead>
            <tbody>
            @forelse ($unlocks as $u)
                <tr>
                    <td class="font-mono text-sm">{{ $u->number }}</td>
                    <td class="text-sm">{{ \App\Support\Format::datetime($u->created_at) }}</td>
                    <td>{{ $u->customer?->name }}<span class="block text-xs text-muted">{{ $u->customer?->mobile }}</span></td>
                    <td class="max-w-[16rem] truncate">{{ $u->title }}</td>
                    <td class="text-sm">{{ $u->tenantRel?->name }}</td>
                    <td class="num">@inr($u->total)</td>
                    <td><span class="{{ $u->status === 'paid' ? 'badge-teal' : 'badge-amber' }}">{{ $u->status === 'paid' ? 'Paid' : 'Not paid' }}</span> @if ($u->method)<span class="text-xs text-muted">{{ $u->method }}</span>@endif</td>
                    <td class="text-sm">{{ $u->status === 'paid' ? ($u->expires_at ? \App\Support\Format::date($u->expires_at) : 'No end') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">No contact views in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-4">{{ $unlocks->links() }}</div>
    </div>
</x-layouts.workspace>
