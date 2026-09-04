<?php

namespace App\Services\Product;

use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\User;
use Feeder\Core\Services\ProductMarketLanguageService;
use Feeder\Core\Services\StockService;
use Feeder\Core\Support\CurrencyDisplay;
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Database\Eloquent\Builder;

class ResellerProductDetailService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ProductMarketLanguageService $productLanguageService,
    ) {}

    public function findAccessibleProduct(User $reseller, Product $product): Product
    {
        $accessibleProduct = $this->accessibleQuery($reseller)
            ->whereKey($product->getKey())
            ->with([
                'category',
                'descriptions',
                'variants',
                'images.file',
                'supplier.company',
                'market.country',
                'market.currency',
            ])
            ->first();

        abort_if($accessibleProduct === null, 404);

        $this->attachVariantStock($accessibleProduct);

        return $accessibleProduct;
    }

    /**
     * @return array{
     *     product: Product,
     *     productCurrency: ?Currency,
     *     priceLocked: bool,
     *     isProSupplier: bool,
     *     productLanguages: list<array{
     *         code: string,
     *         label: string,
     *         tab_label: string,
     *         placeholder: string
     *     }>,
     *     images: list<string>,
     *     fallbackImage: string,
     *     variants: array<string, array{
     *         label: string,
     *         variant_id: string,
     *         weight: string,
     *         cost: float,
     *         selling_price: float|null,
     *         suggested_price_min: float|null,
     *         suggested_price_max: float|null,
     *         commission_min: float|null,
     *         commission_max: float|null,
     *         stock: int
     *     }>,
     *     defaultVariantKey: ?string,
     *     soldCount: int,
     *     reviewCount: int,
     *     rating: float
     * }
     */
    public function buildViewData(User $reseller, Product $product): array
    {
        $product = $this->findAccessibleProduct($reseller, $product);

        $productCurrency = CurrencyDisplay::currencyFromMarket($product->market);
        $priceLocked = (bool) $product->price_locked;
        $variants = $this->buildVariantPayload($product, $priceLocked);
        $defaultVariant = $product->variants->first();
        $fallbackImage = asset('assets/images/product6.png');

        $images = $product->images
            ->map(fn ($image) => $image->file_uuid
                ? route('files.view', ['uuid' => $image->file_uuid])
                : null)
            ->filter()
            ->values()
            ->all();

        if ($images === []) {
            $images = [$fallbackImage];
        }

        return [
            'product' => $product,
            'productCurrency' => $productCurrency,
            'priceLocked' => $priceLocked,
            'isProSupplier' => (bool) $product->supplier?->company?->isProSupplier(),
            'productLanguages' => $this->productLanguageService->languagesForMarket($product->market),
            'images' => $images,
            'fallbackImage' => $fallbackImage,
            'variants' => $variants,
            'defaultVariantKey' => $defaultVariant ? (string) $defaultVariant->id : null,
            'soldCount' => 125,
            'reviewCount' => 24,
            'rating' => 4.8,
        ];
    }

    private function accessibleQuery(User $reseller): Builder
    {
        $allowedMarketIds = $this->allowedMarketIds($reseller);

        return Product::query()
            ->forReseller($reseller->id)
            ->where('status', ProductStatus::ACTIVE)
            ->where('system_visible', true)
            ->when(
                $allowedMarketIds !== [],
                fn (Builder $query) => $query->whereIn('market_id', $allowedMarketIds),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );
    }

    /**
     * @return list<int>
     */
    private function allowedMarketIds(User $reseller): array
    {
        $company = $reseller->company;

        if (! $company) {
            return [];
        }

        return $company->allowedMarkets()->pluck('markets.id')->map(fn ($id) => (int) $id)->all();
    }

    private function attachVariantStock(Product $product): void
    {
        $variantIds = $product->variants
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $stockByVariant = $this->stockService->remainingStockForVariants($variantIds);

        foreach ($product->variants as $variant) {
            $variant->setAttribute(
                'remaining_stock',
                (int) ($stockByVariant[(int) $variant->id] ?? 0)
            );
        }
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     variant_id: string,
     *     weight: string,
     *     cost: float,
     *     selling_price: float|null,
     *     suggested_price_min: float|null,
     *     suggested_price_max: float|null,
     *     commission_min: float|null,
     *     commission_max: float|null,
     *     stock: int
     * }>
     */
    private function buildVariantPayload(Product $product, bool $priceLocked): array
    {
        $payload = [];

        foreach ($product->variants as $variant) {
            /** @var ProductVariant $variant */
            $commission = ResellerProductPricing::commissionRange($variant, $priceLocked);

            $payload[(string) $variant->id] = [
                'label' => $variant->name,
                'variant_id' => (string) $variant->id,
                'weight' => $variant->weight !== null
                    ? number_format((float) $variant->weight, 3).' kg'
                    : '—',
                'cost' => ResellerProductPricing::resellerCost($variant),
                'selling_price' => $variant->selling_price !== null ? (float) $variant->selling_price : null,
                'suggested_price_min' => $variant->suggested_price_min !== null
                    ? (float) $variant->suggested_price_min
                    : null,
                'suggested_price_max' => $variant->suggested_price_max !== null
                    ? (float) $variant->suggested_price_max
                    : null,
                'commission_min' => $commission['min'],
                'commission_max' => $commission['max'],
                'stock' => (int) ($variant->remaining_stock ?? 0),
            ];
        }

        return $payload;
    }
}
