@extends('layout_main.app')

@php
    use Feeder\Core\Support\CurrencyDisplay;

    $product = $product ?? null;
    $productCurrency = $productCurrency ?? null;
    $priceLocked = (bool) ($priceLocked ?? false);
    $isProSupplier = (bool) ($isProSupplier ?? false);
    $productLanguages = $productLanguages ?? [];
    $images = $images ?? [];
    $fallbackImage = $fallbackImage ?? asset('assets/images/product6.png');
    $variants = $variants ?? [];
    $defaultVariantKey = $defaultVariantKey ?? array_key_first($variants);
    $defaultVariant = $defaultVariantKey !== null ? $variants[$defaultVariantKey] ?? null : null;

    $productName = $product?->name ?? '—';
    $supplierName = $product?->supplierCompanyName() ?? '—';
    $category = $product?->category?->name ?? '—';
    $marketCountry = $product?->market?->country?->name ?? '—';
    $currencyCode = $productCurrency?->iso_code ?? '—';
    $currencyDecimals = (int) ($productCurrency?->decimal_places ?? 2);

    $soldCount = $soldCount ?? 125;
    $reviewCount = $reviewCount ?? 24;
    $rating = $rating ?? 4.8;

    $formatAmount = static fn(float|int|null $amount): string => CurrencyDisplay::formatAmount(
        $productCurrency,
        $amount,
    );
    $formatRange = static fn(float|int|null $min, float|int|null $max): string => sprintf(
        '%s — %s',
        CurrencyDisplay::formatAmount($productCurrency, $min),
        CurrencyDisplay::formatAmount($productCurrency, $max),
    );

    $displayStock = (int) ($defaultVariant['stock'] ?? 0);
    $stockLabel = $displayStock > 0 ? $displayStock . ' units' : 'Out of Stock';
    $stockBadgeClass =
        $displayStock > 0
            ? 'bg-success-subtle text-success border border-success border-opacity-10'
            : 'bg-danger-subtle text-danger border border-danger border-opacity-10';

    $supplierCompany = $product?->supplier?->company;
    $supplierInfo = [
        'company' => $supplierCompany?->name ?? '—',
        'type' => $isProSupplier ? 'PRO' : 'Standard',
        'category_focus' => $category,
        'market' => $marketCountry,
        'member_since' => optional($supplierCompany?->created_at)->format('M Y') ?? '—',
    ];
