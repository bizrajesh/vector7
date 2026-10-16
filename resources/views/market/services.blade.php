<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight">{{ __('Services') }}</h1>
        <p class="mt-2 max-w-2xl text-lg text-muted">{{ __('Practical help before and after you buy — from title checks to registration. Request a service from your account and our team will call you.') }}</p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <nav class="flex gap-2" aria-label="{{ __('Who is it for') }}">
                @foreach ([null => __('All services'), 'buyers' => __('For buyers'), 'promoters' => __('For promoters')] as $k => $label)
                    <a href="{{ route('market.services', array_filter(['for' => $k, 'q' => $q])) }}" class="chip no-underline {{ $for === ($k ?: null) ? 'bg-navy text-white' : '' }}" @if ($for === ($k ?: null)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('market.services') }}" class="ml-auto flex gap-2" role="search">
                @if ($for)<input type="hidden" name="for" value="{{ $for }}">@endif
                <label for="svc_q" class="sr-only">{{ __('Search services') }}</label>
                <input id="svc_q" name="q" value="{{ $q }}" class="input" placeholder="{{ __('e.g. survey, loan, legal') }}">
                <button class="btn-light">{{ __('Search') }}</button>
            </form>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($services as $s)
                <a href="{{ route('market.service', $s->slug) }}" class="card card-pad block text-navy no-underline transition hover:shadow-lift">
                    <p class="text-lg font-bold">{{ __($s->name) }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __($s->summary) }}</p>
                    <div class="mt-4 flex items-center justify-between"><span class="font-bold text-teal-700">{{ __($s->priceLabel()) }}</span><span class="text-xs text-muted">{{ ['tenant' => __('For promoters'), 'customer' => __('For buyers'), 'both' => __('Buyers & promoters')][$s->available_to] }}</span></div>
                </a>
            @empty
                <p class="text-muted">{{ __('No services match. Try another word or show all services.') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.public>
