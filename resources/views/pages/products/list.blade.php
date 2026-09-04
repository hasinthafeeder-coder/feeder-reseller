@extends('layout_main.app')

@php
    use Feeder\Core\Support\CurrencyDisplay;
    use Feeder\Core\Support\ResellerProductPricing;

    $suppliers = $suppliers ?? collect();
    $categories = $categories ?? collect();
    $supplierTypes = $supplierTypes ?? [];
    $products = $products ?? null;
    $filters = $filters ?? [
        'search' => '',
        'suppliers' => [],
        'categories' => [],
        'supplier_types' => [],
    ];
    $fallbackImage = asset('assets/images/product6.png');
@endphp

@push('styles')
    <style>
        .product-gallery {
            position: relative;
        }

        .product-gallery-track {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            touch-action: pan-x;
        }

        .product-gallery-track::-webkit-scrollbar {
            display: none;
        }

        .product-gallery-track.has-multiple {
            cursor: grab;
        }

        .product-gallery-track.has-multiple.is-dragging {
            cursor: grabbing;
            scroll-behavior: auto;
        }

        .product-gallery-slide {
            flex: 0 0 100%;
            max-width: 100%;
            scroll-snap-align: start;
            scroll-snap-stop: always;
        }

        .product-gallery-slide img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            display: block;
            user-select: none;
            -webkit-user-drag: none;
        }

        .product-gallery-dots {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 10px;
            z-index: 2;
            display: flex;
            justify-content: center;
            gap: 5px;
            pointer-events: none;
        }

        .product-gallery-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.55);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12);
        }

        .product-gallery-dots span.is-active {
            background: var(--bs-primary);
        }

        .product-card-badge {
            z-index: 2;
        }
    </style>
