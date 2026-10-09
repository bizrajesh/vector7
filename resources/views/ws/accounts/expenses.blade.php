<x-layouts.workspace title="Expenses">
    <x-page-header title="Expenses" subtitle="Link an expense to a project stage or facility so budget vs actual stays right.">
        @can('accounts.export')<x-export-buttons />@endcan
    </x-page-header>
    @include('ws.accounts._nav')
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-filters>
                <x-field name="q" label="Search" :value="request('q')" placeholder="Vendor, description, txn" />
                <x-select name="project" label="Project" :options="$projects->pluck('name', 'id')" :value="request('project')" placeholder="All" />
                <x-select name="category" label="Category" :options="$categories->pluck('name', 'id')" :value="request('category')" placeholder="All" />
                <x-field name="from" type="date" label="From" :value="request('from')" />
                <x-field name="to" type="date" label="To" :value="request('to')" />
            </x-filters>
            <div class="card">
                <div class="flex items-center justify-between p-4"><p class="text-sm text-muted">{{ number_format($expenses->total()) }} expense(s)</p><p class="text-lg font-extrabold tabular-nums">Total @inr($total)</p></div>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Txn</th><th>Description</th><th>Project</th><th>Category</th><th class="num">Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse ($expenses as $e)
                        <tr>
                            <td><span class="font-mono text-xs font-semibold">{{ $e->transaction_no }}</span><p class="text-xs text-muted">@date($e->spent_on)</p></td>
                            <td>{{ $e->description }}<p class="text-xs text-muted">{{ $e->vendor }}{{ $e->mode ? ' · '.(\App\Models\Payment::MODES[$e->mode] ?? $e->mode) : '' }}{{ $e->reference_no ? ' · '.$e->reference_no : '' }}</p></td>
                            <td>{{ $e->project?->name ?? 'General' }}@if ($e->stage || $e->estimateLine)<p class="text-xs text-muted">{{ $e->stage?->name ?? $e->estimateLine?->description }}</p>@endif</td>
                            <td>{{ $e->category?->name }}@if ($e->refund_id)<p><span class="badge-amber">Refund</span></p>@endif</td>
                            <td class="num font-semibold">@inr($e->amount)</td>
                            <td class="whitespace-nowrap">
                                @if ($e->bill)<a href="{{ route('files.show', $e->bill) }}" class="btn-ghost btn-sm" aria-label="View bill"><x-icon name="link" class="h-4 w-4" /></a>@endif
                                @if (! $e->refund_id)
                                    @can('accounts.update')
                                        <details class="inline-block"><summary class="btn-ghost btn-sm cursor-pointer">Edit</summary>
                                            <form method="POST" action="{{ route('ws.expenses.update', $e) }}" enctype="multipart/form-data" class="card card-pad absolute right-4 z-20 mt-1 grid w-[min(92vw,34rem)] gap-3 sm:grid-cols-2">
                                                @csrf @method('PUT')
                                                @include('ws.accounts._expense-fields', ['e' => $e, 'p' => 'e'.$e->id])
                                                <div class="sm:col-span-2"><button class="btn-primary">Save</button></div>
                                            </form>
                                        </details>
                                    @endcan
                                    @can('accounts.delete')<x-confirm :action="route('ws.expenses.destroy', $e)" method="DELETE" message="Delete expense {{ $e->transaction_no }}?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No expenses yet" icon="doc" /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="p-4">{{ $expenses->links() }}</div>
            </div>
        </div>
        <div class="space-y-6">
            @can('accounts.create')
                <form id="new-expense" method="POST" action="{{ route('ws.expenses.store') }}" enctype="multipart/form-data" class="card card-pad grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    @csrf
                    <h2 class="section-title sm:col-span-2 xl:col-span-1">Record an expense</h2>
                    @include('ws.accounts._expense-fields', ['e' => new \App\Models\Expense(), 'p' => 'n'])
                    <div class="sm:col-span-2 xl:col-span-1"><button class="btn-primary w-full">Save expense</button></div>
                </form>
                <form method="POST" action="{{ route('ws.expenses.category') }}" class="card card-pad">
                    @csrf
                    <h2 class="section-title">Categories</h2>
                    <p class="mt-1 text-sm text-muted">{{ $categories->pluck('name')->join(', ') ?: 'None yet.' }}</p>
                    <div class="mt-3 flex gap-2"><x-field name="name" label="New category" class="flex-1" /><button class="btn-light self-end">Add</button></div>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.workspace>
