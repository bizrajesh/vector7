@if ($paginator->hasPages())
<nav class="mt-4 flex items-center justify-between gap-2 text-sm" aria-label="Pagination">
    <p class="text-muted">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ \App\Support\Format::num($paginator->total()) }}</p>
    <div class="flex gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-light btn-sm opacity-50" aria-disabled="true">Previous</span>
        @else
            <a class="btn-light btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        @if ($paginator->hasMorePages())
            <a class="btn-light btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="btn-light btn-sm opacity-50" aria-disabled="true">Next</span>
        @endif
    </div>
</nav>
@endif
