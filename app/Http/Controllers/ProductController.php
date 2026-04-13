<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductCategoryService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->service->list($request->only(['search', 'category_id', 'active']));
        $categories = $this->categoryService->dropdown();

        return view('products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = $this->categoryService->dropdown();

        return view('products.create', compact('categories'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->service->create($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Product \"{$product->name}\" created.");
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category', 'listings']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $categories = $this->categoryService->dropdown();

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->service->update($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        try {
            $this->service->delete($product);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product = $this->service->toggleActive($product);
        $state = $product->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Product {$state}.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorize('restore', Product::class);

        $product = $this->service->restore($product);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product restored.');
    }
}
