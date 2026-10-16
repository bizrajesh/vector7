<nav class="tabs mb-6" aria-label="Plans">
    <a href="{{ route('app.plans.index') }}" class="tab {{ request()->routeIs('app.plans.*') ? 'active' : '' }}">Plans</a>
    <a href="{{ route('app.topups.index') }}" class="tab {{ request()->routeIs('app.topups.*') ? 'active' : '' }}">Top-up packs</a>
    <a href="{{ route('app.contact-charges.index') }}" class="tab {{ request()->routeIs('app.contact-charges.*') ? 'active' : '' }}">Contact view charges</a>
</nav>
