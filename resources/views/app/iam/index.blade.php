<x-layouts.workspace title="IAM">
    <x-page-header title="IAM" subtitle="App users (App Admin, App Manager). App Managers can use every App module except IAM and Settings.">
        <a href="{{ route('app.iam.tenant-users') }}" class="btn-light"><x-icon name="users" class="h-4 w-4" /> Tenant users</a>
    </x-page-header>
    @include('partials.generated-password')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td><p class="font-semibold">{{ $u->name }}</p><p class="text-xs text-muted">{{ $u->email }}</p></td>
                        <td>{{ $u->role->name }}</td>
                        <td>@if ($u->isLocked())<span class="badge-red">Locked</span>@elseif ($u->is_active)<span class="badge-teal">Active</span>@else<span class="badge-gray">Disabled</span>@endif</td>
                        <td class="text-xs">{{ \App\Support\Format::datetime($u->last_login_at) }}</td>
                        <td><div class="flex flex-wrap justify-end gap-1">
                            @include('partials.generate-form', ['action' => route('app.iam.generate', $u), 'name' => $u->name])
                            <details class="relative" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                                <form method="POST" action="{{ route('app.iam.update', $u) }}" class="absolute right-0 z-20 mt-2 w-80 space-y-2 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50">
                                    @csrf @method('PUT')
                                    <x-field name="name" label="Name" :value="$u->name" id="u{{ $u->id }}n" required />
                                    <x-field name="email" type="email" label="Email" :value="$u->email" id="u{{ $u->id }}e" required />
                                    <x-field name="mobile" label="Mobile" :value="$u->mobile" id="u{{ $u->id }}m" />
                                    <x-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" :value="$u->role_id" id="u{{ $u->id }}r" />
                                    <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$u->is_active" />
                                    <button class="btn-primary btn-sm">Save</button>
                                </form>
                            </details>
                            <x-confirm :action="route('app.iam.reset-link', $u)" message="Email a reset link to {{ $u->email }}?">Reset link</x-confirm>
                            @if ($u->isLocked())<x-confirm :action="route('app.iam.unlock', $u)" message="Unlock {{ $u->name }}?">Unlock</x-confirm>@endif
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <form method="POST" action="{{ route('app.iam.store') }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="section-title">Add App user</h2>
            <x-field name="name" label="Name" required id="nu_n" />
            <x-field name="email" type="email" label="Email" required id="nu_e" />
            <x-field name="mobile" label="Mobile" id="nu_m" />
            <x-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" id="nu_r" />
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="password_mode" value="link" checked class="text-teal-700 focus:ring-teal"> Email a set-password link</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="password_mode" value="generate" class="text-teal-700 focus:ring-teal"> Generate a password now</label>
            <div class="space-y-2 pl-6" data-show-when="password_mode=generate">
                <input type="hidden" name="must_change" value="0"><x-checkbox name="must_change" label="Change at next login" :checked="true" />
                <x-checkbox name="email_user" label="Also email it" />
            </div>
            <button class="btn-primary">Create</button>
        </form>
    </div>
</x-layouts.workspace>
