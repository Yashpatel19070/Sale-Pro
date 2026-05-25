<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\DeliverOrderRequest;
use App\Http\Requests\Order\RecordCashPaymentRequest;
use App\Http\Requests\Order\ShipOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->only(['search', 'status']);
        $orders = $this->service->paginate($filters);

        return view('orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Order::class);

        $customers = Customer::byStatus(CustomerStatus::Active)
            ->latest()
            ->get(['id', 'name', 'email', 'phone']);

        $addresses = CustomerAddress::orderBy('label')
            ->get(['id', 'customer_id', 'label', 'first_name', 'last_name', 'address_line1', 'city', 'state', 'postal_code'])
            ->groupBy('customer_id');

        return view('orders.create', [
            'customers' => $customers,
            'sources' => OrderSource::cases(),
            'addresses' => $addresses,
        ]);
    }

    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->service->create($request->validated(), $request->user());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order created.');
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'lines.serial.product', 'orderFees', 'payments', 'shipments']);

        return view('orders.show', compact('order'));
    }

    public function pay(RecordCashPaymentRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('pay', $order);

        try {
            $this->service->recordCashPayment($order, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Payment recorded.');
    }

    public function ship(ShipOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('ship', $order);

        $this->service->ship($order, $request->validated(), $request->user());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order shipped.');
    }

    public function deliver(DeliverOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('deliver', $order);

        $this->service->markDelivered($order, $request->validated(), $request->user());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Delivery recorded.');
    }

    public function edit(Order $order): View
    {
        $this->authorize('edit', $order);

        $order->load(['customer', 'lines.serial.product', 'orderFees']);

        $addresses = CustomerAddress::orderBy('label')
            ->get(['id', 'customer_id', 'label', 'first_name', 'last_name', 'address_line1', 'city', 'state', 'postal_code'])
            ->groupBy('customer_id');

        return view('orders.edit', [
            'order' => $order,
            'sources' => OrderSource::cases(),
            'addresses' => $addresses,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        try {
            $this->service->update($order, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        try {
            $this->service->cancel($order, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order cancelled.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        try {
            $this->service->delete($order);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('orders.index')->with('success', 'Order deleted.');
    }

    public function taxPreview(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $data = $request->validate([
            'lines' => ['array'],
            'lines.*.serial_id' => ['nullable', 'integer'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'array'],
        ]);

        $result = $this->service->taxPreview(
            $data['lines'] ?? [],
            $data['shipping'] ?? []
        );

        return response()->json($result);
    }
}
