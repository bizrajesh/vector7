<nav class="tabs mb-6" aria-label="Accounts">
    @foreach (['ws.accounts.index' => 'Overview', 'ws.accounts.receipts' => 'Receipts', 'ws.expenses.index' => 'Expenses', 'ws.accounts.budget' => 'Budget vs actual', 'ws.accounts.daybook' => 'Day book', 'ws.accounts.receivables' => 'Receivables'] as $r => $l)
        <a href="{{ route($r) }}" class="tab {{ request()->routeIs($r) ? 'active' : '' }}" @if (request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
