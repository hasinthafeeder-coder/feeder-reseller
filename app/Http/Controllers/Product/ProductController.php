<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexResellerProductRequest;
use App\Services\Product\ResellerProductDetailService;
use App\Services\Product\ResellerProductListService;
use Feeder\Core\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ResellerProductListService $productListService,
        private readonly ResellerProductDetailService $productDetailService,
    ) {}

    public function index(IndexResellerProductRequest $request): View
    {
        $reseller = Auth::user();

        return view('pages.products.list', [
            'products' => $this->productListService->paginate($reseller, $request),
            'suppliers' => $this->productListService->supplierFilterOptions($reseller),
            'categories' => $this->productListService->categoryFilterOptions($reseller),
            'supplierTypes' => $this->productListService->supplierTypeFilterOptions(),
            'filters' => $this->productListService->activeFilters($request, $reseller),
        ]);
    }

    public function show(Product $product): View
    {
        $reseller = Auth::user();

        return view('pages.products.details', $this->productDetailService->buildViewData($reseller, $product));
    }
}
