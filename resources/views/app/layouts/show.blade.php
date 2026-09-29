@php
    $editable = $layout->isEditable();
    $canManage = auth()->user()->can('layouts.manage');
    $tabs = ['overview' => 'Overview', 'land' => 'Land & documents', 'stages' => 'Stages', 'facilities' => 'Facilities', 'estimate' => 'Estimate', 'shares' => 'Shareholders'];
@endphp
<x-layouts.app :title="$layout->name">
    <x-page-header :title="$layout->name" :subtitle="$layout->code.' · '.($layout->location ?? $layout->district ?? '')" :back="route('app.layouts.index')">
        <x-slot:actions>
            <x-status :status="$layout->status" />
            @if ($canManage)
                @if ($editable)
                    <a href="{{ route('app.layouts.edit', $layout) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="h-4 w-4" />Edit</a>
                    <form method="POST" action="{{ route('app.layouts.submit', $layout) }}" data-confirm="Submit this project? The estimate will be locked as the baseline and land details can no longer be edited.">@csrf
                        <button class="btn-primary btn-sm">Submit project</button>
                    </form>
                @elseif ($layout->status->value === 'ready_to_launch')
                    <a href="{{ route('app.plots.import', $layout) }}" class="btn-ghost btn-sm"><x-icon name="upload" class="h-4 w-4" />Import plots</a>
                    <form method="POST" action="{{ route('app.layouts.launch', $layout) }}" data-confirm="Launch this project and open plots for booking?">@csrf
                        <button class="btn-primary btn-sm">Launch</button>
                    </form>
                @elseif ($layout->status->value === 'launched')
                    <a href="{{ route('app.plots.index', $layout) }}" class="btn-primary btn-sm"><x-icon name="grid" class="h-4 w-4" />Plot catalogue</a>
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    <nav class="-mx-4 mb-5 flex gap-5 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0" aria-label="Project sections">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('app.layouts.show', ['layout' => $layout, 'tab' => $key]) }}" class="subtab {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @include('app.layouts.tabs.'.$tab)
</x-layouts.app>
