<x-layouts.workspace title="Help">
    <x-page-header title="Help" subtitle="How-to guides for every screen. You can also ask the assistant (chat button at the top) in plain words." />
    <form method="GET" class="card card-pad mb-6 flex gap-2" role="search">
        <label for="help_q" class="sr-only">Search help</label>
        <input id="help_q" name="q" value="{{ $q }}" class="input" placeholder="e.g. how do I record a payment?">
        <button class="btn-primary"><x-icon name="search" class="h-4 w-4" /> Search</button>
    </form>
    @if ($results !== null)
        <div class="card mb-6">
            <h2 class="section-title p-4">Results</h2>
            <ul class="divide-y divide-navy-50">
                @forelse ($results as $a)
                    <li class="p-4"><a href="{{ route('help.show', $a['slug']) }}" class="font-semibold">{{ $a['title'] }}</a><p class="text-sm text-muted">{{ \Illuminate\Support\Str::limit(strip_tags(\App\Services\HelpCenter::html($a)), 160) }}</p></li>
                @empty
                    <li class="p-4 text-sm text-muted">Nothing found. Try other words.</li>
                @endforelse
            </ul>
        </div>
    @endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($articles as $a)
            <a href="{{ route('help.show', $a['slug']) }}" class="card card-pad block text-navy no-underline hover:shadow-lift"><p class="font-bold">{{ $a['title'] }}</p><p class="mt-1 text-sm text-muted">{{ \Illuminate\Support\Str::limit(strip_tags(\App\Services\HelpCenter::html($a)), 110) }}</p></a>
        @endforeach
    </div>
</x-layouts.workspace>
