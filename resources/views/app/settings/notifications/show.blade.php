<x-layouts.app :title="$group->name">
    <x-page-header :title="$group->name" subtitle="Notification group" :back="route('app.settings.notifications.index')" />
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.settings.notifications.update', $group) }}" class="card-pad flex flex-col gap-3">
            @csrf @method('PUT')
            <x-field name="name" label="Group name" :value="$group->name" required />
            <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channel_email" value="1" class="check" @checked($group->channel_email)> Email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channel_whatsapp" value="1" class="check" @checked($group->channel_whatsapp)> WhatsApp</label>
            </div>
            <fieldset>
                <legend class="label">Events</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($eventOptions as $code => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="events[]" value="{{ $code }}" class="check" @checked($group->events->contains('event_code', $code))> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="btn-primary">Save</button>
        </form>
        <div class="card-pad">
            <h2 class="section-title mb-2">Members</h2>
            @foreach ($group->members as $member)
                <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                    <span class="flex-1"><span class="block font-semibold">{{ $member->user?->name ?? $member->name }}</span><span class="text-xs text-ink-muted">{{ $member->user?->email ?? $member->email }} {{ $member->user?->phone ?? $member->phone }}</span></span>
                    <form method="POST" action="{{ route('app.settings.notifications.members.destroy', [$group, $member]) }}">@csrf @method('DELETE')
                        <button class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-700" aria-label="Remove member"><x-icon name="trash" class="h-4 w-4" /></button>
                    </form>
                </div>
            @endforeach
            <form method="POST" action="{{ route('app.settings.notifications.members.store', $group) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                @csrf
                <x-select name="user_id" label="Team member" :options="$users->pluck('name', 'id')" placeholder="— or add an external contact —" class="sm:col-span-2" />
                <x-field name="name" label="External name" />
                <x-field name="email" type="email" label="Email" />
                <x-field name="phone" type="tel" label="WhatsApp number" />
                <div class="flex items-end"><button type="submit" class="btn-outline w-full">Add member</button></div>
            </form>
        </div>
    </div>
</x-layouts.app>
