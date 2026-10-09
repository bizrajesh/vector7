@props(['title' => 'My account'])
@php($c = auth('customer')->user())
<x-layouts.public :title="$title" :seo="['title' => $title, 'noindex' => true]">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-sm text-muted">Signed in as {{ $c->email }}</p><h1 class="text-3xl font-extrabold tracking-tight">{{ $title }}</h1></div>
            <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="logout" class="h-4 w-4" /> Sign out</button></form>
        </div>
        <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
            <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Account">
                @foreach ([['Overview', 'account.dashboard', 'home'], ['Bookings', 'account.bookings', 'calendar'], ['Purchases', 'account.purchases', 'cart'], ['Payments & receipts', 'account.payments', 'receipt'], ['Documents', 'account.documents', 'doc'], ['Saved requirements', 'account.requirements', 'bell'], ['Enquiries', 'account.enquiries', 'chat'], ['Help & tickets', 'account.tickets', 'lifebuoy'], ['Profile', 'account.profile', 'user']] as [$l, $r, $i])
                    @php($on = request()->routeIs($r) || request()->routeIs($r.'.*'))
                    <a href="{{ route($r) }}" class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium no-underline {{ $on ? 'bg-navy text-white' : 'text-navy hover:bg-white' }}" @if ($on) aria-current="page" @endif><x-icon :name="$i" class="h-4 w-4" />{{ $l }}</a>
                @endforeach
            </nav>
            <div class="min-w-0">{{ $slot }}</div>
        </div>
    </div>
</x-layouts.public>
