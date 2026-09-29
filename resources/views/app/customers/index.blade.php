<x-layouts.app title="Customers">
    <x-page-header title="Customers" subtitle="Buyers and prospects" />
    <form method="GET" class="mb-4 flex max-w-md gap-2" role="search">
        <label for="cq" class="sr-only">Search</label>
        <input id="cq" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="Name or phone">
        <button class="btn-ghost">Search</button>
    </form>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @forelse ($customers as $c)
                <a href="{{ route('app.customers.show', $c) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#F0EEE3] text-[13px] font-bold">{{ mb_strtoupper(mb_substr($c->name, 0, 2)) }}</span>
                    <span class="flex-1"><span class="block font-semibold">{{ $c->name }}</span><span class="text-xs text-ink-muted">{{ $c->phone }} {{ $c->email ? '· '.$c->email : '' }}</span></span>
                    <span class="text-sm text-ink-muted">{{ $c->sales_count }} {{ \Illuminate\Support\Str::plural('sale', $c->sales_count) }}</span>
                </a>
            @empty
                <x-empty title="No customers found" icon="users" />
            @endforelse
        </div>
        @can('customers.manage')
            <form method="POST" action="{{ route('app.customers.store') }}" class="card-pad flex flex-col gap-3">@csrf
                <h2 class="section-title">Add customer</h2>
                <x-field name="name" label="Full name" required />
                <x-field name="phone" type="tel" label="Mobile" required />
                <x-field name="email" type="email" label="Email" />
                <x-field name="aadhaar" label="Aadhaar" inputmode="numeric" autocomplete="off" />
                <x-field name="pan" label="PAN" autocomplete="off" />
                <x-field name="address" label="Address" />
                <button class="btn-primary">Save customer</button>
            </form>
        @endcan
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.app>
