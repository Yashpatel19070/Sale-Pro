# Order Module — Request to Data Flow Diagram

**Scenario:** Ex-19 — Walk-in Cash, In-Store Pickup, No Address
Three HTTP actions cover the complete lifecycle: `store` → `recordCashPayment` → `complete`

---

## Files Overview

### CREATE — New Files (31 total)

```
database/migrations/
├── xxxx_create_orders_table.php
├── xxxx_create_order_lines_table.php
├── xxxx_create_order_fees_table.php
├── xxxx_create_payments_table.php
└── xxxx_create_order_events_table.php

app/Enums/
├── OrderStatus.php       — pending, back_ordered, processing, shipped, complete, cancelled, refunded, rts
├── OrderSource.php       — online, walk_in, phone
├── PaymentMethod.php     — cash, stripe_card, stripe_terminal, stripe_checkout, cheque
└── PaymentStatus.php     — unpaid, paid

app/Models/
├── Order.php             — hasMany OrderLine, OrderFee, Payment, OrderEvent
├── OrderLine.php         — belongsTo Order, InventorySerial
├── OrderFee.php          — belongsTo Order
├── Payment.php           — morphTo payable, belongsTo Order
└── OrderEvent.php        — belongsTo Order, User (append-only)

database/factories/
├── OrderFactory.php
├── OrderLineFactory.php
├── OrderFeeFactory.php
├── PaymentFactory.php
└── OrderEventFactory.php

app/Http/Controllers/
└── OrderController.php   — 9 methods

app/Services/
└── OrderService.php      — 9 methods (6 public, 3 private)

app/Http/Requests/Order/
├── StoreOrderRequest.php
├── UpdateOrderRequest.php
└── RecordCashPaymentRequest.php

app/Policies/
└── OrderPolicy.php       — 7 policy methods

resources/views/orders/
├── index.blade.php
├── create.blade.php
├── show.blade.php
└── edit.blade.php

tests/Unit/
└── OrderServiceTest.php

tests/Feature/
└── OrderControllerTest.php
```

### MODIFY — Existing Files (4 total)

```
app/Enums/Permission.php
└── + ViewOrders, CreateOrders, ManageOrders

app/Providers/AppServiceProvider.php
├── + Order::class => OrderPolicy::class                           (in $policies array via Gate::policy)
└── + Relation::enforceMorphMap(['order' => Order::class])         (in boot() — stores "order" as payable_type)

routes/web.php
└── + Route::resource('orders', OrderController::class)
    + Route::post('orders/{order}/cash-payment', ...)  → orders.cash-payment
    + Route::post('orders/{order}/complete', ...)       → orders.complete

app/Services/InventoryMovementService.php
└── + recordSale(int $serialId, Order $order, User $by): InventoryMovement
```

---

## Flow 1 — Create Order

**Route:** `POST /admin/orders`

