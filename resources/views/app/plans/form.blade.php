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
        @php($val = fn ($k, $default) => old('limits.'.$k, $plan->exists ? $plan->limit($k) : $default))
        <div class="card card-pad">
            <h2 class="section-title">Plan items</h2>
            <p class="text-sm text-muted">What the tenant gets each period. Use -1 for unlimited. Tenants can buy top-ups on top of these (see Top-up packs).</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (['storage_mb' => 1024, 'projects' => 2, 'plots' => 200, 'users' => 5, 'listings' => (int) \App\Services\AppSettings::get('listings.free', 5)] as $k => $d)
                    <x-field name="limits[{{ $k }}]" type="number" min="-1" :label="\App\Services\PlanLimiter::LIMIT_LABELS[$k].($k === 'storage_mb' ? ' (MB)' : '')" :value="$val($k, $d)" required id="limit_{{ $k }}" />
                @endforeach
            </div>
            <p class="mt-2 text-xs text-muted">Every promoter keeps {{ \App\Services\AppSettings::get('listings.free', 5) }} active listings free (set under Contact view charges), even when this number is lower or the subscription has lapsed. Photos: up to {{ \App\Services\AppSettings::get('listings.image_max_mb', 5) }} MB each.</p>
        </div>
        <div class="card card-pad">
            <h2 class="section-title">Other limits</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['images_per_listing' => 20, 'ai_credits' => 50] as $k => $d)
                    <x-field name="limits[{{ $k }}]" type="number" min="-1" :label="\App\Services\PlanLimiter::LIMIT_LABELS[$k]" :value="$val($k, $d)" required id="limit_{{ $k }}" />
                @endforeach
            </div>
            <details class="mt-4" @if (collect(\App\Services\PlanLimiter::ROLE_CAP_KEYS)->contains(fn ($k) => $plan->exists && $plan->limit($k) >= 0)) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">Optional caps per role</summary>
                <p class="mt-1 text-xs text-muted">Leave at -1 (no cap): the “Users (all roles)” limit already counts everyone. Set a number only to limit one role, e.g. 1 Tenant Admin.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach (\App\Services\PlanLimiter::ROLE_CAP_KEYS as $k)
                        <x-field name="limits[{{ $k }}]" type="number" min="-1" :label="\App\Services\PlanLimiter::LIMIT_LABELS[$k]" :value="$val($k, -1)" required id="limit_{{ $k }}" />
                    @endforeach
                </div>
            </details>
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
