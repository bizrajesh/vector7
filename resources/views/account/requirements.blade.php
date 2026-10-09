<x-layouts.account title="Saved requirements">
    <p class="mb-4 text-sm text-muted">We email you when a newly launched plot matches one of these. <a href="{{ route('market.requirement') }}">Add a requirement</a>.</p>
    <div class="space-y-3">
        @forelse ($requirements as $r)
            <div class="card card-pad flex flex-wrap items-start justify-between gap-3">
                <div><p class="font-semibold">“{{ $r->query_text }}”</p>
                    <div class="mt-2 flex flex-wrap gap-1">@foreach (\App\Services\RequirementSearch::describe($r->filters ?? []) as $chip)<span class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-800">{{ $chip }}</span>@endforeach</div>
                    <p class="mt-2 text-xs text-muted">Saved @date($r->created_at){{ $r->last_notified_at ? ' · last alert '.\App\Support\Format::date($r->last_notified_at) : '' }}</p></div>
                <div class="flex gap-2"><a href="{{ route('market.requirement', ['q' => $r->query_text]) }}" class="btn-light btn-sm">See matches</a>
                    <x-confirm :action="route('account.requirements.destroy', $r)" method="DELETE" message="Stop alerts for this requirement?" class="btn-ghost btn-sm">Remove</x-confirm></div>
            </div>
        @empty
            <div class="card"><x-empty title="No saved requirements" icon="bell" /></div>
        @endforelse
    </div>
</x-layouts.account>
