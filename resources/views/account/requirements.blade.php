<x-layouts.account :title="__('Saved requirements')">
    <p class="mb-4 text-sm text-muted">{{ __('We email you when a newly launched plot matches one of these.') }} <a href="{{ route('market.requirement') }}">{{ __('Add a requirement') }}</a>.</p>
    <div class="space-y-3">
        @forelse ($requirements as $r)
            <div class="card card-pad flex flex-wrap items-start justify-between gap-3">
                <div><p class="font-semibold">“{{ $r->query_text }}”</p>
                    <div class="mt-2 flex flex-wrap gap-1">@foreach (\App\Services\RequirementSearch::describe($r->filters ?? []) as $chip)<span class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-800">{{ $chip }}</span>@endforeach</div>
                    <p class="mt-2 text-xs text-muted">{{ __('Saved :date', ['date' => \App\Support\Format::date($r->created_at)]) }}{{ $r->last_notified_at ? ' · '.__('last alert :date', ['date' => \App\Support\Format::date($r->last_notified_at)]) : '' }}</p></div>
                <div class="flex gap-2"><a href="{{ route('market.requirement', ['q' => $r->query_text]) }}" class="btn-light btn-sm">{{ __('See matches') }}</a>
                    <x-confirm :action="route('account.requirements.destroy', $r)" method="DELETE" :message="__('Stop alerts for this requirement?')" class="btn-ghost btn-sm">{{ __('Remove') }}</x-confirm></div>
            </div>
        @empty
            <div class="card"><x-empty :title="__('No saved requirements')" icon="bell" /></div>
        @endforelse
    </div>
</x-layouts.account>
