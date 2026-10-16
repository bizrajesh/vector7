<x-layouts.public :seo="$seo">
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <div class="card card-pad max-w-2xl">
            <p class="badge-gray">{{ __('No longer available') }}</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight">{{ $listing->title }}</h1>
            <p class="mt-2 text-muted">{{ __($listing->purpose === 'rent' ? 'This :type in :place has been rented out. Here are similar properties that are still available.' : 'This :type in :place has been sold. Here are similar properties that are still available.', ['type' => $listing->typeLabel(), 'place' => $listing->location()]) }}</p>
            <a href="{{ \App\Services\PropertySearch::categoryUrl($listing->type, $listing->purpose, $listing->city) }}" class="btn-primary btn-pill mt-5">{{ __('See :what in :place', ['what' => __($listing->type()->plural()), 'place' => $listing->city]) }}</a>
        </div>
        @if ($similar->isNotEmpty())
            <h2 class="mt-10 text-2xl font-extrabold">{{ __('Similar properties') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach ($similar as $s)@include('partials.property-card', ['item' => $s, 'shortlisted' => []])@endforeach</div>
        @endif
    </section>
</x-layouts.public>
