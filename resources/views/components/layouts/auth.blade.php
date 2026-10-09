@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-screen">
<a href="#main" class="skip-link">Skip to content</a>
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <a href="{{ route('home') }}" class="mb-8 no-underline" aria-label="vector7 home"><x-logo class="h-14 w-auto" /></a>
    <main id="main" class="w-full {{ $wide ?? false ? 'max-w-2xl' : 'max-w-md' }}">
        <div class="card card-pad sm:p-8">
            <x-flash />
            <div class="{{ session()->hasAny(['ok','error','warn']) || $errors->any() ? 'mt-4' : '' }}">{{ $slot }}</div>
        </div>
        @isset($below)<div class="mt-6 text-center text-sm text-muted">{{ $below }}</div>@endisset
    </main>
    <p class="mt-10 text-xs text-muted">© {{ date('Y') }} vector7 · <a href="{{ route('market.privacy') }}">Privacy</a> · <a href="{{ route('market.terms') }}">Terms</a></p>
</div>
</body>
</html>
