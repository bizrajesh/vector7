<x-layouts.account title="Shortlist & saved searches">
    <section>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="section-title">Shortlisted properties</h2>
            @if ($items->count() > 1)<a href="{{ route('market.compare', ['keys' => $items->pluck('key')->take(3)->implode(',')]) }}" class="btn-light btn-sm">Compare the first {{ min(3, $items->count()) }}</a>@endif
        </div>
        @if ($items->isEmpty())
            <div class="card card-pad mt-3 text-sm text-muted">Tap the star on any property to keep it here. Sold or unpublished properties drop off automatically.</div>
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)@include('partials.property-card', ['item' => $item, 'shortlisted' => [$item['key'] => true]])@endforeach
            </div>
        @endif
    </section>

    <section class="mt-10">
        <h2 class="section-title">Saved searches</h2>
        <div class="card mt-3">
            <ul class="divide-y divide-navy-50">
                @forelse ($searches as $s)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0"><a href="{{ $s->url() }}" class="font-semibold">{{ $s->query_text ?: $s->label() }}</a>
                            <p class="text-xs text-muted">{{ $s->label() }} · saved @date($s->created_at)@if ($s->last_notified_at) · last alert @date($s->last_notified_at)@endif</p></div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('account.searches.alerts', $s) }}">@csrf @method('PATCH')<button class="btn-light btn-sm">{{ $s->alerts ? 'Alerts on' : 'Alerts off' }}</button></form>
                            <form method="POST" action="{{ route('account.searches.destroy', $s) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm" aria-label="Delete saved search"><x-icon name="trash" class="h-4 w-4" /></button></form>
                        </div>
                    </li>
                @empty
                    <li class="p-4 text-sm text-muted">Use “Save search & alert me” on any results page to get an email when a new property matches.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="mt-10 max-w-2xl">
        <h2 class="section-title">Share your experience</h2>
        <form method="POST" action="{{ route('account.testimonial') }}" class="card card-pad mt-3 space-y-3">
            @csrf
            <p class="text-sm text-muted">Bought or rented through vector7? A few lines help other buyers. We publish it with your first name after a quick review.</p>
            <x-textarea name="body" label="Your testimonial" rows="4" required id="t_body" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="place" label="Your town" id="t_place" />
                <x-select name="rating" label="Rating" :options="[5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']" id="t_rating" />
            </div>
            <button class="btn-primary">Submit</button>
        </form>
    </section>
</x-layouts.account>
