<x-layouts.workspace :title="$article['title'].' — Help'">
    <x-page-header :title="$article['title']" :back="route('help.index')">
        @if ($url = \App\Services\HelpCenter::screenUrl($article))<a href="{{ $url }}" class="btn-primary">Open this screen</a>@endif
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-4">
        <article class="card card-pad prose-v7 min-w-0 xl:col-span-3">{!! $html !!}</article>
        <nav class="card card-pad h-fit text-sm" aria-label="Help topics">
            <p class="section-title mb-2">Topics</p>
            <ul class="space-y-1">@foreach ($articles as $a)<li><a href="{{ route('help.show', $a['slug']) }}" class="{{ $a['slug'] === $article['slug'] ? 'font-bold text-navy' : '' }}">{{ $a['title'] }}</a></li>@endforeach</ul>
        </nav>
    </div>
</x-layouts.workspace>
