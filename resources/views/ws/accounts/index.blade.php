<x-layouts.workspace title="Accounts">
    <x-page-header title="Accounts" subtitle="Money received from buyers, money spent on projects, and what is still due." >
        @can('accounts.create')<a href="{{ route('ws.expenses.index') }}#new-expense" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Record expense</a>@endcan
    </x-page-header>
    @include('ws.accounts._nav')
    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Received today" :value="\App\Support\Format::inrShort($today)" icon="rupee" />
        <x-stat label="Received this month" :value="\App\Support\Format::inrShort($month)" icon="receipt" tone="navy" />
        <x-stat label="Spent this month" :value="\App\Support\Format::inrShort($expMonth)" icon="doc" tone="gold" />
        <x-stat label="Still to collect" :value="\App\Support\Format::inrShort($receivable)" icon="clock" tone="navy" />
        <x-stat label="Overdue" :value="\App\Support\Format::inrShort($overdue)" icon="alert" :tone="$overdue > 0 ? 'red' : 'teal'" />
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card">
            <div class="flex items-center justify-between p-4"><h2 class="section-title">Latest receipts</h2><a href="{{ route('ws.accounts.receipts') }}" class="text-sm font-semibold">All receipts</a></div>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Receipt</th><th>Customer</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($recent as $p)
                    <tr><td><span class="font-mono text-xs font-semibold">{{ $p->transaction_no }}</span><p class="text-xs text-muted">@date($p->paid_on) · {{ \App\Models\Payment::MODES[$p->mode] ?? $p->mode }}</p></td>
                        <td>{{ $p->customer->name }}<p class="text-xs text-muted">Plot {{ $p->plot->plot_no }} · {{ $p->project->name }}</p></td>
                        <td class="num font-semibold text-teal-700">@inr($p->amount)</td></tr>
                @empty
                    <tr><td colspan="3"><x-empty title="No receipts yet" icon="receipt" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        <section class="card">
            <div class="flex items-center justify-between p-4"><h2 class="section-title">Latest expenses</h2><a href="{{ route('ws.expenses.index') }}" class="text-sm font-semibold">All expenses</a></div>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Expense</th><th>For</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($recentExp as $e)
                    <tr><td><span class="font-mono text-xs font-semibold">{{ $e->transaction_no }}</span><p class="text-xs text-muted">@date($e->spent_on) · {{ $e->category?->name }}</p></td>
                        <td>{{ $e->description }}<p class="text-xs text-muted">{{ $e->project?->name ?? 'General' }}{{ $e->vendor ? ' · '.$e->vendor : '' }}</p></td>
                        <td class="num font-semibold text-red-700">@inr($e->amount)</td></tr>
                @empty
                    <tr><td colspan="3"><x-empty title="No expenses recorded" icon="doc" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</x-layouts.workspace>
