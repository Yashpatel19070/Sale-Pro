<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->service->list($request->only(['search', 'status']));

        return view('suppliers.index', compact('suppliers'));
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', compact('supplier'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->service->create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->code} created.");
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $this->service->deactivate($supplier);
        } catch (\DomainException $e) {
            return back()->withErrors(['supplier' => $e->getMessage()]);
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', "{$supplier->code} deactivated.");
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $this->authorize('restore', $supplier);

        $this->service->restore($supplier);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "{$supplier->code} restored.");
    }
}
