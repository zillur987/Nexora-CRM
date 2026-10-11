<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ListProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(ListProductsRequest $request)
    {
        return ProductResource::collection($this->service->list($request->validated()));
    }

    public function store(StoreProductRequest $request)
    {
        return ProductResource::make($this->service->create($request->validated()))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product->load('category'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        return ProductResource::make($this->service->update($product, $request->validated()));
    }

    public function destroy(Product $product): Response
    {
        $this->service->delete($product);
        return response()->noContent();
    }

    /** Counts for the type tabs and the KPI strip (catalog-wide, ignoring list filters). */
    public function summary()
    {
        $byType = Product::query()->selectRaw('product_type, COUNT(*) as aggregate')->groupBy('product_type')->pluck('aggregate', 'product_type');

        // Stock value per currency: amounts in different currencies are never summed together.
        $totals = Product::query()
            ->where('track_inventory', true)
            ->selectRaw('currency, SUM(quantity_on_hand * cost_price) as stock_cost, SUM(quantity_on_hand * sale_price) as stock_retail')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'stock_cost' => number_format((float) $row->stock_cost, 2, '.', ''),
                'stock_retail' => number_format((float) $row->stock_retail, 2, '.', ''),
            ])->values();

        return response()->json(['data' => [
            'total' => Product::query()->count(),
            'active' => Product::query()->where('is_active', true)->count(),
            'low_stock' => Product::query()->lowStock()->count(),
            'by_type' => $byType,
            'totals' => $totals,
        ]]);
    }

    public function lookups()
    {
        return response()->json([
            'data' => [
                'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'brands' => Product::query()->whereNotNull('brand')->where('brand', '<>', '')->distinct()->orderBy('brand')->pluck('brand'),
                'product_types' => ['physical', 'digital', 'service'],
                'units' => ['each', 'piece', 'box', 'pack', 'kg', 'g', 'lb', 'liter', 'meter', 'hour', 'day', 'month', 'year'],
            ],
        ]);
    }
}
