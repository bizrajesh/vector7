<x-layouts.workspace title="Notification groups">
    <x-page-header title="Users & roles" subtitle="Emails (budget alerts, bookings, receipts…) go to everyone in the matching group." />
    @include('ws.iam.tabs')
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($groups as $g)
            <form method="POST" action="{{ route('ws.groups.update', $g) }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-2"><h2 class="section-title">{{ $g->name }}</h2><span class="{{ $g->is_active ? 'badge-teal' : 'badge-gray' }}">{{ $g->is_active ? 'Active' : 'Inactive' }}</span></div>
                <x-field name="name" label="Name" :value="$g->name" required id="g{{ $g->id }}name" />
                <x-field name="description" label="Description" :value="$g->description" id="g{{ $g->id }}desc" />
                <fieldset><legend class="label">Members</legend>
                    <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach ($users as $u)<x-checkbox name="users[]" :value="$u->id" :label="$u->name" :checked="$g->users->contains($u)" />@endforeach
                    </div>
                </fieldset>
                <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$g->is_active" />
                @can('tenant_iam.update')<button class="btn-primary btn-sm">Save group</button>@endcan
            </form>
        @endforeach
        @can('tenant_iam.create')
            <form method="POST" action="{{ route('ws.groups.store') }}" class="card card-pad space-y-3">
                @csrf
                <h2 class="section-title">New group</h2>
                <x-field name="name" label="Name" required id="new_group_name" />
                <x-field name="description" label="Description" id="new_group_desc" />
                <fieldset><legend class="label">Members</legend>
                    <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2">@foreach ($users as $u)<x-checkbox name="users[]" :value="$u->id" :label="$u->name" />@endforeach</div>
                </fieldset>
                <button class="btn-primary btn-sm">Create group</button>
            </form>
        @endcan
    </div>
</x-layouts.workspace>
