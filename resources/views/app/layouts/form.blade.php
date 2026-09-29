<x-layouts.app :title="$layout->exists ? 'Edit layout' : 'New layout'">
    <x-page-header :title="$layout->exists ? 'Edit '.$layout->name : 'New layout project'" subtitle="Step 1 · Layout details. Owners, survey numbers, documents and stages come next." :back="$layout->exists ? route('app.layouts.show', $layout) : route('app.layouts.index')" />
    <form method="POST" action="{{ $layout->exists ? route('app.layouts.update', $layout) : route('app.layouts.store') }}" class="card-pad grid max-w-4xl gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @csrf @if ($layout->exists) @method('PUT') @endif
        <x-field name="name" label="Layout name" :value="$layout->name" required class="sm:col-span-2" />
        <x-field name="code" label="Code" :value="$layout->code" required help="e.g. LP-001" />
        <x-field name="location" label="Location" :value="$layout->location" class="sm:col-span-2 lg:col-span-3" />
        <x-field name="village" label="Village" :value="$layout->village" />
        <x-field name="taluk" label="Taluk" :value="$layout->taluk" />
        <x-field name="district" label="District" :value="$layout->district" />
        <x-field name="latitude" type="number" step="0.0000001" label="Latitude" :value="$layout->latitude" />
        <x-field name="longitude" type="number" step="0.0000001" label="Longitude" :value="$layout->longitude" />
        <x-field name="planned_launch_date" type="date" label="Target launch date" :value="$layout->planned_launch_date?->toDateString()" />
        <x-field name="total_sqft" type="number" step="0.01" label="Total area (sqft)" :value="$layout->total_sqft" required inputmode="decimal" help="1 acre = 43,560 sqft" />
        <x-field name="sellable_pct" type="number" step="0.01" label="Sellable %" :value="$layout->sellable_pct" required />
        <x-field name="std_plot_sqft" type="number" step="0.01" label="Standard plot size (sqft)" :value="$layout->std_plot_sqft" required />
        <x-field name="land_cost" type="number" step="0.01" label="Land cost (₹)" :value="$layout->land_cost ?? 0" required inputmode="decimal" />
        <x-field name="contingency_pct" type="number" step="0.01" label="Contingency %" :value="$layout->contingency_pct" required />
        <x-field name="default_rate_sqft" type="number" step="0.01" label="Planned rate per sqft (₹)" :value="$layout->default_rate_sqft" />
        <div class="sm:col-span-2 lg:col-span-3"><button type="submit" class="btn-primary">{{ $layout->exists ? 'Save changes' : 'Create layout' }}</button></div>
    </form>
</x-layouts.app>
