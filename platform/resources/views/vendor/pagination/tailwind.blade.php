@if ($paginator->hasPages())
<nav class="pagination" role="navigation" aria-label="Pagination">
    <p>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
    <div class="pagination-links">
        @if ($paginator->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
        @foreach ($elements as $element)
            @if (is_string($element))<span aria-hidden="true">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
    </div>
</nav>
@endif
