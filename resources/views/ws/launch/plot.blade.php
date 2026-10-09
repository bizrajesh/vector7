<x-layouts.workspace :title="'Plot '.$plot->plot_no">
    <x-page-header :title="'Plot '.$plot->plot_no" :subtitle="$project->name.' · '.$plot->statusLabel()" :back="route('ws.launch.show', $project)" />
    @php($locked = ! in_array($plot->status, ['available', 'blocked']))
    @if ($locked)<div class="flash-warn mb-4">This plot is {{ $plot->statusLabel() }}. You can update details and prices, but not its status. Existing bookings and sales keep the price stored on them.</div>@endif
    <form method="POST" action="{{ route('ws.plots.update', [$project, $plot]) }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf @method('PUT')
        <x-field name="plot_no" label="Plot no." :value="$plot->plot_no" required />
        <x-field name="patta_number" label="Patta number" :value="$plot->patta_number" required />
        <x-field name="size_sqft" id="size_sqft" type="number" step="0.01" label="Size (sq ft)" :value="(float) $plot->size_sqft" required />
        <x-select name="facing" label="Facing" :options="array_combine(\App\Models\Plot::FACINGS, \App\Models\Plot::FACINGS)" :value="$plot->facing" required />
        <x-field name="length_ft" type="number" step="0.01" label="Length (ft)" :value="$plot->length_ft ? (float) $plot->length_ft : ''" />
        <x-field name="width_ft" type="number" step="0.01" label="Width (ft)" :value="$plot->width_ft ? (float) $plot->width_ft : ''" />
        <x-field name="road_width_ft" type="number" step="0.01" label="Road width (ft)" :value="$plot->road_width_ft ? (float) $plot->road_width_ft : ''" />
        <x-select name="corner_plot" label="Corner plot" :options="['N' => 'No', 'Y' => 'Yes']" :value="$plot->is_corner ? 'Y' : 'N'" />
        <x-field name="east_boundary" label="East boundary" :value="$plot->east_boundary" />
        <x-field name="west_boundary" label="West boundary" :value="$plot->west_boundary" />
        <x-field name="north_boundary" label="North boundary" :value="$plot->north_boundary" />
        <x-field name="south_boundary" label="South boundary" :value="$plot->south_boundary" />
        <div><x-field name="rate_per_sqft" id="rate_per_sqft" type="number" step="0.01" label="Actual rate / sq ft (₹)" :value="(float) $plot->rate_per_sqft" required />
            <p class="hint">Actual price <strong data-calc="size_sqft*rate_per_sqft">—</strong></p></div>
        <x-field name="offer" label="Offer text" :value="$plot->offer_text" placeholder="Diwali offer – ₹100/sq ft off" />
        <div><x-field name="offer_rate_per_sqft" id="offer_rate_per_sqft" type="number" step="0.01" label="Offer rate / sq ft (₹)" :value="$plot->offer_rate_per_sqft ? (float) $plot->offer_rate_per_sqft : ''" />
            <p class="hint">Offer price <strong data-calc="size_sqft*offer_rate_per_sqft">—</strong></p></div>
        <x-field name="offer_valid_till" label="Offer valid till (DD-MM-YYYY)" :value="$plot->offer_valid_till?->format('d-m-Y')" placeholder="DD-MM-YYYY" />
        @unless ($locked)<x-select name="status" label="Status" :options="['Available' => 'Available', 'Blocked' => 'Blocked']" :value="ucfirst($plot->status)" />@endunless
        <div class="flex items-end sm:col-span-2 lg:col-span-4"><button class="btn-primary">Save plot</button></div>
    </form>
    @if ($plot->history->isNotEmpty())
        <div class="card mt-6"><h2 class="section-title p-4">Status history</h2><div class="table-wrap"><table class="tbl">
            <thead><tr><th>When</th><th>From</th><th>To</th><th>Reason</th></tr></thead>
            <tbody>@foreach ($plot->history as $h)<tr><td class="text-xs">{{ \App\Support\Format::datetime($h->created_at) }}</td><td>{{ \App\Models\Plot::STATUSES[$h->from_status] ?? '—' }}</td><td>{{ \App\Models\Plot::STATUSES[$h->to_status] ?? $h->to_status }}</td><td class="text-sm">{{ $h->reason }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
</x-layouts.workspace>
