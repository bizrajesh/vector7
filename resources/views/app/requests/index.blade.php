<x-layouts.app title="Requests">
    <x-page-header title="Purchase requests" subtitle="From the website and the customer portal — purchases are completed by the sales team" />
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'] as $key => $label)
            <a href="{{ route('app.requests.index', ['status' => $key]) }}" class="pill {{ request('status', 'new') === $key ? 'active' : '' }}">{{ $label }} {{ $counts[$key] ?? 0 }}</a>
        @endforeach
    </div>
    <div class="flex flex-col gap-3">
        @forelse ($requests as $r)
            <article class="card-pad flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $r->name }}
                        <span class="{{ $r->type === 'purchase' ? 'badge-av' : 'badge-os' }}">{{ $r->type === 'purchase' ? 'Wants to buy' : 'Call back' }}</span>
                        <span class="badge-rs">{{ ucfirst($r->source) }}</span>
                        @if ($r->booking?->status === 'pending')<span class="badge-bk">Holding online</span>@endif
                    </p>
                    <p class="text-sm text-ink-2"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $r->phone) }}" class="font-semibold">{{ $r->phone }}</a> {{ $r->email ? '· '.$r->email : '' }}</p>
                    <p class="text-sm text-ink-muted">{{ $r->layout?->name }} {{ $r->plot ? '· Plot '.$r->plot->plot_no : '' }} · {{ $r->created_at->diffForHumans() }}</p>
                    @if ($r->message)<p class="mt-1 text-sm">“{{ $r->message }}”</p>@endif
                    @if ($r->handler)<p class="mt-1 text-xs text-ink-muted">{{ ucfirst($r->status) }} by {{ $r->handler->name }} {{ $r->handled_at?->diffForHumans() }} {{ $r->notes ? '· '.$r->notes : '' }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($r->plot)
                        <a href="{{ route('app.plots.show', $r->plot) }}" class="btn-ghost btn-sm">Open plot</a>
                        @if (in_array($r->plot->status->value, ['available', 'booked']))<a href="{{ route('app.sales.create', $r->plot) }}" class="btn-primary btn-sm">Start sale</a>@endif
                    @endif
                    @if ($r->status !== 'closed')
                        <form method="POST" action="{{ route('app.requests.update', $r) }}" class="flex gap-2">@csrf @method('PUT')
                            <input type="hidden" name="status" value="{{ $r->status === 'new' ? 'contacted' : 'closed' }}">
                            <input name="notes" class="input min-h-[36px] w-40 py-1 text-sm" placeholder="Note" aria-label="Note">
                            <button class="btn-outline btn-sm">{{ $r->status === 'new' ? 'Mark contacted' : 'Close' }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="card"><x-empty title="No requests here" icon="bell">New website and portal requests appear here and are sent to your notification groups.</x-empty></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.app>
