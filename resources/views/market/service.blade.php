<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <nav class="text-sm text-muted" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('market.services') }}">Services</a> / <span aria-current="page">{{ $service->name }}</span></nav>
        <h1 class="mt-3 text-4xl font-extrabold tracking-tight">{{ $service->name }}</h1>
        <p class="mt-3 text-2xl font-extrabold text-teal-700">{{ $service->priceLabel() }}</p>
        <div class="mt-6 whitespace-pre-line text-lg leading-relaxed">{{ $service->description }}</div>
        <div class="card card-pad mt-8">
            @if (auth('customer')->check() && in_array($service->available_to, ['customer', 'both']))
                <form method="POST" action="{{ route('account.services.request', $service) }}" class="space-y-3">@csrf
                    <x-textarea name="notes" label="Tell us what you need" rows="3" required />
                    <button class="btn-primary">Request this service</button>
                </form>
            @elseif ($service->available_to === 'tenant')
                <p>This service is for promoters. <a href="{{ route('staff.login') }}">Sign in to your workspace</a> and request it from Help desk.</p>
            @else
                <p><a href="{{ route('login') }}" class="font-semibold">Sign in</a> or <a href="{{ route('register') }}" class="font-semibold">create a free account</a> to request this service.</p>
            @endif
        </div>
    </div>
</x-layouts.public>
