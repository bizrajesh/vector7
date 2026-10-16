<x-layouts.account :title="__('Shortlist & saved searches')">
    <section>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="section-title">{{ __('Shortlisted properties') }}</h2>
            @if ($items->count() > 1)<a href="{{ route('market.compare', ['keys' => $items->pluck('key')->take(3)->implode(',')]) }}" class="btn-light btn-sm">{{ __('Compare the first :n', ['n' => min(3, $items->count())]) }}</a>@endif
        </div>
        @if ($items->isEmpty())
            <div class="card card-pad mt-3 text-sm text-muted">{{ __('Tap the star on any property to keep it here. Sold or unpublished properties drop off automatically.') }}</div>
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)@include('partials.property-card', ['item' => $item, 'shortlisted' => [$item['key'] => true]])@endforeach
            </div>
        @endif
    </section>

    <section class="mt-10">
        <h2 class="section-title">{{ __('Saved searches') }}</h2>
        <div class="card mt-3">
            <ul class="divide-y divide-navy-50">
                @forelse ($searches as $s)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0"><a href="{{ $s->url() }}" class="font-semibold">{{ $s->query_text ?: $s->label() }}</a>
                            <p class="text-xs text-muted">{{ $s->label() }} · {{ __('saved :date', ['date' => \App\Support\Format::date($s->created_at)]) }}@if ($s->last_notified_at) · {{ __('last alert :date', ['date' => \App\Support\Format::date($s->last_notified_at)]) }}@endif</p></div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('account.searches.alerts', $s) }}">@csrf @method('PATCH')<button class="btn-light btn-sm">{{ $s->alerts ? __('Alerts on') : __('Alerts off') }}</button></form>
                            <form method="POST" action="{{ route('account.searches.destroy', $s) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm" aria-label="{{ __('Delete saved search') }}"><x-icon name="trash" class="h-4 w-4" /></button></form>
                        </div>
                    </li>
                @empty
                    <li class="p-4 text-sm text-muted">{{ __('Use “Save search & alert me” on any results page to get an email when a new property matches.') }}</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="mt-10 max-w-2xl">
        <h2 class="section-title">{{ __('Share your experience') }}</h2>
        <form method="POST" action="{{ route('account.testimonial') }}" class="card card-pad mt-3 space-y-3">
            @csrf
            <p class="text-sm text-muted">{{ __('Bought or rented through vector7? A few lines help other buyers. We publish it with your first name after a quick review.') }}</p>
            <x-textarea name="body" :label="__('Your testimonial')" rows="4" required id="t_body" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="place" :label="__('Your town')" id="t_place" />
                <x-select name="rating" :label="__('Rating')" :options="[5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']" id="t_rating" />
            </div>
            <button class="btn-primary">{{ __('Submit') }}</button>
        </form>
    </section>
</x-layouts.account>
