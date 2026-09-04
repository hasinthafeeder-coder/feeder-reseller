<?php

namespace App\Services\Product;

use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\SupplierType;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\User;
use Feeder\Core\Services\ResellerSupplierAssignmentService;
use Feeder\Core\Services\StockService;
use Feeder\Core\Support\ProductCategoryTree;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResellerProductListService
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly ResellerSupplierAssignmentService $assignmentService,
        private readonly StockService $stockService,
    ) {}

    public function paginate(User $reseller, Request $request): LengthAwarePaginator
    {
        $paginator = $this->filteredQuery($reseller, $request)
            ->with([
                'category',
                'variants',
                'images.file',
                'supplier.company',
                'market.country',
                'market.currency',
            ])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $this->attachStockTotals($paginator->getCollection());

        return $paginator;
    }

    /**
     * @return Collection<int, array{id: int, name: string, count: int}>
     */
    public function supplierFilterOptions(User $reseller): Collection
    {
        $counts = $this->baseQuery($reseller)
            ->selectRaw('supplier_id, COUNT(*) as product_count')
            ->groupBy('supplier_id')
            ->pluck('product_count', 'supplier_id');

        return $this->assignmentService
            ->listAssignedSuppliers($reseller)
            ->map(fn (User $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->company?->name ?? 'Supplier #'.$supplier->id,
                'count' => (int) ($counts[$supplier->id] ?? 0),
            ])
            ->sortBy(fn (array $option) => mb_strtolower($option['name']))
            ->values();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    public function categoryFilterOptions(User $reseller): Collection
    {
        $counts = $this->baseQuery($reseller)
            ->selectRaw('category_id, COUNT(*) as product_count')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');

        $categoryIdsWithProducts = $counts->keys()->filter()->all();

        if ($categoryIdsWithProducts === []) {
            return collect();
        }

        $allCategories = ProductCategory::query()
            ->active()
            ->ordered()
            ->get();

        $relevantCategoryIds = ProductCategoryTree::expandWithAncestors(
            $allCategories,
            $categoryIdsWithProducts
        );

        $relevantCategories = $allCategories
            ->whereIn('id', $relevantCategoryIds)
            ->values()
            ->each(function (ProductCategory $category) use ($counts): void {
                $category->setAttribute(
                    'product_count',
                    (int) ($counts[$category->id] ?? 0)
                );
            });

        $roots = ProductCategoryTree::build($relevantCategories);

        return ProductCategoryTree::pruneIrrelevantBranches($roots, $categoryIdsWithProducts);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function supplierTypeFilterOptions(): array
    {
        return [
            ['value' => SupplierType::PRO->value, 'label' => 'PRO'],
            ['value' => SupplierType::STANDARD->value, 'label' => 'Standard'],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     suppliers: list<int>,
     *     categories: list<string>,
     *     supplier_types: list<string>
     * }
     */
    public function activeFilters(Request $request, User $reseller): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'suppliers' => $this->resolveAllowedSupplierFilter(
                $reseller,
                array_map('intval', (array) $request->input('suppliers', []))
            ),
            'categories' => array_values(array_filter((array) $request->input('categories', []))),
            'supplier_types' => array_values(array_filter((array) $request->input('supplier_types', []))),
        ];
    }

    private function baseQuery(User $reseller): Builder
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

    private function filteredQuery(User $reseller, Request $request): Builder
    {
        $query = $this->baseQuery($reseller);

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $requestedSupplierIds = array_values(array_unique(array_map(
            'intval',
            (array) $request->input('suppliers', [])
        )));

        if ($requestedSupplierIds !== []) {
            $allowedSupplierIds = $this->resolveAllowedSupplierFilter($reseller, $requestedSupplierIds);

            if ($allowedSupplierIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('supplier_id', $allowedSupplierIds);
            }
        }

        $categoryIds = array_values(array_filter((array) $request->input('categories', [])));

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $supplierTypes = array_values(array_filter((array) $request->input('supplier_types', [])));

        if ($supplierTypes !== []) {
            $query->whereHas('supplier.company', function (Builder $companyQuery) use ($supplierTypes): void {
                $companyQuery->whereIn('supplier_type', $supplierTypes);
            });
        }

        return $query;
    }

    /**
     * @param  list<int>  $requestedSupplierIds
     * @return list<int>
     */
    private function resolveAllowedSupplierFilter(User $reseller, array $requestedSupplierIds): array
    {
        if ($requestedSupplierIds === []) {
            return [];
        }

        $allowedSupplierIds = $this->assignmentService
            ->assignedSupplierIds($reseller)
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($requestedSupplierIds, $allowedSupplierIds));
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

    private function attachStockTotals(Collection $products): void
    {
        $variantIds = $products
            ->flatMap(fn (Product $product) => $product->variants->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $stockByVariant = $this->stockService->remainingStockForVariants($variantIds);

        foreach ($products as $product) {
            $totalStock = $product->variants->sum(
                fn ($variant) => $stockByVariant[(int) $variant->id] ?? 0
            );

            $product->setAttribute('total_stock', $totalStock);
        }
    }
}
