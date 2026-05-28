<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SerialStatus;
use App\Http\Requests\Order\CalculateTaxRequest;
use App\Http\Requests\Order\RecordCashPaymentRequest;
use App\Http\Requests\Order\StoreCustomerAddressFromOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\ProductListing;
use App\Services\AvaTaxService;
use App\Services\CustomerAddressService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service,
        private readonly AvaTaxService $avatax,
        private readonly CustomerAddressService $addresses,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->service->paginate($request->only(['search', 'status', 'source', 'from', 'to']));

        return view('orders.index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'source', 'from', 'to']),
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

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

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
            'lines.lineFees',
            'payments.createdBy',
            'events.createdBy',
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

        $order->load(['lines.productListing.product', 'lines.lineFees', 'customer.addresses', 'payments']);

        // Match snapshot back to a CustomerAddress using 5 fields to reduce false-positive injection risk.
        $matchAddressId = function (?string $line1, ?string $city, ?string $state, ?string $postal, ?string $country) use ($order): ?int {
            if ($line1 === null || $city === null || $postal === null) {
                return null;
            }

            $match = $order->customer->addresses->first(fn ($a) => $a->address_line1 === $line1
                && $a->city === $city
                && $a->state === $state
                && $a->postal_code === $postal
                && $a->country === $country
            );

            return $match?->id;
        };

        $existingOrder = [
            'customer_id' => $order->customer_id,
            'source' => $order->source->value,
            'payment_method' => $order->payments->first()?->method?->value ?? PaymentMethod::Cash->value,
            'billing_address_id' => $matchAddressId($order->billing_address_line1, $order->billing_city, $order->billing_state, $order->billing_postal_code, $order->billing_country),
            'shipping_address_id' => $matchAddressId($order->shipping_address_line1, $order->shipping_city, $order->shipping_state, $order->shipping_postal_code, $order->shipping_country),
            'shipping' => (float) $order->shipping,
            'lines' => $order->lines->map(fn ($l) => [
                'product_listing_id' => $l->product_listing_id,
                'sku' => $l->sku,
                'unit_price' => (float) $l->unit_price,
                'tax_amount' => (float) $l->tax_amount,
                'stock' => '',
                'fees' => $l->lineFees->map(fn ($f) => [
                    'name' => $f->name,
                    'amount' => (float) $f->amount,
                    'tax_amount' => (float) $f->tax_amount,
                ])->all(),
            ])->all(),
        ];

        return view('orders.edit', [
            'order' => $order,
            'existingOrder' => $existingOrder,
            'customers' => Customer::with('addresses')->orderBy('name')->get(),
            'productListings' => ProductListing::with('product')->active()->get(),
            'sources' => OrderSource::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

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
        $this->authorize('recordCashPayment', $order);

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

    // ── Helper endpoints ─────────────────────────────────────────────────

    public function customerAddresses(Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', Order::class);
        $this->authorize('view', $customer);

        $customer->load('addresses');

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
                'address_line1' => $a->address_line1,
                'city' => $a->city,
                'state' => $a->state,
                'postal_code' => $a->postal_code,
                'country' => $a->country,
            ])
        );
    }

    public function storeCustomerAddress(StoreCustomerAddressFromOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorize('create', [CustomerAddress::class, $customer]);
        unset($data['customer_id']);

        $address = $this->addresses->store($customer, $data);

        return response()->json([
            'id' => $address->id,
            'label' => $address->label,
            'summary' => trim(implode(', ', array_filter([
                trim($address->first_name.' '.$address->last_name),
                $address->address_line1,
                $address->city,
            ]))),
            'is_default' => $address->is_default,
            'address_line1' => $address->address_line1,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
        ], 201);
    }

    public function listingStock(ProductListing $listing): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $listing->load('product');

        $stock = InventorySerial::query()
            ->with('location')
            ->where('product_id', $listing->product_id)
            ->where('status', SerialStatus::InStock)
            ->whereNotIn('id', function ($q) {
                $q->select('inventory_serial_id')
                    ->from('order_lines')
                    ->whereNotNull('inventory_serial_id');
            })
            ->get()
            ->groupBy('inventory_location_id')
            ->map(fn ($serials, $locId) => [
                'location' => $serials->first()->location?->name ?? 'Unknown',
                'qty' => $serials->count(),
            ])
            ->values();

        return response()->json([
            'sku' => $listing->product->sku,
            'stock' => $stock,
        ]);
    }

    public function calculateTax(CalculateTaxRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $data = $request->validated();
        $customerId = (int) $data['customer_id'];
        $shippingAddress = $data['shipping_address'] ?? null;
        $lines = $data['lines'];

        $customer = Customer::find($customerId);

        // Pass the customer's exemption reason to AvaTax so the quote reflects the
        // exemption (and AvaTax records the usage) — matches what commitInvoice sends.
        $entityUseCode = $customer?->tax_exempt ? $customer->entity_use_code : null;

        // For pickup (no shipping address), use shop address
        $shipTo = $shippingAddress ?: [
            'address_line1' => config('shop.billing.address_line1'),
            'city' => config('shop.billing.city'),
            'state' => config('shop.billing.state'),
            'postal_code' => config('shop.billing.postal_code'),
            'country' => config('shop.billing.country'),
        ];

        // Flatten: unit + fees per line into AvaTax items
        $items = [];
        $indexMap = []; // tracks which item belongs where
        foreach ($lines as $i => $line) {
            $items[] = [
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'sku' => $line['sku'] ?? '',
            ];
            $indexMap[] = ['type' => 'line', 'line' => $i];

            foreach ($line['fees'] ?? [] as $j => $fee) {
                $items[] = [
                    'unit_price' => (float) ($fee['amount'] ?? 0),
                    'sku' => 'FEE-'.($fee['name'] ?? ''),
                ];
                $indexMap[] = ['type' => 'fee', 'line' => $i, 'fee' => $j];
            }
        }

        $result = $this->avatax->calculateTax($items, $shipTo, (string) $customerId, $entityUseCode);

        // Rebuild nested structure
        $response = ['lines' => []];
        foreach ($lines as $i => $line) {
            $response['lines'][$i] = [
                'tax_amount' => 0,
                'fees' => array_fill(0, count($line['fees'] ?? []), ['tax_amount' => 0]),
            ];
        }

        foreach ($result as $k => $taxRow) {
            $loc = $indexMap[$k];
            if ($loc['type'] === 'line') {
                $response['lines'][$loc['line']]['tax_amount'] = $taxRow['tax_amount'];
            } else {
                $response['lines'][$loc['line']]['fees'][$loc['fee']]['tax_amount'] = $taxRow['tax_amount'];
            }
        }

        return response()->json($response);
    }
}
