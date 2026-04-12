<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductCategory\StoreProductCategoryRequest;
use App\Http\Requests\ProductCategory\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductCategory::class);

        $filters = $request->only(['search', 'active']);
        $categories = $this->service->tree($filters);

        return view('product_categories.index', compact('categories', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', ProductCategory::class);

        return view('product_categories.create', [
            'category' => null,
            'flatTree' => $this->service->flatTree(),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $category = $this->service->create($request->validated());

        return redirect()
            ->route('product-categories.show', $category)
            ->with('success', "Category \"{$category->name}\" created.");
    }

    public function show(ProductCategory $productCategory): View
    {
        $this->authorize('view', $productCategory);

        $productCategory->load(['parent', 'children' => fn ($q) => $q->orderBy('name')]);

        return view('product_categories.show', ['category' => $productCategory]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $this->authorize('update', $productCategory);

        $productCategory->load('children.children.children');

        $forbiddenIds = array_merge(
            [$productCategory->id],
            $productCategory->descendantIds()
        );

        $flatTree = array_values(array_filter(
            $this->service->flatTree(),
            fn ($item) => ! in_array($item->id, $forbiddenIds, true)
        ));

        return view('product_categories.edit', [
            'category' => $productCategory,
            'flatTree' => $flatTree,
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->service->update($productCategory, $request->validated());

        return redirect()
            ->route('product-categories.show', $productCategory)
            ->with('success', 'Category updated.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('delete', $productCategory);

        $this->service->delete($productCategory);

        return redirect()
            ->route('product-categories.index')
            ->with('success', "Category \"{$productCategory->name}\" deleted.");
    }
}