@endphp

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 mt-1">
            <h3 class="mb-0">Product Details</h3>

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
                        <a href="{{ route('products.index') }}" class="text-decoration-none"><span>Products Grid</span></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span class="text-secondary">Product Details</span>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <div class="row g-4 align-items-start">
                <div class="col-lg-6">
                    <div class="border rounded-10 p-2 bg-light-subtle">
                        <div class="tab-content mb-3" id="productGalleryTabs">
                            @foreach ($images as $index => $imageUrl)
                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                                    id="gallery-{{ $index + 1 }}" role="tabpanel">
                                    <img src="{{ $imageUrl }}" class="rounded-10 w-100 product-gallery-main-image"
                                        alt="{{ $productName }} image {{ $index + 1 }}">
                                </div>
                            @endforeach
                        </div>

                        <ul class="nav nav-tabs border-0 m-0 product-gallery-thumbs" id="productGalleryThumbs"
                            role="tablist">
                            @foreach ($images as $index => $imageUrl)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link p-0 border-0 {{ $index === 0 ? 'active' : '' }}"
                                        id="gallery-thumb-{{ $index + 1 }}" data-bs-toggle="tab"
                                        data-bs-target="#gallery-{{ $index + 1 }}" type="button" role="tab"
                                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                        <img src="{{ $imageUrl }}" class="rounded-10 product-gallery-thumb-image"
                                            alt="thumb {{ $index + 1 }}">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                @if ($isProSupplier)
                                    <span class="badge bg-primary">PRO</span>
                                @endif
                                <span class="badge fs-20 {{ $stockBadgeClass }}" id="product-stock-badge">
                                    @if ($displayStock > 0)
                                        {{ $displayStock }} in stock
                                    @else
                                        Out of Stock
                                    @endif
                                </span>
                            </div>
                            <h3 class="mb-2">{{ $productName }}</h3>
                            <span class="fs-14 text-body d-block">{{ $supplierName }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                        <div class="d-flex align-items-center gap-1">
                            @for ($star = 1; $star <= 5; $star++)
                                @if ($star <= floor($rating))
                                    <i class="ri-star-fill fs-16 text-warning"></i>
                                @elseif ($star - 0.5 <= $rating)
                                    <i class="ri-star-half-fill fs-16 text-warning"></i>
                                @else
                                    <i class="ri-star-line fs-16 text-warning"></i>
                                @endif
                            @endfor
                            <span class="fs-14 fw-medium text-secondary ms-1">{{ number_format($rating, 1) }}</span>
                        </div>
                        <span class="fs-14 text-body">{{ $reviewCount }} Reviews</span>
                        <span class="fs-14 text-secondary">{{ $soldCount }} Sold</span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="badge fs-14 bg-light text-body border">Category: {{ $category }}</span>
                        <span class="badge fs-14 bg-light text-body border">Market: {{ $marketCountry }}</span>
                    </div>

                    @if ($defaultVariant)
                        <div class="border rounded-10 p-3 bg-light-subtle mb-4" id="product-pricing-panel"
                            data-price-lock="{{ $priceLocked ? 'true' : 'false' }}">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div id="product-price-label" class="text-muted fs-13 mb-1">
                                        {{ $priceLocked ? 'Selling Price' : 'Suggested Price' }}
                                    </div>
                                    <div id="product-price-value" class="fs-24 fw-bold text-secondary mb-0">
                                        @if ($priceLocked)
                                            {{ $formatAmount($defaultVariant['selling_price']) }}
                                        @else
                                            {{ $formatRange($defaultVariant['suggested_price_min'], $defaultVariant['suggested_price_max']) }}
                                        @endif
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted fs-13 mb-1">
                                        Dropshipping Price
                                    </div>
                                    <div id="product-cost-value" class="fs-20 fw-medium">
                                        {{ $formatAmount($defaultVariant['cost']) }}
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div id="product-commission-label" class="text-muted fs-13 mb-1">
                                        Potential Commission

                                    </div>
                                    <div id="product-commission-value" class="fs-20 fw-semibold text-primary">
                                        @if ($priceLocked)
                                            {{ $formatAmount($defaultVariant['commission_min']) }}
                                        @else
                                            {{ $formatRange($defaultVariant['commission_min'], $defaultVariant['commission_max']) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (count($variants) > 1)
                        <div class="mb-4">
                            <h5 class="mb-3">Variants</h5>
                            <div class="mb-3">
                                <span class="fs-14 fw-medium text-secondary d-block mb-2">Variant:</span>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($variants as $variantKey => $variantOption)
                                        <div class="form-check">
                                            <input class="form-check-input variant-option-input" type="radio"
                                                name="variant_id" id="variant-{{ $variantKey }}"
                                                value="{{ $variantKey }}" @checked($variantKey === $defaultVariantKey)>
                                            <label class="form-check-label fs-14 text-secondary"
                                                for="variant-{{ $variantKey }}">
                                                <span class="d-block">{{ $variantOption['label'] }}</span>
                                                <span class="d-block fs-12 text-muted">Variant ID:
                                                    {{ $variantOption['variant_id'] }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($defaultVariant)
                        <div class="border rounded-10 p-3 bg-light-subtle mb-4" id="selected-variant-panel">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <span class="fw-medium fs-16 d-block"
                                        id="selected-variant-label">{{ $defaultVariant['label'] }}</span>
                                    <span class="fs-14 text-muted d-block">Variant ID: <span
                                            id="selected-variant-id">{{ $defaultVariant['variant_id'] }}</span></span>
                                </div>
                                <span class="badge bg-light text-body border">Weight: <span
                                        id="selected-variant-weight">{{ $defaultVariant['weight'] }}</span></span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-muted fs-13 mb-1">Cost</div>
                                    <div class="fs-16 fw-medium" id="selected-variant-cost">
                                        {{ $formatAmount($defaultVariant['cost']) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-13 mb-1" id="selected-variant-price-label">
                                        {{ $priceLocked ? 'Selling Price' : 'Suggested Price Range' }}
                                    </div>
                                    <div class="fs-16 fw-medium" id="selected-variant-price">
                                        @if ($priceLocked)
                                            {{ $formatAmount($defaultVariant['selling_price']) }}
                                        @else
                                            {{ $formatRange($defaultVariant['suggested_price_min'], $defaultVariant['suggested_price_max']) }}
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-13 mb-1">Available Stock</div>
                                    <div class="fs-16 fw-medium" id="selected-variant-stock">
                                        @if ($displayStock > 0)
                                            {{ $displayStock }} units
                                        @else
                                            Out of Stock
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-10 p-3 bg-light-subtle mb-4">
                            <div class="text-muted fs-13 mb-1">Available Stock</div>
                            <div class="fs-16 fw-medium" id="product-stock-summary">{{ $stockLabel }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-20 flex-wrap gap-2">
                <h4 class="mb-0">Product Information</h4>
            </div>

            <div class="border rounded-10 p-4 bg-white">
                <h5 class="mb-3">Description</h5>
                @forelse ($productLanguages as $language)
                    @php
                        $description = $product?->descriptionFor($language['code']) ?? '';
                    @endphp
                    <div @class(['mb-4' => ! $loop->last])>
                        <h6 class="mb-2 text-secondary">{{ $language['tab_label'] }}</h6>
                        @if (filled($description))
                            <p class="fs-16 lh-1-8 text-body mb-0">{{ $description }}</p>
                        @else
                            <p class="fs-16 lh-1-8 text-body mb-0 text-muted">No {{ $language['label'] }} description available.</p>
                        @endif
                    </div>
                @empty
                    <p class="fs-16 lh-1-8 text-body mb-0 text-muted">No description available.</p>
                @endforelse
            </div>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-20 flex-wrap gap-2">
                <h4 class="mb-0">Supplier Information</h4>
                @if ($isProSupplier)
                    <span class="badge bg-primary">PRO</span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                        <div class="text-muted fs-13 mb-1">Company</div>
                        <div class="fs-16 fw-medium">{{ $supplierInfo['company'] }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                        <div class="text-muted fs-13 mb-1">Supplier Type</div>
                        <div class="fs-16 fw-medium">{{ $supplierInfo['type'] }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                        <div class="text-muted fs-13 mb-1">Category Focus</div>
                        <div class="fs-16 fw-medium">{{ $supplierInfo['category_focus'] }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                        <div class="text-muted fs-13 mb-1">Market</div>
                        <div class="fs-16 fw-medium">{{ $supplierInfo['market'] }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 h-100 bg-light-subtle">
                        <div class="text-muted fs-13 mb-1">Member Since</div>
                        <div class="fs-16 fw-medium">{{ $supplierInfo['member_since'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-white p-20 rounded-10 border border-white">
            <div class="d-flex justify-content-between align-items-center mb-20 flex-wrap gap-2">
                <h4 class="mb-0">Reviews & Ratings</h4>
                <span
                    class="badge bg-warning-subtle text-warning border border-warning border-opacity-10">{{ $reviewCount }}
                    reviews</span>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="border rounded-10 p-4 bg-light-subtle text-center h-100">
                        <div class="fs-36 fw-bold text-warning mb-2">{{ number_format($rating, 1) }}</div>
                        <div class="mb-2">
                            @for ($star = 1; $star <= 5; $star++)
                                @if ($star <= floor($rating))
                                    <i class="ri-star-fill text-warning"></i>
                                @elseif ($star - 0.5 <= $rating)
                                    <i class="ri-star-half-fill text-warning"></i>
                                @else
                                    <i class="ri-star-line text-warning"></i>
                                @endif
                            @endfor
                        </div>
                        <div class="text-muted">Based on {{ $reviewCount }} reviews</div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="d-flex flex-column gap-3">
                        <div class="border rounded-10 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="fw-medium">Excellent sound quality</span>
                                <div>
                                    @for ($star = 1; $star <= 5; $star++)
                                        <i class="ri-star-fill text-warning fs-14"></i>
                                    @endfor
                                </div>
                            </div>
                            <p class="mb-1 text-body fs-14 lh-1-7">
                                Clear audio, comfortable fit, and the battery easily lasts through a full workday.
                            </p>
                            <span class="fs-12 text-muted">Verified reseller · 2 weeks ago</span>
                        </div>

                        <div class="border rounded-10 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="fw-medium">Great margin potential</span>
                                <div>
                                    @for ($star = 1; $star <= 4; $star++)
                                        <i class="ri-star-fill text-warning fs-14"></i>
                                    @endfor
                                    <i class="ri-star-line text-warning fs-14"></i>
                                </div>
                            </div>
                            <p class="mb-1 text-body fs-14 lh-1-7">
                                Reliable supplier and consistent stock. Customers appreciate the ANC feature.
                            </p>
                            <span class="fs-12 text-muted">Verified reseller · 1 month ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-grow-1"></div>

    <style>
        .nav-tabs-separator .nav-link {
            border: 1px solid #e5e7eb;
            border-bottom: 0;
            margin-right: 8px;
            border-radius: 8px 8px 0 0;
            color: #6b7280;
            background: #f8fafc;
        }

        .nav-tabs-separator .nav-link.active {
            background: #fff;
            color: #111827;
            border-color: #e5e7eb;
            font-weight: 600;
        }

        .product-gallery-main-image {
            height: 500px;
            object-fit: cover;
        }

        .product-gallery-thumbs {
            gap: 12px;
            flex-wrap: wrap;
        }

        .product-gallery-thumb-image {
            width: 90px;
            height: 90px;
            object-fit: cover;
        }

        @media (max-width: 767.98px) {
            .product-gallery-main-image {
                height: 320px;
            }

            .product-gallery-thumb-image {
                width: 72px;
                height: 72px;
            }
        }
    </style>
@endsection

@push('scripts')
    <script>
        (function() {
            var priceLock = @json($priceLocked);
            var currencyCode = @json($currencyCode);
            var currencyDecimals = @json($currencyDecimals);
            var variants = @json($variants);
            var defaultVariantKey = @json($defaultVariantKey);

            function formatAmount(amount) {
                if (amount === null || amount === undefined) {
                    return '—';
                }

                return currencyCode + ' ' + Number(amount).toLocaleString('en-US', {
                    minimumFractionDigits: currencyDecimals,
                    maximumFractionDigits: currencyDecimals,
                });
            }

            function formatRange(min, max) {
                return formatAmount(min) + ' – ' + formatAmount(max);
            }

            function selectedVariantKey() {
                var checked = document.querySelector('input[name="variant_id"]:checked');

                if (!checked) {
                    return defaultVariantKey;
                }

                return checked.value;
            }

            function firstVariant() {
                var key = defaultVariantKey;

                if (key && variants[key]) {
                    return variants[key];
                }

                var keys = Object.keys(variants);

                return keys.length > 0 ? variants[keys[0]] : null;
            }

            function updateVariantDisplay() {
                var key = selectedVariantKey();
                var variant = (key && variants[key]) ? variants[key] : firstVariant();

                if (!variant) {
                    return;
                }

                var stock = variant.stock;
                var inStock = stock > 0;

                var selectedVariantLabel = document.getElementById('selected-variant-label');
                if (selectedVariantLabel) {
                    selectedVariantLabel.textContent = variant.label;
                }

                var selectedVariantId = document.getElementById('selected-variant-id');
                if (selectedVariantId) {
                    selectedVariantId.textContent = variant.variant_id;
                }

                var selectedVariantWeight = document.getElementById('selected-variant-weight');
                if (selectedVariantWeight) {
                    selectedVariantWeight.textContent = variant.weight;
                }

                var selectedVariantCost = document.getElementById('selected-variant-cost');
                if (selectedVariantCost) {
                    selectedVariantCost.textContent = formatAmount(variant.cost);
                }

                var selectedPriceLabel = document.getElementById('selected-variant-price-label');
                var selectedPriceValue = document.getElementById('selected-variant-price');
                var productPriceLabel = document.getElementById('product-price-label');
                var productPriceValue = document.getElementById('product-price-value');
                var commissionLabel = document.getElementById('product-commission-label');
                var commissionValue = document.getElementById('product-commission-value');

                if (priceLock) {
                    if (selectedPriceLabel) {
                        selectedPriceLabel.textContent = 'Selling Price';
                    }
                    if (selectedPriceValue) {
                        selectedPriceValue.textContent = formatAmount(variant.selling_price);
                    }
                    if (productPriceLabel) {
                        productPriceLabel.textContent = 'Selling Price';
                    }
                    if (productPriceValue) {
                        productPriceValue.textContent = formatAmount(variant.selling_price);
                    }
                    if (commissionLabel) {
                        commissionLabel.innerHTML =
                            'Your Commission<span class="d-block fs-12">Selling Price − Cost</span>';
                    }
                    if (commissionValue) {
                        commissionValue.textContent = formatAmount(variant.commission_min);
                    }
                } else {
                    if (selectedPriceLabel) {
                        selectedPriceLabel.textContent = 'Suggested Price Range';
                    }
                    if (selectedPriceValue) {
                        selectedPriceValue.textContent = formatRange(variant.suggested_price_min, variant
                            .suggested_price_max);
                    }
                    if (productPriceLabel) {
                        productPriceLabel.textContent = 'Suggested Price';
                    }
                    if (productPriceValue) {
                        productPriceValue.textContent = formatRange(variant.suggested_price_min, variant
                            .suggested_price_max);
                    }
                }

                var stockText = inStock ? stock + ' units' : 'Out of Stock';

                var selectedVariantStock = document.getElementById('selected-variant-stock');
                if (selectedVariantStock) {
                    selectedVariantStock.textContent = stockText;
                }

                var productStockSummary = document.getElementById('product-stock-summary');
                if (productStockSummary) {
                    productStockSummary.textContent = stockText;
                }

                var stockBadge = document.getElementById('product-stock-badge');
                if (stockBadge) {
                    stockBadge.textContent = inStock ? stock + ' in stock' : 'Out of Stock';
                    stockBadge.className = 'badge ' + (inStock ?
                        'bg-success-subtle text-success border border-success border-opacity-10' :
                        'bg-danger-subtle text-danger border border-danger border-opacity-10');
                }
            }

            document.querySelectorAll('.variant-option-input').forEach(function(input) {
                input.addEventListener('change', updateVariantDisplay);
            });

            updateVariantDisplay();
        })();
    </script>
@endpush
