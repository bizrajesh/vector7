<x-layouts.public :seo="$seo">
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <div class="card card-pad max-w-2xl">
            <p class="badge-gray">No longer available</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight">{{ $listing->title }}</h1>
            <p class="mt-2 text-muted">This {{ strtolower($listing->typeLabel()) }} in {{ $listing->location() }} has been {{ $listing->purpose === 'rent' ? 'rented out' : 'sold' }}. Here are similar properties that are still available.</p>
            <a href="{{ \App\Services\PropertySearch::categoryUrl($listing->type, $listing->purpose, $listing->city) }}" class="btn-primary btn-pill mt-5">See {{ strtolower($listing->type()->plural()) }} in {{ $listing->city }}</a>
        </div>
        @if ($similar->isNotEmpty())
            <h2 class="mt-10 text-2xl font-extrabold">Similar properties</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach ($similar as $s)@include('partials.property-card', ['item' => $s, 'shortlisted' => []])@endforeach</div>
        @endif
    </section>
</x-layouts.public>
