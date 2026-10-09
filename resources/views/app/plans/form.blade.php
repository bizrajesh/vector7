<x-layouts.workspace :title="$plan->exists ? 'Edit plan' : 'New plan'">
    <x-page-header :title="$plan->exists ? 'Edit '.$plan->name : 'New plan'" :back="route('app.plans.index')" />
    <form method="POST" action="{{ $plan->exists ? route('app.plans.update', $plan) : route('app.plans.store') }}" class="space-y-6">
        @csrf @if ($plan->exists) @method('PUT') @endif
        <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field name="name" label="Name" :value="$plan->name" required />
            <x-field name="price" type="number" step="0.01" label="Price (₹, excl. GST)" :value="$plan->price" required />
            <x-select name="billing_cycle" label="Billing cycle" :options="['monthly' => 'Monthly', 'yearly' => 'Yearly']" :value="$plan->billing_cycle" />
            <x-field name="trial_days" type="number" label="Trial days" :value="$plan->trial_days" required />
            <x-textarea name="description" label="Description" :value="$plan->description" rows="2" class="sm:col-span-2 lg:col-span-3" />
            <x-field name="sort" type="number" label="Sort order" :value="$plan->sort" />
            <div class="flex flex-wrap gap-6 sm:col-span-2 lg:col-span-4">
                <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active (can be chosen)" :checked="$plan->is_active" />
                <input type="hidden" name="is_trial_default" value="0"><x-checkbox name="is_trial_default" label="Use for new sign-ups (trial)" :checked="$plan->is_trial_default" />
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="section-title">Limits</h2>
            <p class="text-sm text-muted">Use -1 for unlimited.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (\App\Services\PlanLimiter::LIMIT_LABELS as $k => $l)
                    <x-field name="limits[{{ $k }}]" type="number" :label="$l.($k === 'storage_mb' ? ' (MB)' : '')" :value="$plan->exists ? $plan->limit($k) : 0" required />
                @endforeach
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="section-title">Enabled modules</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (config('permissions.plan_modules') as $m => $l)
                    <x-checkbox name="modules[]" :value="$m" :label="$l" :checked="in_array($m, old('modules', $plan->modules ?? []))" />
                @endforeach
            </div>
        </div>
        <button class="btn-primary">Save plan</button>
    </form>
</x-layouts.workspace>
