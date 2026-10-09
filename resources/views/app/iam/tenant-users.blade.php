<x-layouts.workspace title="Tenant users">
    <x-page-header title="Tenant users" subtitle="App Admin can generate passwords, send reset links and unlock any tenant user." :back="route('app.iam.index')" />
    @include('partials.generated-password')
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" />
        <x-select name="tenant" label="Tenant" :options="$tenants" :value="request('tenant')" placeholder="All tenants" />
    </x-filters>
    <div class="card">
        <div class="flex justify-end border-b border-navy-50 p-4">
            <form method="POST" action="{{ route('app.iam.bulk') }}" id="bulk-form" class="flex items-center gap-2">@csrf
                <input type="hidden" name="must_change" value="0"><x-checkbox name="must_change" label="Change at next login" :checked="true" />
                <button class="btn-light btn-sm" data-bulk-submit="bulk-form" data-bulk-name="users"><x-icon name="download" class="h-4 w-4" /> Generate passwords (Excel)</button>
            </form>
        </div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th class="w-8"><input type="checkbox" class="checkbox" data-check-all="users" aria-label="Select all"></th><th>Name</th><th>Tenant</th><th>Role</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td><input type="checkbox" class="checkbox" data-bulk="users" value="{{ $u->id }}" aria-label="Select {{ $u->name }}"></td>
                    <td><p class="font-semibold">{{ $u->name }}</p><p class="text-xs text-muted">{{ $u->email }}</p></td>
                    <td class="text-sm">{{ $u->tenant?->name }}</td><td class="text-sm">{{ $u->role?->name }}</td>
                    <td>@if ($u->isLocked())<span class="badge-red">Locked</span>@elseif ($u->is_active)<span class="badge-teal">Active</span>@else<span class="badge-gray">Disabled</span>@endif</td>
                    <td><div class="flex justify-end gap-1">
                        @include('partials.generate-form', ['action' => route('app.iam.generate', $u), 'name' => $u->name])
                        <x-confirm :action="route('app.iam.reset-link', $u)" message="Email a reset link to {{ $u->email }}?">Reset link</x-confirm>
                        @if ($u->isLocked())<x-confirm :action="route('app.iam.unlock', $u)" message="Unlock {{ $u->name }}?">Unlock</x-confirm>@endif
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="p-4">{{ $users->links() }}</div>
    </div>
</x-layouts.workspace>
