<x-layouts.app title="Stage groups">
    <x-page-header title="Settings" subtitle="Stage groups: the sequence of activities that brings a layout to launch" />
    @include('app.settings._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @forelse ($groups as $group)
                <a href="{{ route('app.settings.stage-groups.show', $group) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                    <span class="flex-1"><span class="block font-semibold">{{ $group->name }}</span><span class="text-xs text-ink-muted">{{ $group->description }}</span></span>
                    <span class="text-sm text-ink-muted">{{ $group->stages_count }} stages · @inr($group->stages_sum_cost ?? 0)</span>
                    @unless ($group->is_active)<span class="badge-rs">Inactive</span>@endunless
                </a>
            @empty
                <x-empty title="No stage groups yet" icon="ops" />
            @endforelse
        </div>
        <form method="POST" action="{{ route('app.settings.stage-groups.store') }}" class="card-pad flex flex-col gap-3">
            @csrf
            <h2 class="section-title">New stage group</h2>
            <x-field name="name" label="Name" required />
            <x-field name="description" label="Description" />
            <button type="submit" class="btn-primary">Create group</button>
        </form>
    </div>
</x-layouts.app>
