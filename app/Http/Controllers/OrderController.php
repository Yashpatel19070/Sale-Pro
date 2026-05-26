<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SerialStatus;
use App\Http\Requests\Order\RecordCashPaymentRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\ProductListing;
use App\Services\AvaTaxService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function calculateTax(Request $request, AvaTaxService $avatax): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $customerId = $request->input('customer_id');
        $shippingAddress = $request->input('shipping_address');
        $lines = $request->input('lines', []);
        $zeros = array_map(fn () => ['tax_rate' => 0, 'tax_amount' => 0], $lines);

        if (empty($shippingAddress)) {
            return response()->json($zeros);
        }

        $customer = Customer::find($customerId);

        if ($customer?->tax_exempt) {
            return response()->json($zeros);
        }

        return response()->json(
            $avatax->calculateTax($lines, $shippingAddress, (string) $customerId)
        );
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->only(['status', 'source', 'search', 'date_from', 'date_to']);
        $orders = $this->service->paginate($filters);

        return view('orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => OrderStatus::cases(),
            'sources' => OrderSource::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Order::class);

        return view('orders.create', [
            'customers' => Customer::with('addresses')->orderBy('name')->get(),
            'productListings' => ProductListing::with('product')->active()->get(),
            'sources' => OrderSource::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function customerAddresses(Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        return response()->json(
            $customer->addresses->map(fn ($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'summary' => trim(implode(', ', array_filter([
                    trim($a->first_name.' '.$a->last_name),
                    $a->address_line1,
                    $a->city,
                ]))),
                'is_default' => $a->is_default,
            ])
        );
    }

    public function listingStock(ProductListing $listing): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $listing->load('product');

        $reserved = DB::table('order_lines')
            ->whereNotNull('inventory_serial_id')
            ->pluck('inventory_serial_id');

        $stock = InventorySerial::where('product_id', $listing->product_id)
            ->where('status', SerialStatus::InStock->value)
            ->whereNotIn('id', $reserved)
            ->with('location')
            ->get()
            ->groupBy('inventory_location_id')
            ->map(fn ($serials) => [
                'location' => $serials->first()->location?->name ?? 'Unknown',
                'qty' => $serials->count(),
            ])
            ->values();

        return response()->json([
            'sku' => $listing->product->sku,
            'stock' => $stock,
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->service->store($request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', $order)
            ->with('success', "Order {$order->number} created.");
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load([
            'customer',
            'lines.productListing.product',
            'lines.inventorySerial',
            'orderFees',
            'payments.createdBy',
            'createdBy',
        ]);

        return view('orders.show', compact('order'));
    }

    public function edit(Order $order): View|RedirectResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('orders.show', $order)
                ->withErrors(['error' => 'Only pending orders can be edited.']);
        }

        $order->load(['customer', 'lines.productListing.product', 'lines.inventorySerial', 'orderFees']);

        return view('orders.edit', [
            'order' => $order,
            'customers' => Customer::with('addresses')->orderBy('name')->get(),
            'productListings' => ProductListing::with('product')->active()->get(),
            'sources' => OrderSource::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        try {
            $this->service->update($order, $request->validated());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        try {
            $this->service->delete($order);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('orders.index')
            ->with('success', 'Order deleted.');
    }

    public function recordCashPayment(RecordCashPaymentRequest $request, Order $order): RedirectResponse
    {
        try {
            $this->service->recordCashPayment($order, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Payment recorded.');
    }

    public function complete(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('complete', $order);

        try {
            $this->service->complete($order, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order completed.');
    }
}
