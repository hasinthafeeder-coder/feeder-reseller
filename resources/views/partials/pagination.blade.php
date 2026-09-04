@php
    use Feeder\Core\Support\PaginationWindow;

    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $ariaLabel = $ariaLabel ?? 'Pagination';
    $pages = PaginationWindow::pages($paginator->currentPage(), $paginator->lastPage());
@endphp

@if ($paginator->total() > 0)
    <div
        class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap mb-4">
        <span class="fs-15">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }}
            of {{ $paginator->total() }} entries
        </span>

        @if ($paginator->hasPages())
            <nav class="custom-pagination" aria-label="{{ $ariaLabel }}">
                <ul class="pagination mb-0 justify-content-center">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled">
                            <span class="page-link icon" aria-hidden="true">
                                <i class="material-symbols-outlined">west</i>
                            </span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link icon" href="{{ $paginator->previousPageUrl() }}" aria-label="Previous page">
                                <i class="material-symbols-outlined">west</i>
                            </a>
                        </li>
                    @endif

                    @foreach ($pages as $page)
                        @if ($page === '...')
                            <li class="page-item disabled">
                                <span class="page-link">&hellip;</span>
                            </li>
                        @elseif ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link icon" href="{{ $paginator->nextPageUrl() }}" aria-label="Next page">
                                <i class="material-symbols-outlined">east</i>
                            </a>
                        </li>
                    @else
                        <li class="page-item disabled">
                            <span class="page-link icon" aria-hidden="true">
                                <i class="material-symbols-outlined">east</i>
                            </span>
                        </li>
                    @endif
                </ul>
            </nav>
        @endif
    </div>
@endif
