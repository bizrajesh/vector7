<x-layouts.workspace title="Conversation">
    <x-page-header :title="$c->title ?: 'Conversation'" :subtitle="($c->scope === 'market' ? 'Marketplace bot' : 'Workspace bot').' · '.($c->customer?->name ?? $c->user?->name ?? 'Visitor').($c->tenant ? ' · '.$c->tenant->name : '')" :back="route('app.chats.index')" />
    <div class="card card-pad">
        <ol class="space-y-3">
            @foreach ($c->messages as $m)
                <li class="rounded-xl p-3 {{ $m->role === 'user' ? 'bg-page' : 'bg-teal-50' }}">
                    <p class="text-xs text-muted"><strong class="text-navy">{{ $m->role === 'user' ? 'Visitor / user' : 'Bot' }}</strong> · {{ \App\Support\Format::datetime($m->created_at) }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ $m->content }}</p>
                    @if (! empty($m->meta['tools']))<p class="mt-1 text-xs text-muted">Tools used: {{ implode(', ', $m->meta['tools']) }}</p>@endif
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.workspace>
