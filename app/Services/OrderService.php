<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderFee;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly AvaTaxService $avatax,
        private readonly InventoryMovementService $inventoryMovements,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with(['customer', 'lines'])
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where('number', 'like', "%{$filters['search']}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$filters['search']}%"))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function create(array $data, User $user): Order
    {
        $customerId = (int) $data['customer_id'];

        $serials = [];
        foreach ($data['lines'] as $i => $line) {
            $serials[$i] = InventorySerial::with('product')->findOrFail($line['serial_id']);
        }

        $lineSummaries = [];
        foreach ($data['lines'] as $i => $line) {
            $lineSummaries[$i] = [
                'unit_price' => (float) $line['unit_price'],
                'sku' => $serials[$i]->product->sku ?? '',
            ];
        }

        $shipTo = $this->extractShipTo($data);
        $taxData = $this->avatax->calculateTax($lineSummaries, $shipTo);

        return DB::transaction(function () use ($data, $user, $customerId, $taxData, $serials) {
            $shippingAddr = $this->resolveAddress($customerId, $data['shipping'] ?? []);
            $billingAddr = ($data['billing_same_as_shipping'] ?? false)
                ? $shippingAddr
                : $this->resolveAddress($customerId, $data['billing'] ?? []);

            $number = $this->nextOrderNumber();
            $lineRows = $this->buildLines($data['lines'], $taxData, $serials);
            $subtotal = array_sum(array_column($lineRows, 'line_total'));
            $feeTotal = array_sum(array_column($data['fees'] ?? [], 'amount'));
            $shipping = (float) $data['shipping_amount'];

            $order = Order::create([
                'number' => $number,
                'customer_id' => $customerId,
                'source' => $data['source'],
                'status' => OrderStatus::Pending,
                'payment_status' => 'unpaid',
                'created_by' => $user->id,
                'subtotal' => $subtotal,
                'fees' => $feeTotal,
                'shipping' => $shipping,
                'grand_total' => $subtotal + $feeTotal + $shipping,
                'currency' => 'USD',
                ...$this->shippingSnapshot($shippingAddr),
                ...$this->billingSnapshot($billingAddr),
            ]);

            foreach ($lineRows as $row) {
                OrderLine::create(['order_id' => $order->id, ...$row]);
            }

            foreach ($data['fees'] ?? [] as $fee) {
                OrderFee::create([
                    'order_id' => $order->id,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                ]);
            }

            return $order;
        });
    }

    public function recordCashPayment(Order $order, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($order, $data, $user) {
            throw_if(
                $order->status !== OrderStatus::Pending,
                \DomainException::class,
                'Only pending orders can receive payment.'
            );

            $payment = Payment::create([
                'order_id' => $order->id,
                'payable_type' => 'order',
                'payable_id' => $order->id,
                'method' => PaymentMethod::Cash,
                'amount' => $data['amount'],
                'status' => PaymentStatus::Paid,
                'created_by' => $user->id,
                'currency' => 'USD',
                'cash_received_at' => $data['cash_received_at'],
            ]);

            $order->update([
                'payment_status' => 'paid',
                'status' => OrderStatus::Processing,
            ]);

            return $payment;
        });
    }

    public function ship(Order $order, array $data, User $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            throw_if(
                $order->status !== OrderStatus::Processing,
                \DomainException::class,
                'Only processing orders can be shipped.'
            );

            $addressId = $this->resolveShipmentAddressId($order);

            Shipment::create([
                'shippable_type' => 'order',
                'shippable_id' => $order->id,
                'customer_address_id' => $addressId,
                'direction' => 'outbound',
                'carrier' => $data['carrier'],
                'tracking' => $data['tracking'],
                'label_cost' => $data['label_cost'],
                'status' => 'in_transit',
                'created_by' => $user->id,
                'shipped_at' => $data['shipped_at'],
            ]);

            foreach ($order->lines()->get() as $line) {
                $serial = InventorySerial::with('location')
                    ->lockForUpdate()
                    ->findOrFail($line->inventory_serial_id);

                $this->inventoryMovements->sale($serial, $serial->location, $user, $order->number);
            }

            $order->update([
                'status' => OrderStatus::Shipped,
                'shipped_at' => $data['shipped_at'],
                'shipped_by' => $user->id,
            ]);

            return $order;
        });
    }

    public function markDelivered(Order $order, array $data, User $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            throw_if(
                $order->status !== OrderStatus::Shipped,
                \DomainException::class,
                'Only shipped orders can be marked delivered.'
            );

            $order->shipments()
                ->where('direction', 'outbound')
                ->latest()
                ->firstOrFail()
                ->update([
                    'status' => 'delivered',
                    'delivered_at' => $data['delivered_at'],
                    'delivered_by' => $user->id,
                ]);

            $order->update([
                'delivered_at' => $data['delivered_at'],
                'delivered_by' => $user->id,
            ]);

            return $order;
        });
    }

    public function update(Order $order, array $data, User $user): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $fresh = Order::lockForUpdate()->find($order->id);

            throw_if(
                $fresh->status !== OrderStatus::Pending,
                \DomainException::class,
                'Only pending orders can be edited.'
            );

            $shippingAddr = $this->resolveAddress($fresh->customer_id, $data['shipping'] ?? []);
            $billingAddr = ($data['billing_same_as_shipping'] ?? false)
                ? $shippingAddr
                : $this->resolveAddress($fresh->customer_id, $data['billing'] ?? []);

            $fresh->orderFees()->delete();

            foreach ($data['fees'] ?? [] as $fee) {
                OrderFee::create([
                    'order_id' => $fresh->id,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                ]);
            }

            $feeTotal = array_sum(array_column($data['fees'] ?? [], 'amount'));
            $shipping = (float) $data['shipping_amount'];
            $subtotal = (float) $fresh->subtotal;

            $fresh->update([
                'source' => $data['source'],
                'fees' => $feeTotal,
                'shipping' => $shipping,
                'grand_total' => $subtotal + $feeTotal + $shipping,
                ...$this->shippingSnapshot($shippingAddr),
                ...$this->billingSnapshot($billingAddr),
            ]);

            return $fresh->fresh();
        });
    }

    public function cancel(Order $order, User $user): Order
    {
        throw_if(
            ! in_array($order->status, [OrderStatus::Pending, OrderStatus::Processing]),
            \DomainException::class,
            'Only pending or processing orders can be cancelled.'
        );

        $order->update([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        return $order->fresh();
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order) {
            throw_if(
                $order->status !== OrderStatus::Cancelled,
                \DomainException::class,
                'Only cancelled orders can be deleted.'
            );

            $order->orderFees()->delete();
            $order->lines()->delete();
            $order->delete();
        });
    }

    private function nextOrderNumber(): string
    {
        $current = DB::table('sequences')
            ->where('name', 'orders')
            ->lockForUpdate()
            ->value('value');

        $next = $current + 1;

        DB::table('sequences')->where('name', 'orders')->update(['value' => $next]);

        return sprintf('ORD-%d-%04d', now()->year, $next);
    }

    private function resolveAddress(int $customerId, array $address): ?CustomerAddress
    {
        if (! empty($address['address_id'])) {
            return CustomerAddress::findOrFail($address['address_id']);
        }

        if (! empty($address['line1'])) {
            return CustomerAddress::create([
                'customer_id' => $customerId,
                'label' => 'Delivery',
                'first_name' => $address['first_name'],
                'last_name' => $address['last_name'],
                'email' => $address['email'],
                'phone' => $address['phone'],
                'address_line1' => $address['line1'],
                'address_line2' => $address['line2'] ?? null,
                'city' => $address['city'],
                'state' => $address['state'],
                'postal_code' => $address['postal_code'],
                'country' => $address['country'],
                'is_default' => false,
            ]);
        }

        return null;
    }

    public function taxPreview(array $lines, array $shipping): array
    {
        $lineSummaries = [];
        foreach ($lines as $i => $line) {
            if (empty($line['serial_id'])) {
                continue;
            }
            $serial = InventorySerial::with('product')->find((int) $line['serial_id']);
            if (! $serial) {
                continue;
            }
            $lineSummaries[$i] = [
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'sku' => $serial->product->sku ?? '',
            ];
        }

        if (empty($lineSummaries)) {
            return [];
        }

        $shipTo = $this->extractShipTo(['shipping' => $shipping]);

        return $this->avatax->calculateTax($lineSummaries, $shipTo);
    }

    private function extractShipTo(array $data): array
    {
        $addr = $data['shipping'] ?? [];

        if (! empty($addr['address_id'])) {
            $ca = CustomerAddress::find($addr['address_id']);
            if ($ca) {
                return [
                    'line1' => $ca->address_line1,
                    'city' => $ca->city,
                    'state' => $ca->state,
                    'postal_code' => $ca->postal_code,
                    'country' => $ca->country,
                ];
            }
        }

        if (! empty($addr['line1'])) {
            return [
                'line1' => $addr['line1'],
                'city' => $addr['city'],
                'state' => $addr['state'],
                'postal_code' => $addr['postal_code'],
                'country' => $addr['country'],
            ];
        }

        return [];
    }

    private function buildLines(array $lines, array $taxData, array $serials): array
    {
        $result = [];

        foreach ($lines as $i => $line) {
            $serial = $serials[$i];
            $unitPrice = (float) $line['unit_price'];
            $taxRate = (float) ($taxData[$i]['tax_rate'] ?? 0.0);
            $taxAmount = (float) ($taxData[$i]['tax_amount'] ?? 0.0);

            $result[] = [
                'inventory_serial_id' => $serial->id,
                'sku' => $serial->product->sku ?? '',
                'product_name' => $serial->product->name ?? '',
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => round($unitPrice + $taxAmount, 2),
            ];
        }

        return $result;
    }

    private function shippingSnapshot(?CustomerAddress $address): array
    {
        return [
            'shipping_first_name' => $address?->first_name,
            'shipping_last_name' => $address?->last_name,
            'shipping_email' => $address?->email,
            'shipping_phone' => $address?->phone,
            'shipping_address_line1' => $address?->address_line1,
            'shipping_address_line2' => $address?->address_line2,
            'shipping_city' => $address?->city,
            'shipping_state' => $address?->state,
            'shipping_postal_code' => $address?->postal_code,
            'shipping_country' => $address?->country,
        ];
    }

    private function billingSnapshot(?CustomerAddress $address): array
    {
        return [
            'billing_first_name' => $address?->first_name,
            'billing_last_name' => $address?->last_name,
            'billing_email' => $address?->email,
            'billing_phone' => $address?->phone,
            'billing_address_line1' => $address?->address_line1,
            'billing_address_line2' => $address?->address_line2,
            'billing_city' => $address?->city,
            'billing_state' => $address?->state,
            'billing_postal_code' => $address?->postal_code,
            'billing_country' => $address?->country,
        ];
    }

    private function resolveShipmentAddressId(Order $order): ?int
    {
        if (! $order->shipping_address_line1) {
            return null;
        }

        return CustomerAddress::where('customer_id', $order->customer_id)
            ->where('address_line1', $order->shipping_address_line1)
            ->where('postal_code', $order->shipping_postal_code)
            ->value('id');
    }
}
