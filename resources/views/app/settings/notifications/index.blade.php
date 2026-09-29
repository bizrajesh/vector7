<x-layouts.app title="Notification groups">
    <x-page-header title="Settings" subtitle="Notification groups receive email and WhatsApp alerts for chosen events" />
    @include('app.settings._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @forelse ($groups as $group)
                <a href="{{ route('app.settings.notifications.show', $group) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                    <span class="flex-1 font-semibold">{{ $group->name }}</span>
                    @if ($group->channel_email)<span class="badge-os">Email</span>@endif
                    @if ($group->channel_whatsapp)<span class="badge-av">WhatsApp</span>@endif
                    <span class="text-sm text-ink-muted">{{ $group->members_count }} members · {{ $group->events_count }} events</span>
                </a>
            @empty
                <x-empty title="No notification groups" icon="bell">Create a group, add members and choose the events they should hear about.</x-empty>
            @endforelse
        </div>
        <form method="POST" action="{{ route('app.settings.notifications.store') }}" class="card-pad flex flex-col gap-3">
            @csrf
            <h2 class="section-title">New group</h2>
            <x-field name="name" label="Group name" required />
            <input type="hidden" name="channel_email" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channel_email" value="1" class="check" checked> Email</label>
            <input type="hidden" name="channel_whatsapp" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channel_whatsapp" value="1" class="check"> WhatsApp</label>
            <button type="submit" class="btn-primary">Create group</button>
        </form>
    </div>
</x-layouts.app>
