<x-layouts.workspace title="New listing">
    <x-page-header title="What are you listing?" subtitle="Single properties that are not part of a layout. For a whole approved layout use Quick Launch." :back="route('ws.listings.index')">
        @can('quick_launch.create')<a href="{{ route('ws.quick-launch.create') }}" class="btn-light"><x-icon name="rocket" class="h-4 w-4" /> Quick launch a layout</a>@endcan
    </x-page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($types as $key => $t)
            <a href="{{ route('ws.listings.create', ['type' => $key]) }}" class="card card-pad flex items-start gap-4 text-navy no-underline transition hover:shadow-lift">
                <span class="rounded-xl bg-teal-50 p-3 text-teal-700"><x-icon :name="$t->icon()" class="h-6 w-6" /></span>
                <span>
                    <span class="block text-lg font-bold">{{ $t->label() }}</span>
                    <span class="text-sm text-muted">{{ count($t->purposes()) > 1 ? 'For sale or rent / lease' : 'For sale' }}{{ $t->requiresPatta() ? ' · patta / survey no. needed' : '' }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-layouts.workspace>