@endpush

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Products Grid</h3>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb align-items-center mb-0 lh-1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                            <i class="ri-home-8-line fs-15 text-primary me-1"></i>
                            <span class="text-body fs-14 hover">Dashboard</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span>E-Commerce</span>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Products Grid</span>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-4 col-lg-3">
                <div class="card bg-white rounded-10 border border-white p-20 mb-4">
                    <h3 class="mb-20">Filter</h3>
                    <form method="GET" action="{{ route('products.index') }}">
                        <span class="fs-16 fw-medium text-secondary d-block mb-2">Search</span>
                        <div class="table-src-form position-relative mx-0 mb-20">
                            <input type="text" class="form-control w-100" style="height: 40px;"
                                name="search" value="{{ $filters['search'] }}" placeholder="Search product...">
                            <div
                                class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                <span class="material-symbols-outlined">search</span>
                            </div>
                        </div>

                        <ul class="p-0 list-unstyled last-child-none mb-20 filter-check">
                            <li class="mb-10">
                                <span class="fs-16 fw-medium text-secondary">Supplier</span>
                            </li>
                            @foreach ($suppliers as $supplier)
                                <li class="mb-10">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="form-check">
                                            <input class="form-check-input rounded-circle border-border-color-50 position-relative"
                                                type="checkbox" id="filter-supplier-{{ $supplier['id'] }}"
                                                name="suppliers[]" value="{{ $supplier['id'] }}"
                                                style="top: -2.4px;"
                                                @checked(in_array($supplier['id'], $filters['suppliers'], true))>
                                            <label class="form-check-label fs-14 text-secondary"
                                                for="filter-supplier-{{ $supplier['id'] }}">
                                                {{ $supplier['name'] }}
                                            </label>
                                        </div>
                                        <span class="fs-14 text-body">{{ $supplier['count'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <ul class="p-0 list-unstyled last-child-none mb-20 filter-check last-child-border-none">
                            <li class="mb-10">
                                <span class="fs-16 fw-medium text-secondary">Category</span>
                            </li>
                            @foreach ($categories as $category)
                                @include('pages.products.partials.category-filter-node', [
                                    'node' => $category,
                                    'depth' => 0,
                                    'filters' => $filters,
                                ])
                            @endforeach
                        </ul>

                        <ul class="p-0 list-unstyled last-child-none mb-20 filter-check last-child-border-none">
                            <li class="mb-10">
                                <span class="fs-16 fw-medium text-secondary">Supplier Type</span>
                            </li>
                            @foreach ($supplierTypes as $supplierType)
                                <li class="mb-10">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="form-check">
                                            <input class="form-check-input rounded-circle border-border-color-50 position-relative"
                                                type="checkbox" id="filter-supplier-type-{{ $supplierType['value'] }}"
                                                name="supplier_types[]" value="{{ $supplierType['value'] }}"
                                                style="top: -2.4px;"
                                                @checked(in_array($supplierType['value'], $filters['supplier_types'], true))>
                                            <label class="form-check-label fs-14 text-secondary"
                                                for="filter-supplier-type-{{ $supplierType['value'] }}">
                                                {{ $supplierType['label'] }}
                                            </label>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary text-white">Filter</button>
                            <a href="{{ route('products.index') }}" class="btn btn-light border">Clear Filters</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-8 col-lg-9">
                <div class="row justify-content-center">
                    @forelse ($products as $product)
                        @php
                            $primaryVariant = $product->variants->first();
                            $productImages = $product->images
                                ->map(fn ($image) => $image->file_uuid
                                    ? route('files.thumbnail', ['uuid' => $image->file_uuid, 'size' => 'md'])
                                    : $fallbackImage)
                                ->filter()
                                ->values()
                                ->all();

                            if ($productImages === []) {
                                $productImages = [$fallbackImage];
                            }

                            $hasMultipleImages = count($productImages) > 1;
                            $stockQuantity = (int) ($product->total_stock ?? 0);
                            $isInStock = $stockQuantity > 0;
                            $isProSupplier = (bool) $product->supplier?->company?->isProSupplier();
                            $productCurrency = CurrencyDisplay::currencyFromMarket($product->market);
                            $priceLocked = (bool) $product->price_locked;
                            $resellerCost = $primaryVariant
                                ? ResellerProductPricing::resellerCost($primaryVariant)
                                : null;
                            $rating = 4;
                            $soldCount = 125;
                            $reviewCount = 24;
                        @endphp
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card bg-white rounded-10 border-0 mb-4">
                                <div class="position-relative">
                                    <div class="product-gallery rounded-10 overflow-hidden">
                                        <div class="product-gallery-track{{ $hasMultipleImages ? ' has-multiple' : '' }}"
                                            data-product-gallery="{{ $product->id }}">
                                            @foreach ($productImages as $imageUrl)
                                                <div class="product-gallery-slide">
                                                    <a href="{{ route('products.details', $product) }}" class="text-decoration-none d-block">
                                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    @if ($isProSupplier)
                                        <span
                                            class="badge bg-primary product-card-badge position-absolute top-0 start-0"
                                            style="margin: 12px;">PRO</span>
                                    @endif

                                    <span
                                        class="badge {{ $isInStock ? 'bg-success' : 'bg-danger' }} product-card-badge position-absolute top-0 end-0"
                                        style="margin: 12px;">
                                        @if ($isInStock)
                                            {{ $stockQuantity }} in stock
                                        @else
                                            Out of Stock
                                        @endif
                                    </span>

                                    @if ($hasMultipleImages)
                                        <div class="product-gallery-dots" data-gallery-dots="{{ $product->id }}">
                                            @foreach ($productImages as $imageIndex => $imageUrl)
                                                <span class="{{ $imageIndex === 0 ? 'is-active' : '' }}"></span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="p-20">
                                    <h3 class="mb-6">
                                        <a href="{{ route('products.details', $product) }}"
                                            class="text-decoration-none text-secondary hover-text">{{ $product->name }}</a>
                                    </h3>
                                    <span class="fs-14 text-body d-block mb-10">{{ $product->supplierCompanyName() }}</span>
                                    <div class="mb-12">
                                        @if ($primaryVariant)
                                            @if ($priceLocked)
                                                <span class="fs-14 text-body d-block mb-1">Selling Price</span>
                                                <h3 class="mb-1">
                                                    {{ CurrencyDisplay::formatAmount($productCurrency, $primaryVariant->selling_price) }}
                                                </h3>
                                            @else
                                                <span class="fs-14 text-body d-block mb-1">Suggested Price</span>
                                                <h3 class="mb-1">
                                                    {{ CurrencyDisplay::formatProductVariantListPrice(
                                                        $productCurrency,
                                                        false,
                                                        $primaryVariant->selling_price,
                                                        $primaryVariant->suggested_price,
                                                        $primaryVariant->suggested_price_min,
                                                        $primaryVariant->suggested_price_max,
                                                    ) }}
                                                </h3>
                                            @endif
                                            <span class="fs-14 text-body d-block mt-1">Cost {{ CurrencyDisplay::formatAmount($productCurrency, $resellerCost) }}</span>
                                            <span class="fs-14 text-body d-block">Commission: {{ ResellerProductPricing::formatCommission($productCurrency, $priceLocked, $primaryVariant) }}</span>
                                        @else
                                            <h3 class="mb-1">—</h3>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <span class="fs-14 text-secondary">{{ $soldCount }} sold</span>
                                        <div class="text-end">
                                            <div style="margin-top: -5px;">
                                                @for ($star = 1; $star <= 5; $star++)
                                                    @if ($star <= floor($rating))
                                                        <i class="ri-star-fill fs-16 text-warning"></i>
                                                    @elseif ($star - 0.5 <= $rating)
                                                        <i class="ri-star-half-fill fs-16 text-warning"></i>
                                                    @else
                                                        <i class="ri-star-line fs-16 text-warning"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                            <span class="fs-14 text-body">{{ $reviewCount }} reviews</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="card bg-white rounded-10 border-0 mb-4 p-20 text-center text-muted">
                                No products found.
                            </div>
                        </div>
                    @endforelse
                    @if ($products && $products->total() > 0)
                        <div class="col-lg-12">
                            @include('partials.pagination', [
                                'paginator' => $products,
                                'ariaLabel' => 'Products grid pagination',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var dragThreshold = 8;

            document.querySelectorAll('[data-product-gallery]').forEach(function (track) {
                var slides = track.querySelectorAll('.product-gallery-slide');

                if (slides.length < 2) {
                    return;
                }

                var galleryId = track.getAttribute('data-product-gallery');
                var dots = document.querySelectorAll('[data-gallery-dots="' + galleryId + '"] span');
                var isDragging = false;
                var didDrag = false;
                var startX = 0;
                var scrollStart = 0;

                function updateDots() {
                    var slideWidth = track.clientWidth;

                    if (slideWidth === 0) {
                        return;
                    }

                    var activeIndex = Math.round(track.scrollLeft / slideWidth);

                    dots.forEach(function (dot, index) {
                        dot.classList.toggle('is-active', index === activeIndex);
                    });
                }

                function snapToNearestSlide() {
                    var slideWidth = track.clientWidth;

                    if (slideWidth === 0) {
                        return;
                    }

                    var index = Math.round(track.scrollLeft / slideWidth);
                    track.scrollTo({
                        left: index * slideWidth,
                        behavior: 'smooth'
                    });
                }

                track.addEventListener('mousedown', function (event) {
                    isDragging = true;
                    didDrag = false;
                    startX = event.pageX;
                    scrollStart = track.scrollLeft;
                    track.classList.add('is-dragging');
                    event.preventDefault();
                });

                track.addEventListener('mousemove', function (event) {
                    if (!isDragging) {
                        return;
                    }

                    var distance = event.pageX - startX;

                    if (Math.abs(distance) > dragThreshold) {
                        didDrag = true;
                    }

                    track.scrollLeft = scrollStart - distance;
                    event.preventDefault();
                });

                function endDesktopDrag() {
                    if (!isDragging) {
                        return;
                    }

                    isDragging = false;
                    track.classList.remove('is-dragging');
                    snapToNearestSlide();
                }

                track.addEventListener('mouseup', endDesktopDrag);
                track.addEventListener('mouseleave', endDesktopDrag);

                track.addEventListener('click', function (event) {
                    if (didDrag) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                }, true);

                track.addEventListener('scroll', updateDots, { passive: true });
            });
        })();
    </script>
@endpush
