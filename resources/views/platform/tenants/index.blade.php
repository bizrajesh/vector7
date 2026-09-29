<x-layouts.platform title="Tenants">
    <x-page-header title="Tenants" subtitle="Every business using Vector7" />
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <label for="tq" class="sr-only">Search</label>
        <input id="tq" type="search" name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Name or email">
        <label for="ts" class="sr-only">Status</label>
        <select id="ts" name="status" class="input max-w-[180px]"><option value="">All statuses</option>
            @foreach (\App\Enums\SubscriptionStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
        </select>
        <button class="btn-ghost">Filter</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table min-w-[720px]">
            <thead><tr><th class="pl-4">Tenant</th><th>Plan</th><th>Status</th><th>Users</th><th>Trial / period end</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @foreach ($tenants as $t)
                <tr>
                    <td class="pl-4"><span class="block font-semibold">{{ $t->name }}</span><span class="text-xs text-ink-muted">{{ $t->email }} · {{ $t->city }}</span></td>
                    <td>{{ $t->plan?->name }}</td><td><x-status :status="$t->status" /></td>
                    <td class="num">{{ $t->users_count }} / {{ $t->plan?->max_users }}</td>
                    <td>{{ ($t->subscription?->current_period_end ?? $t->subscription?->trial_ends_at)?->format('j M Y') ?? '—' }}</td>
                    <td>{{ $t->created_at->format('j M Y') }}</td>
                    <td class="text-right"><a href="{{ route('platform.tenants.show', $t) }}" class="font-semibold">Manage</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tenants->links() }}</div>
</x-layouts.platform>
