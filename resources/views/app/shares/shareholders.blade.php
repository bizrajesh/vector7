<x-layouts.app title="Shareholders">
    <x-page-header title="Shareholders" subtitle="Partners who contribute capital or land" :back="route('app.shares.index')" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @forelse ($shareholders as $s)
                <details class="border-t border-line-soft first:border-t-0">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="flex-1"><span class="block font-semibold">{{ $s->name }}</span><span class="text-xs text-ink-muted">{{ $s->phone }} {{ $s->email ? '· '.$s->email : '' }} · PAN {{ $s->pan ? substr($s->pan, 0, 2).'XXXXX'.substr($s->pan, -3) : '—' }}</span></span>
                        <span class="text-sm text-ink-muted">{{ $s->issuances_count }} allocations</span>
                    </summary>
                    @can('shares.allocate')
                        <form method="POST" action="{{ route('app.shares.shareholders.update', $s) }}" class="grid gap-3 px-4 pb-4 sm:grid-cols-2">@csrf @method('PUT')
                            <x-field name="name" label="Name" :value="$s->name" required />
                            <x-field name="phone" type="tel" label="Phone" :value="$s->phone" />
                            <x-field name="email" type="email" label="Email" :value="$s->email" />
                            <x-field name="pan" label="PAN" help="Leave blank to keep the stored value" autocomplete="off" />
                            <x-field name="bank_details" label="Bank details" help="Leave blank to keep the stored value" autocomplete="off" class="sm:col-span-2" />
                            <div><button class="btn-ghost btn-sm">Save</button></div>
                        </form>
                    @endcan
                </details>
            @empty
                <x-empty title="No shareholders yet" icon="users" />
            @endforelse
        </div>
        @can('shares.allocate')
            <form method="POST" action="{{ route('app.shares.shareholders.store') }}" class="card-pad flex flex-col gap-3">@csrf
                <h2 class="section-title">Add shareholder</h2>
                <x-field name="name" label="Name" required />
                <x-field name="phone" type="tel" label="Phone" />
                <x-field name="email" type="email" label="Email" />
                <x-field name="pan" label="PAN" autocomplete="off" help="Stored encrypted" />
                <x-field name="bank_details" label="Bank account / IFSC" autocomplete="off" help="Stored encrypted" />
                <button class="btn-primary">Add</button>
            </form>
        @endcan
    </div>
    <div class="mt-4">{{ $shareholders->links() }}</div>
</x-layouts.app>
