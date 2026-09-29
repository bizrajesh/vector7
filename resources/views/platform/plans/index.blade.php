<x-layouts.platform title="Subscription plans">
    <x-page-header title="Subscription plans" subtitle="Plans shown on the pricing and sign-up pages">
        <x-slot:actions><a href="{{ route('platform.plans.create') }}" class="btn-navy"><x-icon name="plus" class="h-4 w-4" stroke="2.2" />New plan</a></x-slot:actions>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table min-w-[760px]">
            <thead><tr><th class="pl-4">Plan</th><th>Monthly</th><th>Yearly</th><th>Trial</th><th>Limits (users / projects / plots)</th><th>Tenants</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td class="pl-4"><span class="block font-semibold">{{ $plan->name }}</span><span class="text-xs text-ink-muted">{{ $plan->code }} {{ $plan->badge ? '· '.$plan->badge : '' }}</span></td>
                    <td class="num">@inr($plan->price_monthly)</td><td class="num">@inr($plan->price_yearly)</td><td>{{ $plan->trial_days }} days</td>
                    <td class="num">{{ $plan->max_users }} / {{ $plan->max_layouts }} / {{ number_format($plan->max_plots) }}</td>
                    <td class="num">{{ $plan->tenants_count }}</td>
                    <td><span class="{{ ['active' => 'badge-av', 'hidden' => 'badge-rs', 'archived' => 'badge-od'][$plan->status] }}">{{ ucfirst($plan->status) }}</span></td>
                    <td class="text-right"><a href="{{ route('platform.plans.edit', $plan) }}" class="font-semibold">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.platform>
