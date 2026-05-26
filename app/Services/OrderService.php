<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Models\CustomerAddress;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderFee;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Order::query()
            ->with(['customer', 'createdBy'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['source']), fn ($q) => $q->where('source', $filters['source']))
            ->when(
                isset($filters['search']),
                fn ($q) => $q->whereHas('customer', fn ($cq) => $cq
                    ->where('first_name', 'like', "%{$filters['search']}%")
                    ->orWhere('last_name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                )
            )
            ->latest()
            ->paginate(20);
    }

    public function store(array $data, User $createdBy): Order
    {
        return DB::transaction(function () use ($data, $createdBy): Order {
            $billingSnapshot = $this->resolveBillingSnapshot(isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null);
            $shippingSnapshot = $this->resolveShippingSnapshot(isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null);

            $order = Order::create([
                'number' => $this->generateNumber(),
                'customer_id' => $data['customer_id'],
                'source' => $data['source'],
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'created_by' => $createdBy->id,
                'subtotal' => 0,
                'fees' => 0,
                'shipping' => $data['shipping'] ?? 0,
                'grand_total' => 0,
                ...$billingSnapshot,
                ...$shippingSnapshot,
            ]);

            foreach ($data['lines'] as $line) {
                $listing = ProductListing::with('product')->findOrFail($line['product_listing_id']);

                $taxAmount = round((float) ($line['tax_amount'] ?? 0), 2);

                OrderLine::create([
                    'order_id' => $order->id,
                    'product_listing_id' => $listing->id,
                    'inventory_serial_id' => null,
                    'sku' => $listing->product->sku,
                    'product_name' => $listing->product->name,
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $taxAmount,
                    'line_total' => round((float) $line['unit_price'] + $taxAmount, 2),
                ]);
            }

            foreach ($data['fees'] ?? [] as $fee) {
                OrderFee::create([
                    'order_id' => $order->id,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                ]);
            }

            $this->recalculateTotals($order);

            $firstLine = $order->lines->first();
            OrderEvent::create([
                'order_id' => $order->id,
                'event' => 'order_placed',
                'metadata' => [
                    'sku' => $firstLine?->sku,
                    'product_name' => $firstLine?->product_name,
                    'grand_total' => number_format((float) $order->grand_total, 2, '.', ''),
                ],
                'created_by' => $createdBy->id,
            ]);

            return $order;
        });
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Pending) {
                throw new \DomainException('Only pending orders can be updated.');
            }

            $order->lines()->delete();
            foreach ($data['lines'] as $line) {
                $listing = ProductListing::with('product')->findOrFail($line['product_listing_id']);

                $taxAmount = round((float) ($line['tax_amount'] ?? 0), 2);

                OrderLine::create([
                    'order_id' => $order->id,
                    'product_listing_id' => $listing->id,
                    'inventory_serial_id' => null,
                    'sku' => $listing->product->sku,
                    'product_name' => $listing->product->name,
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $taxAmount,
                    'line_total' => round((float) $line['unit_price'] + $taxAmount, 2),
                ]);
            }

            $order->orderFees()->delete();
            foreach ($data['fees'] ?? [] as $fee) {
                OrderFee::create([
                    'order_id' => $order->id,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                ]);
            }

            $billingSnapshot = $this->resolveBillingSnapshot(isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null);
            $shippingSnapshot = $this->resolveShippingSnapshot(isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null);

            $order->update([
                'customer_id' => $data['customer_id'],
                'source' => $data['source'],
                'payment_method' => $data['payment_method'],
                'shipping' => $data['shipping'] ?? 0,
                ...$billingSnapshot,
                ...$shippingSnapshot,
            ]);

            $this->recalculateTotals($order);

            return $order->refresh();
        });
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Pending) {
                throw new \DomainException('Only pending orders can be deleted.');
            }

            $order->delete();
        });
    }

    public function recordCashPayment(Order $order, array $data, User $createdBy): Payment
    {
        return DB::transaction(function () use ($order, $data, $createdBy): Payment {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->payment_status === PaymentStatus::Paid) {
                throw new \DomainException('This order has already been paid.');
            }

            $isFullPayment = (float) $data['amount'] >= (float) $locked->grand_total;

            $paymentStatus = $isFullPayment ? PaymentStatus::Paid : PaymentStatus::Partial;

            $payment = Payment::create([
                'order_id' => $order->id,
                'payable_type' => 'order',
                'payable_id' => $order->id,
                'method' => PaymentMethod::Cash,
                'amount' => $data['amount'],
                'status' => $paymentStatus,
                'cash_received_at' => now(),
                'created_by' => $createdBy->id,
            ]);

            $order->update(['payment_status' => $paymentStatus]);

            if ($isFullPayment) {
                $this->assignSerialsToLines($order);
                $this->recordSaleMovements($order, $createdBy);
                $this->advanceToProcessingIfReady($order);
            }

            OrderEvent::create([
                'order_id' => $order->id,
                'event' => 'payment_received',
                'metadata' => [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => number_format((float) $data['amount'], 2, '.', ''),
                    'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                    'fees' => number_format((float) $order->fees, 2, '.', ''),
                    'shipping' => number_format((float) $order->shipping, 2, '.', ''),
                ],
                'created_by' => $createdBy->id,
            ]);

            return $payment;
        });
    }

    public function complete(Order $order, User $completedBy): Order
    {
        return DB::transaction(function () use ($order, $completedBy): Order {
            if ($order->status !== OrderStatus::Processing) {
                throw new \DomainException('Only processing orders can be completed.');
            }

            $order->update(['status' => OrderStatus::Complete]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event' => 'completed',
                'metadata' => null,
                'created_by' => $completedBy->id,
            ]);

            return $order->refresh();
        });
    }

    private function generateNumber(): string
    {
        $year = now()->year;
        $prefix = "ORD-{$year}-";

        $max = Order::withTrashed()
            ->where('number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function recalculateTotals(Order $order): void
    {
        $order->load('lines', 'orderFees');

        $subtotal = $order->lines->sum(fn ($l) => (float) $l->line_total);
        $fees = $order->orderFees->sum(fn ($f) => (float) $f->amount);
        $shipping = (float) $order->shipping;

        $order->update([
            'subtotal' => $subtotal,
            'fees' => $fees,
            'grand_total' => round($subtotal + $fees + $shipping, 2),
        ]);
    }

    private function assignSerialsToLines(Order $order): void
    {
        $order->load('lines.productListing.product');

        foreach ($order->lines as $line) {
            if ($line->inventory_serial_id !== null) {
                continue;
            }

            $serial = InventorySerial::where('product_id', $line->productListing->product_id)
                ->where('status', SerialStatus::InStock->value)
                ->whereNotIn('id', function ($q) {
                    $q->select('inventory_serial_id')->from('order_lines')->whereNotNull('inventory_serial_id');
                })
                ->lockForUpdate()
                ->first();

            if ($serial === null) {
                throw new \DomainException("No in-stock serial available for {$line->product_name}.");
            }

            $line->update(['inventory_serial_id' => $serial->id]);
        }
    }

    private function recordSaleMovements(Order $order, User $by): void
    {
        $order->load(['lines:id,order_id,inventory_serial_id', 'customer:id,name']);
        $note = "Order placed by {$order->customer->name}";

        foreach ($order->lines as $line) {
            if ($line->inventory_serial_id === null) {
                continue;
            }

            $this->movementService->recordSale($line->inventory_serial_id, $order, $by, $note);
        }
    }

    private function advanceToProcessingIfReady(Order $order): void
    {
        $order->refresh();

        $hasUnassigned = $order->lines()->whereNull('inventory_serial_id')->exists();

        if (! $hasUnassigned && $order->payment_status === PaymentStatus::Paid) {
            $order->update(['status' => OrderStatus::Processing]);
        }
    }

    private function resolveBillingSnapshot(?int $addressId): array
    {
        if ($addressId === null) {
            return [];
        }

        $address = CustomerAddress::findOrFail($addressId);

        return [
            'billing_first_name' => $address->first_name,
            'billing_last_name' => $address->last_name,
            'billing_email' => $address->email,
            'billing_phone' => $address->phone,
            'billing_address_line1' => $address->address_line1,
            'billing_address_line2' => $address->address_line2,
            'billing_city' => $address->city,
            'billing_state' => $address->state,
            'billing_postal_code' => $address->postal_code,
            'billing_country' => $address->country,
        ];
    }

    private function resolveShippingSnapshot(?int $addressId): array
    {
        if ($addressId === null) {
            return [];
        }

        $address = CustomerAddress::findOrFail($addressId);

        return [
            'shipping_first_name' => $address->first_name,
            'shipping_last_name' => $address->last_name,
            'shipping_email' => $address->email,
            'shipping_phone' => $address->phone,
            'shipping_address_line1' => $address->address_line1,
            'shipping_address_line2' => $address->address_line2,
            'shipping_city' => $address->city,
            'shipping_state' => $address->state,
            'shipping_postal_code' => $address->postal_code,
            'shipping_country' => $address->country,
        ];
    }
}