```
Browser
  │
  │  POST /admin/orders
  │  {
  │    customer_id: 19,
  │    source: "walk_in",
  │    payment_method: "cash",
  │    shipping_address_id: null,      ← null = in-store pickup → shipping snapshot NULL
  │    shipping: 0,
  │    lines: [{ product_listing_id: PROD-C.id, unit_price: 170.00, tax_rate: 0 }],
  │    fees:  [{ name: "Service Fee", amount: 15.00 }]
  │  }
  │
  │  (if carrier delivery: shipping_address_id: 20 → service copies address to snapshot)
  ▼
StoreOrderRequest
  ├── authorize()
  │     └── $user->can('create', Order::class)
  │           └── OrderPolicy::create()
  │                 └── $user->hasPermissionTo(Permission::CreateOrders) ✓ or ✗ → 403
  │
  └── rules()
        ├── customer_id          required | exists:customers,id
        ├── source               required | Rule::enum(OrderSource::class)
        ├── payment_method       required | Rule::enum(PaymentMethod::class)
        ├── shipping_address_id  nullable | exists:customer_addresses,id
        │     null  → in-store pickup → shipping snapshot NULL
        │     set   → carrier delivery → copy address to snapshot
        ├── shipping             nullable | numeric | min:0
        ├── lines           required | array | min:1
        ├── lines.*.product_listing_id   required | exists:product_listings,id
        ├── lines.*.unit_price           required | numeric | min:0
        ├── lines.*.tax_rate             required | numeric | min:0
        ├── fees            nullable | array
        ├── fees.*.name     required_with:fees | string
        └── fees.*.amount   required_with:fees | numeric | min:0
              │
              └── Validation fails → back with errors (422)
  │
  ▼
OrderController::store(StoreOrderRequest $request)
  └── try
        └── $this->service->store($request->validated(), $request->user())
      catch (\DomainException $e)
        └── back()->withErrors(['error' => $e->getMessage()])->withInput()
  │
  ▼
OrderService::store(array $data, User $createdBy)
  │
  └── DB::transaction
        │
        ├── generateNumber()
        │     ├── Order::withTrashed()
        │     │         ->where('number', 'like', 'ORD-{year}-%')
        │     │         ->lockForUpdate()->max('number')
        │     ├── extract suffix → increment → str_pad(4) → "ORD-2026-0001"
        │     └── ← inside outer transaction — rolls back with everything if store() fails
        │
        ├── Order::create()
        │     └── orders INSERT
        │           ├── number           = "ORD-2026-0001"
        │           ├── customer_id      = 19
        │           ├── source           = walk_in
        │           ├── status           = pending
        │           ├── payment_status   = unpaid
        │           ├── created_by       = 1
        │           ├── subtotal         = 0.00 (recalculated after)
        │           ├── fees             = 0.00 (recalculated after)
        │           ├── shipping         = 0.00
        │           ├── grand_total      = 0.00 (recalculated after)
        │           ├── billing_*        = NULL (cash — billing always NULL)
        │           ├── shipping_*       = NULL (shipping_address_id=null → in-store pickup)
        │           │                           (if shipping_address_id set → copy address fields here)
        │           ├── shipped_at       = NULL
        │           ├── shipped_by       = NULL
        │           ├── delivered_at     = NULL
        │           └── delivered_by     = NULL
        │
        ├── foreach $data['lines'] as $line
        │     │
        │     ├── $listing = ProductListing::with('product')->findOrFail($line['product_listing_id'])
        │     │     ├── $listing->product->sku    → "PROD-C"       ← snapshot source
        │     │     └── $listing->product->name   → "Widget Basic"  ← snapshot source
        │     │
        │     ├── [AUTO-ASSIGN] InventorySerial::where('product_id', $listing->product_id)
        │     │                               ->where('status', 'in_stock')
        │     │                               ->lockForUpdate()->firstOrFail()
        │     │     └── throw DomainException if none available (out of stock)
        │     │
        │     └── OrderLine::create()
        │           └── order_lines INSERT
        │                 ├── order_id              = 19
        │                 ├── product_listing_id    = $listing->id       ← reporting link
        │                 ├── inventory_serial_id   = SN-200.id          ← serial reserved
        │                 ├── sku                   = "PROD-C"           ← from listing→product
        │                 ├── product_name          = "Widget Basic"     ← from listing→product
        │                 ├── unit_price            = 170.00
        │                 ├── tax_rate              = 0.0000
        │                 ├── tax_amount            = 0.00
        │                 └── line_total            = 170.00
        │
        │   ── inventory_serials.status stays in_stock ──
        │   ── NO inventory_movements row created ──
        │
        ├── OrderFee::create()
        │     └── order_fees INSERT
        │           ├── order_id = 19
        │           ├── name     = "Service Fee"
        │           └── amount   = 15.00
        │
        ├── recalculateTotals()
        │     └── orders UPDATE
        │           ├── subtotal    = 170.00
        │           ├── fees        = 15.00
        │           └── grand_total = 185.00
        │
        └── OrderEvent::create()
              └── order_events INSERT
                    ├── order_id   = 19
                    ├── event      = "order_placed"
                    ├── metadata   = {"sku":"PROD-C","product_name":"Widget Basic","grand_total":"185.00"}
                    └── created_by = 1
  │
  ▼
DB state after Flow 1
  orders:             1 row  — status=pending, payment_status=unpaid
  order_lines:        1 row  — serial assigned, status still in_stock
  order_fees:         1 row
  payments:           0 rows
  inventory_movements: 0 rows
  inventory_serials:  SN-200 status = in_stock (unchanged)
  order_events:       1 row  — order_placed
  │
  ▼
OrderController
  └── redirect()->route('orders.show', $order)->with('success', 'Order ORD-2026-0001 created.')
```

