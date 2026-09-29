<x-layouts.app title="Layout Projects">
    <x-page-header title="{{ request('status') === 'in_progress' ? 'Operations' : 'Layout Projects' }}" subtitle="From land to launch">
        <x-slot:actions>
            @can('layouts.manage')<a href="{{ route('app.layouts.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" stroke="2.2" />New layout</a>@endcan
        </x-slot:actions>
    </x-page-header>
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <a href="{{ route('app.layouts.index') }}" class="pill {{ ! request('status') ? 'active' : '' }}">All</a>
        @foreach (\App\Enums\LayoutStatus::cases() as $status)
            <a href="{{ route('app.layouts.index', ['status' => $status->value]) }}" class="pill {{ request('status') === $status->value ? 'active' : '' }}">{{ $status->label() }}</a>
        @endforeach
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($layouts as $layout)
            @php
                $mandatory = $layout->stages->where('is_mandatory', true);
                $progress = $mandatory->count() ? round($mandatory->where('status', 'completed')->count() / $mandatory->count() * 100) : 0;
            @endphp
            <article class="card-pad flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div><h2 class="font-bold"><a href="{{ route('app.layouts.show', $layout) }}" class="text-ink hover:text-teal">{{ $layout->name }}</a></h2><p class="text-xs text-ink-muted">{{ $layout->code }} · {{ $layout->district ?? $layout->location ?? '—' }}</p></div>
                    <x-status :status="$layout->status" />
                </div>
                <div class="flex items-center gap-2 text-xs text-ink-muted"><span class="progress flex-1"><span style="width: {{ $progress }}%"></span></span>{{ $progress }}% stages</div>
                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <div><dt class="text-xs text-ink-muted">Area</dt><dd class="num font-semibold">{{ number_format((float) $layout->total_sqft) }} ft²</dd></div>
                    <div><dt class="text-xs text-ink-muted">Est. cost</dt><dd class="num font-semibold">@inrShort($layout->latestEstimate?->production_value ?? 0)</dd></div>
                    <div><dt class="text-xs text-ink-muted">Plots sold</dt><dd class="num font-semibold">{{ $layout->sold_count }} / {{ $layout->plots_count }}</dd></div>
                </dl>
                <div class="mt-auto flex gap-2">
                    <a href="{{ route('app.layouts.show', $layout) }}" class="btn-ghost btn-sm flex-1">Open</a>
                    @if (in_array($layout->status->value, ['ready_to_launch', 'launched']))
                        <a href="{{ route('app.plots.index', $layout) }}" class="btn-primary btn-sm flex-1">Plots</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No layouts here yet" icon="map" /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $layouts->links() }}</div>
</x-layouts.app>
