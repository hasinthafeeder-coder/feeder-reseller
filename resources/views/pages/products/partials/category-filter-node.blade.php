@php
    $depth = $depth ?? 0;
    $children = $node->children ?? collect();
    $hasChildren = $children->isNotEmpty();
    $productCount = (int) ($node->product_count ?? 0);
    $indentStyle = $depth > 0 ? 'padding-left: ' . ($depth * 16) . 'px;' : '';
    $isRootGroupHeader = $depth === 0 && $hasChildren && $productCount === 0;
@endphp

@if ($isRootGroupHeader)
    <li class="mb-10">
        <button type="button"
            class="btn btn-link text-secondary p-0 text-decoration-none d-flex align-items-center gap-1"
            data-bs-toggle="collapse"
            data-bs-target="#filter-category-group-{{ $node->id }}"
            aria-expanded="true"
            aria-controls="filter-category-group-{{ $node->id }}">
            <span class="material-symbols-outlined fs-18">keyboard_arrow_down</span>
            <span class="fs-16 fw-medium text-secondary">{{ $node->name }}</span>
        </button>
        <div id="filter-category-group-{{ $node->id }}" class="collapse show">
            <ul class="p-0 list-unstyled mb-0 mt-2">
                @foreach ($children as $child)
                    @include('pages.products.partials.category-filter-node', [
                        'node' => $child,
                        'depth' => $depth + 1,
                        'filters' => $filters,
                    ])
                @endforeach
            </ul>
        </div>
    </li>
@else
    <li class="mb-10" @if ($indentStyle !== '') style="{{ $indentStyle }}" @endif>
        @if ($productCount > 0)
            <div class="d-flex align-items-center justify-content-between">
                <div class="form-check">
                    <input class="form-check-input rounded-circle border-border-color-50 position-relative"
                        type="checkbox" id="filter-category-{{ $node->id }}"
                        name="categories[]" value="{{ $node->id }}"
                        style="top: -2.4px;"
                        @checked(in_array($node->id, $filters['categories'], true))>
                    <label class="form-check-label fs-14 text-secondary"
                        for="filter-category-{{ $node->id }}">
                        {{ $node->name }}
                    </label>
                </div>
                <span class="fs-14 text-body">{{ $productCount }}</span>
            </div>
        @elseif ($hasChildren)
            <span class="fs-14 fw-medium text-secondary d-block mb-2">{{ $node->name }}</span>
        @endif

        @if ($hasChildren)
            <ul class="p-0 list-unstyled mb-0">
                @foreach ($children as $child)
                    @include('pages.products.partials.category-filter-node', [
                        'node' => $child,
                        'depth' => $depth + 1,
                        'filters' => $filters,
                    ])
                @endforeach
            </ul>
        @endif
    </li>
@endif
