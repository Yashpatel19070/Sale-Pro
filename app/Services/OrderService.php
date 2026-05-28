<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderEvent as OrderEventEnum;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Models\CustomerAddress;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderLineFee;
use App\Models\Payment;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly AvaTaxService $avaTax,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Order::query()
            ->with(['customer', 'createdBy'])
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('number', 'like', "%{$s}%")
                        ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$s}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(25);
    }

    public function store(array $data, User $createdBy): Order
    {
        return DB::transaction(function () use ($data, $createdBy): Order {
            $billing = $this->resolveBillingSnapshot(
                isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null,
                PaymentMethod::from($data['payment_method'])
            );
            $shipping = $this->resolveShippingSnapshot(
                isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null
            );

            $order = new Order;
            $order->fill([
                'number' => $this->generateNumber(),
                'customer_id' => $data['customer_id'],
                'source' => $data['source'],
                'shipping' => $data['shipping'] ?? 0,
                'created_by' => $createdBy->id,
                ...$billing,
                ...$shipping,
            ]);
            // status / payment_status / grand_total are NOT fillable — forced server-side
            $order->forceFill([
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'grand_total' => 0,
            ])->save();

            foreach ($data['lines'] as $lineData) {
                $listing = ProductListing::with('product')->findOrFail($lineData['product_listing_id']);

                $unitPrice = (float) $lineData['unit_price'];
                $taxAmount = (float) ($lineData['tax_amount'] ?? 0);

                $line = OrderLine::create([
                    'order_id' => $order->id,
                    'product_listing_id' => $listing->id,
                    'sku' => $listing->product->sku,
                    'product_name' => $listing->product->name,
                    'inventory_serial_id' => null,
                    'unit_price' => $unitPrice,
                    'tax_amount' => $taxAmount,
                    'line_total' => round($unitPrice + $taxAmount, 2),
                ]);

                foreach ($lineData['fees'] ?? [] as $feeData) {
                    $feeAmount = (float) $feeData['amount'];
                    $feeTax = (float) ($feeData['tax_amount'] ?? 0);
                    OrderLineFee::create([
                        'order_line_id' => $line->id,
                        'name' => $feeData['name'],
                        'amount' => $feeAmount,
                        'tax_amount' => $feeTax,
                        'fee_total' => round($feeAmount + $feeTax, 2),
                        'created_by' => $createdBy->id,
                    ]);
                }
            }

            $this->recalculateTotals($order);

            $firstLine = $order->lines->first();
            OrderEvent::create([
                'order_id' => $order->id,
                'event' => OrderEventEnum::OrderPlaced,
                'metadata' => [
                    'sku' => $firstLine?->sku,
                    'product_name' => $firstLine?->product_name,
                    'grand_total' => number_format((float) $order->grand_total, 2, '.', ''),
                ],
                'created_by' => $createdBy->id,
            ]);

            return $order->refresh();
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

            foreach ($data['lines'] as $lineData) {
                $listing = ProductListing::with('product')->findOrFail($lineData['product_listing_id']);

                $unitPrice = (float) $lineData['unit_price'];
                $taxAmount = (float) ($lineData['tax_amount'] ?? 0);

                $line = OrderLine::create([
                    'order_id' => $order->id,
                    'product_listing_id' => $listing->id,
                    'sku' => $listing->product->sku,
                    'product_name' => $listing->product->name,
                    'inventory_serial_id' => null,
                    'unit_price' => $unitPrice,
                    'tax_amount' => $taxAmount,
                    'line_total' => round($unitPrice + $taxAmount, 2),
                ]);

                foreach ($lineData['fees'] ?? [] as $feeData) {
                    $feeAmount = (float) $feeData['amount'];
                    $feeTax = (float) ($feeData['tax_amount'] ?? 0);
                    OrderLineFee::create([
                        'order_line_id' => $line->id,
                        'name' => $feeData['name'],
                        'amount' => $feeAmount,
                        'tax_amount' => $feeTax,
                        'fee_total' => round($feeAmount + $feeTax, 2),
                        'created_by' => $order->created_by,
                    ]);
                }
            }

            $billing = $this->resolveBillingSnapshot(
                isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null,
                PaymentMethod::from($data['payment_method'])
            );
            $shipping = $this->resolveShippingSnapshot(
                isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null
            );

            $order->update([
                'customer_id' => $data['customer_id'],
                'source' => $data['source'],
                'shipping' => $data['shipping'] ?? 0,
                ...$billing,
                ...$shipping,
            ]);

            $this->recalculateTotals($order);

            return $order->refresh();
        });
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if ($order->status !== OrderStatus::Pending) {
                throw new \DomainException('Only pending orders can be deleted.');
            }

            // LogsActivity trait fires 'deleted' event before the row is wiped — audit log persists.
            $order->delete();
        });
    }

    public function recordCashPayment(Order $order, array $data, User $createdBy): Payment
    {
        $payment = DB::transaction(function () use ($order, $data, $createdBy): Payment {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Pending) {
                throw new \DomainException('Order is not pending.');
            }
            if ($locked->payment_status === PaymentStatus::Paid) {
                throw new \DomainException('Order is already paid.');
            }
            if (round((float) $data['amount'], 2) !== round((float) $locked->grand_total, 2)) {
                throw new \DomainException('Payment amount must equal the order grand total.');
            }

            // Allocate serials at payment (moved from store() per #6)
            $locked->load('lines.productListing');
            foreach ($locked->lines as $line) {
                if ($line->inventory_serial_id !== null) {
                    continue;
                }
                $serialId = $this->allocateSerial($line->productListing);
                $line->update(['inventory_serial_id' => $serialId]);
            }

            // For cash walk-in, payment = sale: record movement + flip serial to sold
            $locked->load('customer', 'lines');
            $customerName = $locked->customer?->name ?? 'customer';
            foreach ($locked->lines as $line) {
                if ($line->inventory_serial_id !== null) {
                    $this->movements->recordSale(
                        $line->inventory_serial_id,
                        $locked,
                        $createdBy,
                        "cash sale to {$customerName} at counter"
                    );
                }
            }

            $payment = new Payment;
            $payment->fill([
                'order_id' => $locked->id,
                'payable_type' => 'order',
                'payable_id' => $locked->id,
                'method' => PaymentMethod::Cash,
                'amount' => $data['amount'],
                'status' => PaymentStatus::Paid,
            ]);
            // cash_received_at + created_by NOT fillable — forced server-side
            $payment->forceFill([
                'cash_received_at' => now(),
                'created_by' => $createdBy->id,
            ])->save();

            $locked->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Processing,
            ])->save();

            OrderEvent::create([
                'order_id' => $locked->id,
                'event' => OrderEventEnum::PaymentReceived,
                'metadata' => [
                    'method' => 'cash',
                    'amount' => number_format((float) $data['amount'], 2, '.', ''),
                    'shipping' => number_format((float) $locked->shipping, 2, '.', ''),
                ],
                'created_by' => $createdBy->id,
            ]);

            activity()
                ->performedOn($locked)
                ->causedBy($createdBy)
                ->event('payment_recorded')
                ->withProperties(['amount' => $data['amount']])
                ->log('Cash payment recorded');

            return $payment;
        });

        // AvaTax SalesInvoice commit — synchronous, best-effort. Failure is logged
        // inside the service; the recorded payment is not rolled back.
        $order->loadMissing('lines.lineFees');
        $taxLines = [];
        foreach ($order->lines as $line) {
            $taxLines[] = ['unit_price' => (float) $line->unit_price, 'sku' => (string) $line->sku];
            foreach ($line->lineFees as $fee) {
                $taxLines[] = ['unit_price' => (float) $fee->amount, 'sku' => 'FEE-'.$fee->name];
            }
        }

        $shipTo = [
            'address_line1' => $order->shipping_address_line1 ?: $order->billing_address_line1,
            'city' => $order->shipping_city ?: $order->billing_city,
            'state' => $order->shipping_state ?: $order->billing_state,
            'postal_code' => $order->shipping_postal_code ?: $order->billing_postal_code,
            'country' => $order->shipping_country ?: $order->billing_country,
        ];

        $order->loadMissing('customer');
        $entityUseCode = ($order->customer && $order->customer->tax_exempt)
            ? $order->customer->entity_use_code
            : null;

        $this->avaTax->commitInvoice($taxLines, $shipTo, (string) $order->customer_id, $order->number, $entityUseCode);

        return $payment;
    }

    public function complete(Order $order, User $completedBy): Order
    {
        return DB::transaction(function () use ($order, $completedBy): Order {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Processing) {
                throw new \DomainException('Only processing orders can be completed.');
            }

            // Inventory work (movement + serial flip) already happened at recordCashPayment().
            // complete() is now a pure status-finalization step.
            $order->forceFill(['status' => OrderStatus::Complete])->save();

            OrderEvent::create([
                'order_id' => $order->id,
                'event' => OrderEventEnum::Completed,
                'metadata' => [],
                'created_by' => $completedBy->id,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($completedBy)
                ->event('completed')
                ->log('Order completed');

            return $order->refresh();
        });
    }

    // ── private helpers ──────────────────────────────────────────────────

    private function generateNumber(): string
    {
        $year = now()->year;
        $prefix = "ORD-{$year}-";
        $last = Order::where('number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $seq = $last
            ? ((int) substr($last->number, -4)) + 1
            : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function recalculateTotals(Order $order): void
    {
        $order->load('lines.lineFees');

        $lineTotals = $order->lines->sum(fn ($l) => (float) $l->line_total);
        $feeTotals = $order->lines->flatMap->lineFees->sum(fn ($f) => (float) $f->fee_total);

        $order->forceFill([
            'grand_total' => round($lineTotals + $feeTotals + (float) $order->shipping, 2),
        ])->save();
    }

    private function allocateSerial(ProductListing $listing): int
    {
        $serial = InventorySerial::query()
            ->where('product_id', $listing->product_id)
            ->where('status', SerialStatus::InStock)
            ->whereNotIn('id', function ($q) {
                $q->select('inventory_serial_id')
                    ->from('order_lines')
                    ->whereNotNull('inventory_serial_id');
            })
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if ($serial === null) {
            throw new \DomainException("No in-stock serial available for {$listing->product->sku}.");
        }

        return $serial->id;
    }

    private function resolveBillingSnapshot(?int $addressId, PaymentMethod $method): array
    {
        // Customer choice wins — if an address_id is provided, use it regardless of payment method.
        if ($addressId !== null) {
            $a = CustomerAddress::findOrFail($addressId);

            return [
                'billing_first_name' => $a->first_name,
                'billing_last_name' => $a->last_name,
                'billing_email' => $a->email,
                'billing_phone' => $a->phone,
                'billing_address_line1' => $a->address_line1,
                'billing_address_line2' => $a->address_line2,
                'billing_city' => $a->city,
                'billing_state' => $a->state,
                'billing_postal_code' => $a->postal_code,
                'billing_country' => $a->country,
            ];
        }

        // No address chosen — fall back to shop billing for cash sales.
        if ($method === PaymentMethod::Cash) {
            $cfg = config('shop.billing');

            return [
                'billing_first_name' => $cfg['first_name'],
                'billing_last_name' => null,
                'billing_email' => $cfg['email'],
                'billing_phone' => $cfg['phone'],
                'billing_address_line1' => $cfg['address_line1'],
                'billing_address_line2' => null,
                'billing_city' => $cfg['city'],
                'billing_state' => $cfg['state'],
                'billing_postal_code' => $cfg['postal_code'],
                'billing_country' => $cfg['country'],
            ];
        }

        return [];
    }

    private function resolveShippingSnapshot(?int $addressId): array
    {
        if ($addressId === null) {
            return [
                'shipping_first_name' => null,
                'shipping_last_name' => null,
                'shipping_email' => null,
                'shipping_phone' => null,
                'shipping_address_line1' => null,
                'shipping_address_line2' => null,
                'shipping_city' => null,
                'shipping_state' => null,
                'shipping_postal_code' => null,
                'shipping_country' => null,
            ];
        }

        $a = CustomerAddress::findOrFail($addressId);

        return [
            'shipping_first_name' => $a->first_name,
            'shipping_last_name' => $a->last_name,
            'shipping_email' => $a->email,
            'shipping_phone' => $a->phone,
            'shipping_address_line1' => $a->address_line1,
            'shipping_address_line2' => $a->address_line2,
            'shipping_city' => $a->city,
            'shipping_state' => $a->state,
            'shipping_postal_code' => $a->postal_code,
            'shipping_country' => $a->country,
        ];
    }
}
