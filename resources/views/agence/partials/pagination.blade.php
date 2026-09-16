<footer class="vehicle-pagination">
    <span>Affichage de {{ $paginator->firstItem() ?? 0 }} à {{ $paginator->lastItem() ?? 0 }} sur {{ $paginator->total() }} résultat{{ $paginator->total() > 1 ? 's' : '' }}</span>
    @if ($paginator->hasPages())
        <nav aria-label="Pagination">
            @if ($paginator->onFirstPage())<span aria-disabled="true"><x-icon name="arrow-left" /></span>@else<a href="{{ $paginator->previousPageUrl() }}"><x-icon name="arrow-left" /></a>@endif
            @foreach (range(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page)
                <a href="{{ $paginator->url($page) }}" @class(['active' => $page === $paginator->currentPage()])>{{ $page }}</a>
            @endforeach
            @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}"><x-icon name="arrow-right" /></a>@else<span aria-disabled="true"><x-icon name="arrow-right" /></span>@endif
        </nav>
    @endif
</footer>
