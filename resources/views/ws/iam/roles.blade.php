<x-layouts.workspace title="Roles & permissions">
    <x-page-header title="Users & roles" subtitle="System roles follow the vector7 permission matrix. Custom roles combine existing permissions." />
    @include('ws.iam.tabs')
    @php($actions = config('permissions.actions'))
    @php($bases = collect(config('permissions.role_labels'))->only(['tenant_manager', 'tenant_sales', 'tenant_account', 'support']))
    <div class="space-y-4">
        @foreach ($roles as $role)
            <details class="card" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer items-center justify-between gap-3 p-4">
                    <span><span class="font-bold">{{ $role->name }}</span> <span class="ml-2 {{ $role->is_system ? 'badge-navy' : 'badge-teal' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span></span>
                    <span class="text-sm text-muted">{{ $role->users_count }} user(s)</span>
                </summary>
                <div class="border-t border-navy-50 p-4">
                    @php($keys = array_flip($role->permissionKeys()))
                    @php($editable = ! $role->is_system && auth()->user()->isTenantAdmin())
                    <form method="POST" action="{{ $editable ? route('ws.roles.update', $role) : '#' }}">
                        @csrf @method('PUT')
                        @if ($editable)
                            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                                <x-field name="name" label="Role name" :value="$role->name" required />
                                <x-select name="base_role" label="Counts as (plan user limit & dashboard)" :options="$bases" :value="$role->base_role" />
                            </div>
                        @endif
                        <div class="table-wrap">
                            <table class="tbl">
                                <thead><tr><th>Module</th>@foreach ($actions as $a)<th class="text-center">{{ ucfirst($a) }}</th>@endforeach</tr></thead>
                                <tbody>
                                @foreach ($catalog as $module => $label)
                                    <tr><td class="font-medium">{{ $label }}</td>
                                        @foreach ($actions as $a)
                                            <td class="text-center">
                                                @if ($editable)
                                                    <input type="checkbox" class="checkbox" name="permissions[]" value="{{ $module }}.{{ $a }}" @checked(isset($keys["$module.$a"])) aria-label="{{ $label }} {{ $a }}">
                                                @else
                                                    @if (isset($keys["$module.$a"]))<span class="text-teal-700" aria-label="Allowed">{!! \App\Support\Icons::svg('check', 'h-4 w-4 inline') !!}</span>@else<span class="text-muted-light" aria-label="Not allowed">–</span>@endif
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($editable)<div class="mt-4 flex gap-2"><button class="btn-primary">Save role</button></div>@endif
                    </form>
                    @if ($editable)
                        <div class="mt-2"><x-confirm :action="route('ws.roles.destroy', $role)" method="DELETE" message="Delete role {{ $role->name }}?" class="btn-ghost btn-sm text-red-700">Delete role</x-confirm></div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
    @if (auth()->user()->isTenantAdmin())
        <details class="card card-pad mt-6">
            <summary class="cursor-pointer font-bold">Create a custom role</summary>
            <form method="POST" action="{{ route('ws.roles.store') }}" class="mt-4">
                @csrf
                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <x-field name="name" label="Role name" required placeholder="e.g. Site Engineer" />
                    <x-select name="base_role" label="Counts as (plan user limit & dashboard)" :options="$bases" value="tenant_manager" />
                </div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Module</th>@foreach ($actions as $a)<th class="text-center">{{ ucfirst($a) }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($catalog as $module => $label)
                            <tr><td class="font-medium">{{ $label }}</td>@foreach ($actions as $a)<td class="text-center"><input type="checkbox" class="checkbox" name="permissions[]" value="{{ $module }}.{{ $a }}" aria-label="{{ $label }} {{ $a }}"></td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn-primary mt-4">Create role</button>
            </form>
        </details>
    @endif
</x-layouts.workspace>
