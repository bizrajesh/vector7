<x-layouts.workspace title="Notifications">
    <x-page-header title="Notifications" />
    <div class="card divide-y divide-navy-50">
        @forelse ($alerts as $a)
            <a href="{{ $a['link'] ?? '#' }}" class="flex items-center gap-3 p-4 text-navy no-underline hover:bg-page"><span class="dot {{ $a['level'] === 'red' ? 'bg-red-600' : ($a['level'] === 'amber' ? 'bg-amber-500' : 'bg-teal') }}"></span>{{ $a['text'] }}</a>
        @empty
            <x-empty title="You're all caught up" icon="bell" />
        @endforelse
    </div>
</x-layouts.workspace>
