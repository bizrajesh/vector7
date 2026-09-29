<nav class="-mx-4 mb-5 flex gap-5 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0" aria-label="Settings sections">
    @foreach ([
        ['General', route('app.settings.edit'), request()->routeIs('app.settings.edit')],
        ['Stage groups', route('app.settings.stage-groups.index'), request()->routeIs('app.settings.stage-groups.*')],
        ['Facilities', route('app.settings.masters.index', 'facilities'), request()->is('app/settings/masters/facilities')],
        ['Brokers', route('app.settings.masters.index', 'brokers'), request()->is('app/settings/masters/brokers')],
        ['Document writers', route('app.settings.masters.index', 'document-writers'), request()->is('app/settings/masters/document-writers')],
        ['SRO offices', route('app.settings.masters.index', 'sub-registrar-offices'), request()->is('app/settings/masters/sub-registrar-offices')],
        ['Holidays', route('app.settings.masters.index', 'holidays'), request()->is('app/settings/masters/holidays')],
        ['Checklists', route('app.settings.checklists.index'), request()->routeIs('app.settings.checklists.*')],
        ['Notifications', route('app.settings.notifications.index'), request()->routeIs('app.settings.notifications.*')],
    ] as [$label, $url, $active])
        <a href="{{ $url }}" class="subtab {{ $active ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
