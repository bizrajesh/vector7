<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight">Services</h1>
        <p class="mt-2 max-w-2xl text-lg text-muted">Practical help before and after you buy — from title checks to registration. Request a service from your account and our team will call you.</p>
        <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $s)
                <a href="{{ route('market.service', $s->slug) }}" class="card card-pad block text-navy no-underline transition hover:shadow-lift">
                    <p class="text-lg font-bold">{{ $s->name }}</p>
                    <p class="mt-2 text-sm text-muted">{{ $s->summary }}</p>
                    <div class="mt-4 flex items-center justify-between"><span class="font-bold text-teal-700">{{ $s->priceLabel() }}</span><span class="text-xs text-muted">{{ ['tenant' => 'For promoters', 'customer' => 'For buyers', 'both' => 'Buyers & promoters'][$s->available_to] }}</span></div>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.public>
