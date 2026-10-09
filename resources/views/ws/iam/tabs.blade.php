<nav class="tabs mb-6" aria-label="Users and roles">
    <a href="{{ route('ws.iam.index') }}" class="tab {{ request()->routeIs('ws.iam.*') ? 'active' : '' }}">Users</a>
    <a href="{{ route('ws.roles.index') }}" class="tab {{ request()->routeIs('ws.roles.*') ? 'active' : '' }}">Roles & permissions</a>
    <a href="{{ route('ws.groups.index') }}" class="tab {{ request()->routeIs('ws.groups.*') ? 'active' : '' }}">Notification groups</a>
</nav>
