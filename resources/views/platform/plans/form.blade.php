<x-layouts.platform :title="$plan->exists ? 'Edit plan' : 'New plan'">
    <x-page-header :title="$plan->exists ? 'Edit '.$plan->name : 'New plan'" :back="route('platform.plans.index')" />
    <form method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" class="card-pad grid max-w-4xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf @if ($plan->exists) @method('PUT') @endif
        <x-field name="name" label="Name" :value="$plan->name" required />
        <x-field name="code" label="Code" :value="$plan->code" required help="Used in sign-up links" />
        <x-field name="badge" label="Badge" :value="$plan->badge" help="e.g. Most popular" />
        <x-field name="sort_order" type="number" label="Sort order" :value="$plan->sort_order ?? 0" required />
        <x-field name="description" label="Description" :value="$plan->description" class="sm:col-span-2 lg:col-span-4" />
        <x-field name="price_monthly" type="number" step="0.01" label="Price / month (₹)" :value="$plan->price_monthly ?? 0" required />
        <x-field name="price_yearly" type="number" step="0.01" label="Price / year (₹)" :value="$plan->price_yearly ?? 0" required />
        <x-field name="trial_days" type="number" label="Trial days" :value="$plan->trial_days" required />
        <x-select name="status" label="Status" :options="['active' => 'Active', 'hidden' => 'Hidden', 'archived' => 'Archived']" :value="$plan->status" />
        <x-field name="max_users" type="number" label="Max users" :value="$plan->max_users ?? 3" required />
        <x-field name="max_layouts" type="number" label="Max layout projects" :value="$plan->max_layouts ?? 1" required />
        <x-field name="max_plots" type="number" label="Max plots" :value="$plan->max_plots ?? 150" required />
        <x-field name="max_storage_mb" type="number" label="Storage (MB)" :value="$plan->max_storage_mb ?? 2048" required />
        <fieldset class="sm:col-span-2 lg:col-span-4">
            <legend class="label">Features</legend>
            <div class="flex flex-wrap gap-4">
                @foreach ($features as $feature)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $feature }}" class="check" @checked($plan->features[$feature] ?? false)> {{ ucwords(str_replace('_', ' ', $feature)) }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="sm:col-span-2 lg:col-span-4"><button class="btn-navy">Save plan</button></div>
    </form>
</x-layouts.platform>