---

## Flow 2 — Record Cash Payment

**Route:** `POST /admin/orders/{order}/cash-payment`

```
Browser
  │
  │  POST /admin/orders/19/cash-payment
  │  {
  │    amount: 185.00
  │  }
  ▼
RecordCashPaymentRequest
  ├── authorize()
  │     └── $user->can('recordCashPayment', $order)
  │           └── OrderPolicy::recordCashPayment()
  │                 ├── $user->hasPermissionTo(Permission::ManageOrders) ✓ or ✗ → 403
  │                 └── $order->payment_status == unpaid              ✓ or ✗ → 403
  │
  └── rules()
        └── amount           required | numeric | min:0.01
              │
              └── Validation fails → back with errors (422)
  │
  ▼
OrderController::recordCashPayment(RecordCashPaymentRequest $request, Order $order)
  └── try
        └── $this->service->recordCashPayment($order, $request->validated(), $request->user())
      catch (\DomainException $e)
        └── back()->withErrors(['error' => $e->getMessage()])->withInput()
  │
  ▼
OrderService::recordCashPayment(Order $order, array $data, User $createdBy)
  │
  └── DB::transaction
        │
        ├── [GUARD] Order::lockForUpdate()->find($order->id)
        │     └── throw DomainException if payment_status == paid
        │
        ├── Payment::create()
        │     └── payments INSERT
        │           ├── order_id         = 19
        │           ├── payable_type     = "order"
        │           ├── payable_id       = 19
        │           ├── method           = cash
        │           ├── amount           = 185.00
        │           ├── status           = paid
        │           ├── cash_received_at = now()   ← set by service, not from request
        │           └── created_by       = 1
        │
        ├── orders UPDATE
        │     └── payment_status → paid
        │
        ├── advanceToProcessingIfReady()
        │     ├── check: all order_lines.inventory_serial_id IS NOT NULL ✓
        │     ├── check: payment_status = paid ✓
        │     └── orders UPDATE → status = processing
        │
        │   ── inventory_serials.status stays in_stock ──
        │   ── NO inventory_movements row created ──
        │
        └── OrderEvent::create()
              └── order_events INSERT
                    ├── order_id   = 19
                    ├── event      = "payment_received"
                    ├── metadata   = {"method":"cash","amount":"185.00","subtotal":"170.00","fees":"15.00","shipping":"0.00"}
                    └── created_by = 1
  │
  ▼
DB state after Flow 2
  orders:              status=processing, payment_status=paid
  payments:            1 row  — method=cash, status=paid
  inventory_movements: 0 rows (still none)
  inventory_serials:   SN-200 status = in_stock (still unchanged)
  order_events:        2 rows — order_placed, payment_received
  │
  ▼
OrderController
  └── redirect()->route('orders.show', $order)->with('success', 'Payment recorded.')
```

---

## Flow 3 — Complete Order (Hand Over Unit)

**Route:** `POST /admin/orders/{order}/complete`

