<x-layouts.public :seo="$seo">
    <section class="bg-white">
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ __('Tell us the plot you want') }}</h1>
            <p class="mt-3 text-lg text-muted">{{ __('Write it the way you\'d say it. We pick out the location, budget, size and facing, and show plots that fit.') }}</p>
            <form method="GET" action="{{ route('market.requirement') }}" class="mt-8">
                <label for="req_q" class="sr-only">{{ __('Your requirement') }}</label>
                <textarea id="req_q" name="q" rows="3" maxlength="500" class="input text-lg" placeholder="{{ __('1,200 sq ft east-facing plot near Thanjavur under ₹15 lakh') }}" required>{{ $q }}</textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button class="btn-primary btn-pill px-8 py-3"><x-icon name="sparkles" class="h-4 w-4" /> {{ __('Find plots') }}</button>
                    @foreach (['Corner plot in Vallam below 20 lakh', '5 cents north facing', 'Around 1500 sq ft under 25 lakhs'] as $ex)
                        <a href="{{ route('market.requirement', ['q' => $ex]) }}" class="rounded-full bg-page px-3 py-1.5 text-sm text-navy no-underline hover:bg-teal-50">{{ $ex }}</a>
                    @endforeach
                </div>
            </form>
        </div>
    </section>
    @if ($q !== '')
        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold">{{ __('Looking for:') }}</span>
                @forelse ($chips as $c)<span class="rounded-full bg-teal-50 px-3 py-1 text-sm font-semibold text-teal-800">{{ $c }}</span>@empty<span class="text-muted">{{ __('any plot (add a location, budget or size for better matches)') }}</span>@endforelse
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-lg font-bold">{{ trans_choice(':count matching plot|:count matching plots', $plots->count()) }}</p>
                @if (auth('customer')->check())
                    <form method="POST" action="{{ route('account.requirements.save') }}">@csrf
                        <input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="filters" value="{{ json_encode($filters) }}">
                        <button class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> {{ __('Email me new matches') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> {{ __('Sign in to get alerts for new matches') }}</a>
                @endif
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($plots as $plot)
                    <div>
                        <p class="mb-1 truncate text-xs font-semibold text-muted"><a href="{{ $plot->project->publicUrl() }}">{{ $plot->project->name }}</a> · {{ $plot->project->location }}</p>
                        @include('partials.plot-card', ['plot' => $plot])
                    </div>
                @empty
                    <div class="card sm:col-span-2 lg:col-span-4"><x-empty :title="__('Nothing matches yet')" icon="search">{{ __('Save this requirement and we will email you when a matching plot is launched.') }}</x-empty></div>
                @endforelse
            </div>
        </section>
    @endif
</x-layouts.public>
