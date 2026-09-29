@php $tabs = [['My Plot', 'map', route('portal.customer'), true]]; @endphp
<x-layouts.portal title="My plot" :tabs="$tabs">
    <header class="flex items-center justify-between border-b border-line bg-white px-5 py-3.5">
        <div><p class="text-[12.5px] text-ink-muted">Welcome back</p><p class="text-lg font-bold">{{ $customer->name }}</p></div>
        <span class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-md bg-sage text-[10px] font-extrabold tracking-tight text-navy">V7</span><span class="text-xs font-semibold text-ink-muted">{{ $currentTenant->name ?? '' }}</span></span>
    </header>
    <div class="flex flex-col gap-3.5 px-4 py-4">
        @foreach ($bookings as $b)
            <div class="card-pad anim-up flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div><p class="text-lg font-bold">Plot {{ $b->plot->plot_no }}</p><p class="text-[12.5px] text-ink-muted">{{ $b->plot->layout->name }} · {{ number_format((float) $b->plot->size_sqft) }} sqft · @inr($b->plot->cost)</p></div>
                    <span class="{{ $b->isPending() ? 'badge-bk' : 'badge-av' }}">{{ $b->isPending() ? 'Held online' : 'Booked' }}</span>
                </div>
                @if ($b->isPending())
                    <div class="flex items-start gap-2.5 rounded-xl bg-[#FBEFD5] px-3.5 py-3 text-[13px] text-[#5E3D00]">
                        <x-icon name="clock" class="mt-0.5 h-[18px] w-[18px] shrink-0" stroke="2" />
                        <span>Held until <strong>{{ $b->expires_at->format('j M, h:i A') }}</strong> ({{ $b->hoursLeft() }} hours left). Our sales team will call you to collect the booking advance.</span>
                    </div>
                @else
                    <p class="text-sm text-ink-2">Booking confirmed · advance @inr($b->amount) · valid till {{ $b->expires_at->format('j M Y') }}</p>
                @endif
                @if (in_array($b->id, $openRequests, true))
                    <p class="flex items-center gap-2 text-sm font-semibold text-teal"><x-icon name="check" class="h-4 w-4" stroke="2.4" />Purchase request sent — the sales team will contact you.</p>
                @else
                    <form method="POST" action="{{ route('portal.customer.purchase') }}" class="flex flex-col gap-2">@csrf
                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                        <label for="msg-{{ $b->id }}" class="sr-only">Message to sales (optional)</label>
                        <input id="msg-{{ $b->id }}" name="message" class="input" placeholder="Best time to call you (optional)">
                        <button class="btn-primary">I want to buy this plot</button>
                    </form>
                @endif
                @if ($salesContact?->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $salesContact->phone) }}" class="btn-ghost"><x-icon name="phone" class="h-4 w-4" />Call sales · {{ $salesContact->phone }}</a>
                @endif
            </div>
        @endforeach

        @forelse ($sales as $sale)
            @php
                $steps = ['Booked', 'Paying', 'Ready to register', 'Registration', 'Sold'];
                $current = ['ongoing_sale' => 1, 'ror' => 2, 'ongoing_reg' => 3, 'sold' => 4][$sale->plot->status->value] ?? 1;
            @endphp
            <div class="card-pad flex flex-col gap-3.5">
                <div class="flex items-start justify-between"><div><p class="text-xl font-bold">Plot {{ $sale->plot->plot_no }}</p><p class="text-[12.5px] text-ink-muted">{{ $sale->plot->layout->name }} · {{ number_format((float) $sale->plot->size_sqft) }} sqft</p></div><x-status :status="$sale->plot->status" /></div>
                <ol class="grid grid-cols-5 gap-1" aria-label="Progress">
                    @foreach ($steps as $i => $label)
                        <li class="flex flex-col items-center gap-1.5 text-center text-[10.5px] font-semibold {{ $i === $current ? 'text-[#1D4F91]' : 'text-ink-muted' }}" @if ($i === $current) aria-current="step" @endif>
                            @if ($i < $current || $sale->plot->status->value === 'sold')
                                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-full bg-teal text-white"><x-icon name="check" class="h-3.5 w-3.5" stroke="3" /></span>
                            @elseif ($i === $current)
                                <span class="h-[26px] w-[26px] rounded-full border-[3px] border-[#2F6DB5] bg-white"></span>
                            @else
                                <span class="h-[26px] w-[26px] rounded-full border-2 border-[#D6D2BD] bg-white"></span>
                            @endif
                            {{ $label }}
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="card-pad flex flex-col gap-3">
                <div class="flex items-baseline justify-between"><h2 class="text-[15px] font-bold">Payments</h2><span class="num text-[12.5px] text-ink-muted">@inr($sale->paid_amount) of @inr($sale->sale_value)</span></div>
                <div class="progress h-2"><span style="width: {{ $sale->sale_value > 0 ? min(100, round($sale->paid_amount / $sale->sale_value * 100)) : 0 }}%"></span></div>
                @foreach ($sale->instalments as $i)
                    @php $next = $i->status !== 'paid' && $sale->instalments->where('status', '!=', 'paid')->first()?->is($i); @endphp
                    <div class="flex items-center gap-3 {{ $next ? '-mx-1 rounded-xl border border-[#F1D9A6] bg-[#FDF6E7] px-3 py-2.5' : '' }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] {{ $i->status === 'paid' ? 'bg-[#E3F2EA] text-[#1F6B45]' : ($next ? 'bg-[#FBEFD5] text-[#7A4F00]' : 'bg-[#F0EEE3] text-ink-muted') }}"><x-icon :name="$i->status === 'paid' ? 'check' : 'clock'" class="h-[18px] w-[18px]" stroke="2.2" /></span>
                        <span class="flex-1"><span class="block text-sm font-semibold">{{ $i->seq === 1 ? '1st' : ($i->seq === 2 ? '2nd' : 'Final') }} instalment · {{ (float) $i->pct }}%</span>
                            <span class="text-xs {{ $next ? 'font-semibold text-[#7A4F00]' : 'text-ink-muted' }}">{{ $i->status === 'paid' ? 'Paid' : 'Due '.$i->due_date->format('j M') }}</span></span>
                        <span class="num text-sm font-bold">@inr($i->amount)</span>
                    </div>
                @endforeach
            </div>

            <div class="card">
                @foreach ($sale->payments as $p)
                    <a href="{{ route('portal.customer.receipt', $p) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:text-ink">
                        <x-icon name="doc" class="h-5 w-5 text-ink-muted" /><span class="flex-1 text-sm font-semibold">Receipt {{ $p->receipt_no }}</span><span class="num text-xs text-ink-muted">@inr($p->amount)</span>
                    </a>
                @endforeach
            </div>
        @empty
            @if ($bookings->isEmpty())
                <div class="card"><x-empty title="No plots yet" icon="map">Your plot and payments will appear here after booking. <a href="{{ route('projects.index') }}">Browse projects</a></x-empty></div>
            @endif
        @endforelse
    </div>
</x-layouts.portal>
