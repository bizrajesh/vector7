<x-layouts.app title="Users & Roles">
    <x-page-header title="Users & Roles" subtitle="Create logins for Sales, Shareholders, Customers and other Admins">
        <x-slot:actions><a href="{{ route('app.users.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" stroke="2.2" />Add user</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        @foreach ($users as $u)
            <a href="{{ route('app.users.edit', $u) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-teal-50 text-[13px] font-bold text-teal">{{ $u->initials() }}</span>
                <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $u->name }}</span><span class="block truncate text-xs text-ink-muted">{{ $u->email }}@if ($u->shareholder) · {{ $u->shareholder->name }}@endif @if ($u->customer) · {{ $u->customer->name }}@endif</span></span>
                <span class="badge-os">{{ $u->role->label() }}</span>
                @if ($u->status !== 'active')<span class="badge-od">{{ ucfirst($u->status) }}</span>@endif
            </a>
        @endforeach
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
