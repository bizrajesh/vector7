<x-layouts.app title="Billing">
    <x-page-header title="Billing" subtitle="Plan, subscription status and invoices" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card-pad">
            <p class="kpi-label">Current plan</p>
            <p class="mt-1 text-2xl font-bold">{{ $tenant->plan?->name ?? '—' }}</p>
            <p class="mt-1"><x-status :status="$tenant->status" /></p>
            @php $sub = $tenant->subscription; @endphp
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <dt class="text-ink-muted">Billing cycle</dt><dd class="font-semibold">{{ ucfirst($sub?->cycle ?? '—') }}</dd>
                @if ($sub?->trial_ends_at)<dt class="text-ink-muted">Trial ends</dt><dd class="font-semibold">{{ $sub->trial_ends_at->format('j M Y') }}</dd>@endif
                @if ($sub?->current_period_end)<dt class="text-ink-muted">Paid until</dt><dd class="font-semibold">{{ $sub->current_period_end->format('j M Y') }}</dd>@endif
                @if ($sub?->grace_ends_at)<dt class="text-ink-muted">Grace ends</dt><dd class="font-semibold text-[#A12622]">{{ $sub->grace_ends_at->format('j M Y') }}</dd>@endif
            </dl>
            @if (auth()->user()->hasRole('admin'))
                <form method="POST" action="{{ route('app.billing.pay') }}" class="mt-5">@csrf
                    <button type="submit" class="btn-primary w-full">Pay securely online</button>
                </form>
                <p class="help">You will be redirected to Razorpay. Card and UPI details never reach Vector7.</p>
            @endif
        </div>
        <div class="card-pad lg:col-span-2">
            <h2 class="section-title mb-3">Invoices</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($tenant->invoices as $invoice)
                        <tr><td class="font-semibold">{{ $invoice->invoice_no }}</td><td>{{ $invoice->created_at->format('j M Y') }}</td><td class="num">@inr($invoice->total, true)</td>
                            <td><span class="{{ ['paid' => 'badge-av', 'due' => 'badge-bk', 'failed' => 'badge-od', 'void' => 'badge-rs'][$invoice->status] }}">{{ ucfirst($invoice->status) }}</span></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-ink-muted">No invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