```
Browser
  │
  │  POST /admin/orders/19/complete
  │  (no body — action only)
  ▼
OrderController::complete(Request $request, Order $order)
  ├── $this->authorize('complete', $order)
  │     └── OrderPolicy::complete()
  │           ├── $user->hasPermissionTo(Permission::ManageOrders) ✓ or ✗ → 403
  │           └── $order->status == processing                     ✓ or ✗ → 403
  │
  └── try
        └── $this->service->complete($order, $request->user())
      catch (\DomainException $e)
        └── back()->withErrors(['error' => $e->getMessage()])->withInput()
  │
  ▼
OrderService::complete(Order $order, User $completedBy)
  │
  └── DB::transaction
        │
        ├── [GUARD] throw DomainException if order.status != processing
        │
        ├── $order->loadMissing('lines:id,order_id,inventory_serial_id')
        │     └── loads only what we need — serial ID from each line
        │
        ├── foreach $order->lines as $line
        │     │   (Ex-19 has 1 line — loop runs once)
        │     │
        │     └── InventoryMovementService::recordSale($line->inventory_serial_id, $order, $completedBy)
        │             │
        │             ├── [GUARD] InventorySerial::lockForUpdate()->findOrFail($serialId)
        │             │     └── throw DomainException if serial.status != in_stock
        │             │
        │             ├── inventory_movements INSERT
        │             │     ├── inventory_serial_id = SN-200.id
        │             │     ├── type               = sale
        │             │     ├── from_location_id   = Warehouse A location id
        │             │     ├── to_location_id     = NULL  (customer takes it)
        │             │     ├── reference          = "ORD-2026-0001"
        │             │     └── notes              = "picked up at counter"
        │             │
        │             └── inventory_serials UPDATE
        │                   └── SN-200 status → sold
        │
        │   ── NO shipments row created ──
        │
        ├── orders UPDATE
        │     └── status → complete
        │
        └── OrderEvent::create()
              └── order_events INSERT
                    ├── order_id   = 19
                    ├── event      = "completed"
                    ├── metadata   = {}
                    └── created_by = 1
  │
  ▼
DB state after Flow 3 (final)
  orders:              status=complete, payment_status=paid
  order_lines:         1 row  — inventory_serial_id = SN-200.id
  order_fees:          1 row
  payments:            1 row  — method=cash, status=paid
  inventory_movements: 1 row  — type=sale, SN-200, Warehouse A → NULL
  inventory_serials:   SN-200 status = sold
  shipments:           0 rows (in-store pickup — no carrier)
  order_events:        3 rows — order_placed, payment_received, completed
  │
  ▼
OrderController
  └── redirect()->route('orders.show', $order)->with('success', 'Order completed.')
```

---

## Error Paths

| Where | Condition | Result |
|-------|-----------|--------|
| `StoreOrderRequest::authorize()` | No `CreateOrders` permission | 403 Forbidden |
| `StoreOrderRequest::rules()` | Missing required fields | Back with validation errors |
| `OrderService::store()` | No in_stock serial available for product | DomainException → back with error |
| `RecordCashPaymentRequest::authorize()` | No `ManageOrders` permission | 403 Forbidden |
| `RecordCashPaymentRequest::authorize()` | `payment_status = paid` | 403 Forbidden |
| `OrderService::recordCashPayment()` | Payment already exists (race) | DomainException → back with error |
| `OrderController::complete()::authorize()` | No `ManageOrders` permission | 403 Forbidden |
| `OrderController::complete()::authorize()` | `status != processing` | 403 Forbidden |
| `InventoryMovementService::recordSale()` | Serial grabbed by another order (race) | DomainException → full rollback |

---

## Method Map

### `OrderController`
| Method | HTTP | Route |
|--------|------|-------|
| `index` | GET | `/admin/orders` |
| `create` | GET | `/admin/orders/create` |
| `store` | POST | `/admin/orders` |
| `show` | GET | `/admin/orders/{order}` |
| `edit` | GET | `/admin/orders/{order}/edit` |
| `update` | PUT | `/admin/orders/{order}` |
| `destroy` | DELETE | `/admin/orders/{order}` |
| `recordCashPayment` | POST | `/admin/orders/{order}/cash-payment` |
| `complete` | POST | `/admin/orders/{order}/complete` |

### `OrderService`
| Method | Visibility | Called by |
|--------|-----------|-----------|
| `store` | public | `OrderController::store` |
| `update` | public | `OrderController::update` |
| `delete` | public | `OrderController::destroy` |
| `recordCashPayment` | public | `OrderController::recordCashPayment` |
| `complete` | public | `OrderController::complete` |
| `paginate` | public | `OrderController::index` |
| `generateNumber` | private | `store` |
| `recalculateTotals` | private | `store`, `update` |
| `advanceToProcessingIfReady` | private | `recordCashPayment` |

### `InventoryMovementService` (modified)
| Method | Signature | Called by |
|--------|-----------|-----------|
| `recordSale` | `recordSale(int $serialId, Order $order, User $by): InventoryMovement` | `OrderService::complete` |

### `OrderPolicy`
| Method | Permission | Extra guard |
|--------|-----------|-------------|
| `viewAny` | `ViewOrders` | — |
| `view` | `ViewOrders` | — |
| `create` | `CreateOrders` | — |
| `update` | `ManageOrders` | `status = pending` |
| `delete` | `ManageOrders` | `status = pending` |
| `recordCashPayment` | `ManageOrders` | `payment_status = unpaid` |
| `complete` | `ManageOrders` | `status = processing` |
