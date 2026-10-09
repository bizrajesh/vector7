<x-layouts.account title="Documents">
    <div class="space-y-4">
        @foreach ($registrations as $r)
            <div class="card card-pad">
                <p class="font-extrabold">Plot {{ $r->plot->plot_no }} · {{ $r->project->name }}</p>
                <p class="text-sm text-muted">Registration {{ \App\Models\Registration::STATUSES[$r->status] }}@if ($r->registered_doc_no) · Document no. {{ $r->registered_doc_no }} dated @date($r->registered_doc_date)@endif</p>
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($files[$r->id] ?? [] as $f)<li><a href="{{ route('account.file', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a> <span class="text-xs text-muted">{{ $f->sizeLabel() }}</span></li>@empty<li class="text-muted">No documents shared yet.</li>@endforelse
                </ul>
            </div>
        @endforeach
        <div class="card card-pad">
            <p class="font-extrabold">Payment receipts</p>
            <ul class="mt-3 space-y-1 text-sm">@forelse ($payments as $p)<li><a href="{{ route('account.receipt', $p->id) }}" target="_blank" rel="noopener">Receipt {{ $p->receipt?->receipt_no }}</a> — @inr($p->amount), @date($p->paid_on), Plot {{ $p->plot->plot_no }}</li>@empty<li class="text-muted">No receipts yet.</li>@endforelse</ul>
        </div>
    </div>
</x-layouts.account>
