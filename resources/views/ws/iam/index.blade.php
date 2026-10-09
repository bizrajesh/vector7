<x-layouts.workspace title="Users & roles">
    <x-page-header title="Users & roles" subtitle="People who can sign in to this workspace.">
        @can('tenant_iam.create')
            <a href="{{ route('ws.iam.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add user</a>
        @endcan
    </x-page-header>
    @include('ws.iam.tabs')
    @include('partials.generated-password')

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach (\App\Services\PlanLimiter::USER_ROLE_KEYS as $base => $key)
            @php($u = $usage[$key])
            <div class="card p-4">
                <p class="text-xs font-semibold text-muted">{{ $u['label'] }}</p>
                <p class="mt-1 text-lg font-extrabold">{{ $u['display']['used'] }} <span class="text-sm font-semibold text-muted">/ {{ $u['display']['limit'] }}</span></p>
                @unless ($u['unlimited'])<x-progress :pct="$u['pct']" class="mt-2" :label="$u['label']" />@endunless
            </div>
        @endforeach
    </div>

    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, email or mobile" />
        <x-select name="role" label="Role" :options="$roles->pluck('name', 'id')" :value="request('role')" placeholder="All roles" />
        <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Disabled', 'locked' => 'Locked']" :value="request('status')" placeholder="Any" />
    </x-filters>

    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy-50 p-4">
            <p class="text-sm text-muted">{{ $users->total() }} user(s)</p>
            @can('tenant_iam.update')
                <form method="POST" action="{{ route('ws.iam.bulk') }}" id="bulk-form" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="must_change" value="0">
                    <x-checkbox name="must_change" label="Change at next login" :checked="true" />
                    <button class="btn-light btn-sm" data-bulk-submit="bulk-form" data-bulk-name="users"><x-icon name="download" class="h-4 w-4" /> Generate passwords (Excel)</button>
                </form>
            @endcan
        </div>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr>
                    <th class="w-8"><input type="checkbox" class="checkbox" data-check-all="users" aria-label="Select all"></th>
                    <th>Name</th><th>Role</th><th>Groups</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td><input type="checkbox" class="checkbox" data-bulk="users" value="{{ $u->id }}" aria-label="Select {{ $u->name }}"></td>
                        <td><p class="font-semibold">{{ $u->name }}</p><p class="text-xs text-muted">{{ $u->email }} · {{ $u->mobile }}</p>@if ($u->user_code)<p class="text-xs text-muted">{{ $u->user_code }}</p>@endif</td>
                        <td>{{ $u->role->name }}</td>
                        <td class="text-xs">{{ $u->groups->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($u->isLocked())<span class="badge-red">Locked</span>
                            @elseif ($u->is_active)<span class="badge-teal">Active</span>
                            @else<span class="badge-gray">Disabled</span>@endif
                            @if ($u->must_change_password)<span class="badge-amber mt-1">Must change password</span>@endif
                        </td>
                        <td class="whitespace-nowrap text-xs">{{ \App\Support\Format::datetime($u->last_login_at) }}</td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-1">
                                @if (auth()->user()->canGeneratePasswordFor($u))
                                    @include('partials.generate-form', ['action' => route('ws.iam.generate', $u), 'name' => $u->name])
                                @endif
                                @can('tenant_iam.update')
                                    @if (! (auth()->user()->baseRole() === 'support' && $u->isTenantAdmin()))
                                        <a href="{{ route('ws.iam.edit', $u) }}" class="btn-light btn-sm">Edit</a>
                                        <x-confirm :action="route('ws.iam.reset-link', $u)" message="Email a password-reset link to {{ $u->email }}?">Reset link</x-confirm>
                                    @endif
                                    @if ($u->isLocked() && auth()->user()->isTenantAdmin())
                                        <x-confirm :action="route('ws.iam.unlock', $u)" message="Unlock {{ $u->name }}?">Unlock</x-confirm>
                                    @endif
                                @endcan
                                @can('tenant_iam.delete')
                                    @if ($u->id !== auth()->id())
                                        <x-confirm :action="route('ws.iam.destroy', $u)" method="DELETE" message="Delete {{ $u->name }}? This cannot be undone." class="btn-ghost btn-sm text-red-700">Delete</x-confirm>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty title="No users match" icon="users" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $users->links() }}</div>
    </div>

    @can('tenant_iam.create')
        <details class="card card-pad mt-6">
            <summary class="cursor-pointer font-bold">Bulk import users from CSV</summary>
            <form method="POST" action="{{ route('ws.iam.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <p class="text-sm text-muted">Paste the CSV from the template's Users sheet (column H) or upload a .csv file. First line: <code class="rounded bg-page px-1">full_name,email,mobile,role,notification_groups</code>. Roles: Admin, Manager, Sales, Accountant, Support. Separate several groups with ";". Each new user gets a set-password link by email.</p>
                <x-textarea name="csv" rows="5" placeholder="full_name,email,mobile,role,notification_groups&#10;Ravi Kumar,ravi@example.com,9876543210,Manager,Management;Sales Team" />
                <x-field name="file" type="file" label="…or upload a CSV file" accept=".csv,text/csv" />
                <button class="btn-primary">Import users</button>
            </form>
        </details>
    @endcan
</x-layouts.workspace>
