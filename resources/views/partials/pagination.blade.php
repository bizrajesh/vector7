@if ($paginator->hasPages())
<nav class="mt-4 flex items-center justify-between gap-2 text-sm" aria-label="{{ __('Pagination') }}">
    <p class="text-muted">{{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => \App\Support\Format::num($paginator->total())]) }}</p>
    <div class="flex gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-light btn-sm opacity-50" aria-disabled="true">{{ __('Previous') }}</span>
        @else
            <a class="btn-light btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
        @endif
        @if ($paginator->hasMorePages())
            <a class="btn-light btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
        @else
            <span class="btn-light btn-sm opacity-50" aria-disabled="true">{{ __('Next') }}</span>
        @endif
    </div>
</nav>
@endif
