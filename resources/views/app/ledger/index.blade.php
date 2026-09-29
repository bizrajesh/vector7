<x-layouts.app title="Accounting">
    <x-page-header title="Accounting" :subtitle="$salesOnly ? 'Sales income' : 'All money in and out, by project'">
        <x-slot:actions><a href="{{ route('app.ledger.export', request()->query()) }}" class="btn-ghost btn-sm"><x-icon name="download" class="h-4 w-4" />Export CSV</a></x-slot:actions>
    </x-page-header>

    <form method="GET" class="card-pad mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-select name="layout_id" label="Project" :options="$layouts->pluck('name', 'id')" :value="request('layout_id')" placeholder="All projects" />
        @unless ($salesOnly)
            <x-select name="type" label="Type" :options="['investment' => 'Investment', 'expense' => 'Expense', 'sales_income' => 'Sales income', 'commission' => 'Commission', 'refund' => 'Refund', 'distribution' => 'Distribution', 'other' => 'Other']" :value="request('type')" placeholder="All types" />
        @endunless
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
        <div class="flex items-end"><button class="btn-ghost w-full">Apply</button></div>
    </form>

    <section class="mb-4 grid grid-cols-3 gap-3">
        <x-kpi label="Money in" :value="\App\Support\Money::short($totals['in'])" tone="good" />
        <x-kpi label="Money out" :value="\App\Support\Money::short($totals['out'])" />
        <x-kpi label="Net" :value="\App\Support\Money::short($totals['in'] - $totals['out'])" />
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2">
            <table class="table min-w-[680px]">
                <thead><tr><th class="pl-4">Date</th><th>Description</th><th>Project</th><th>Type</th><th class="text-right">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="pl-4 whitespace-nowrap">{{ $e->entry_date->format('j M Y') }}</td>
                        <td><span class="block font-semibold">{{ $e->description }}</span><span class="text-xs text-ink-muted">{{ $e->category?->name }} {{ $e->party ? '· '.$e->party : '' }} {{ $e->reference_no ? '· '.$e->reference_no : '' }}</span></td>
                        <td>{{ $e->layout?->name ?? '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $e->type)) }}</td>
                        <td class="num whitespace-nowrap text-right font-semibold {{ $e->direction === 'in' ? 'text-[#1F6B45]' : '' }}">{{ $e->direction === 'in' ? '+' : '−' }}@inr($e->amount)</td>
                        <td class="text-right">
                            @if (! $salesOnly && auth()->user()->can('ledger.manage') && $e->source_type === 'manual' && ! $e->reversal_of)
                                <details class="relative"><summary class="cursor-pointer list-none rounded-lg p-2 text-ink-muted hover:bg-cream-100" aria-label="Reverse entry"><x-icon name="dots" class="h-4 w-4" /></summary>
                                    <form method="POST" action="{{ route('app.ledger.reverse', $e) }}" class="absolute right-0 z-10 mt-1 flex w-60 flex-col gap-2 rounded-xl border border-line bg-white p-3 shadow-card">@csrf
                                        <x-field name="reason" label="Reason" required /><button class="btn-danger btn-sm">Post reversal</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="pl-4 text-ink-muted">No entries for these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (! $salesOnly && auth()->user()->can('ledger.manage'))
            <form method="POST" action="{{ route('app.ledger.store') }}" enctype="multipart/form-data" class="card-pad flex flex-col gap-3">@csrf
                <h2 class="section-title">Manual entry</h2>
                <x-select name="ledger_category_id" label="Category" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.($c->direction === 'in' ? 'in' : 'out').')'])" required />
                <x-select name="type" label="Type" :options="['expense' => 'Expense', 'investment' => 'Investment', 'refund' => 'Refund', 'other' => 'Other']" required />
                <x-select name="layout_id" label="Project" :options="$layouts->pluck('name', 'id')" placeholder="General" />
                <x-field name="amount" type="number" step="0.01" label="Amount (₹)" required />
                <x-field name="entry_date" type="date" label="Date" :value="now()->toDateString()" required />
                <x-field name="description" label="Description" required />
                <x-field name="party" label="Party" />
                <x-field name="reference_no" label="Reference" />
                <div><label for="ledger-att" class="label">Attachment</label><input id="ledger-att" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input py-2.5"></div>
                <button class="btn-primary">Post entry</button>
                <p class="help">Posted entries cannot be edited; use a reversal to correct.</p>
            </form>
        @endif
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
