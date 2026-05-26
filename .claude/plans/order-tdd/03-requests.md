# Order Module — FormRequests

---

## `StoreOrderRequest`

File: `app/Http/Requests/Order/StoreOrderRequest.php`

### `authorize()`
```php
return $this->user()->can('create', Order::class);
```

### Validation Rules

| Field | Rules | Notes |
|-------|-------|-------|
| `customer_id` | required, integer, exists:customers,id | |
| `source` | required, `Rule::enum(OrderSource::class)` | |
| `payment_method` | required, `Rule::enum(PaymentMethod::class)` | |
| `billing_address_id` | nullable, integer, exists:customer_addresses,id | NULL = no billing address → billing snapshot NULL. Set = copy address to billing_* snapshot columns |
| `shipping_address_id` | nullable, integer, exists:customer_addresses,id | NULL = in-store pickup → shipping snapshot NULL. Set = carrier delivery → copy address to shipping_* snapshot columns |
| `shipping` | nullable, numeric, min:0 | Shipping cost. Default 0.00 |
| `lines` | required, array, min:1 | At least one line |
| `lines.*.product_listing_id` | required, integer, `Rule::exists('product_listings','id')->where('is_active',true)` | Serial assigned at recordCashPayment(), not store() — no serial picker on form |
| `lines.*.unit_price` | required, numeric, min:0 | |
| `lines.*.tax_rate` | required, numeric, min:0, max:100 | |
| `fees` | nullable, array | |
| `fees.*.name` | required_with:fees, string, max:100 | |
| `fees.*.amount` | required_with:fees, numeric, min:0 | |

---

## `UpdateOrderRequest`

File: `app/Http/Requests/Order/UpdateOrderRequest.php`

### `authorize()`
```php
$order = $this->route('order');
return $this->user()->can('update', $order);
```

### `prepareForValidation()`
Policy already guards status — no extra preparation needed.

### Validation Rules
Same fields as `StoreOrderRequest`. Source and payment_method cannot change after creation — exclude from update rules:

| Field | Rules |
|-------|-------|
| `shipping` | nullable, numeric, min:0 |
| `lines` | required, array, min:1 |
| `lines.*.product_listing_id` | required, integer, `Rule::exists('product_listings','id')->where('is_active',true)` |
| `lines.*.unit_price` | required, numeric, min:0 |
| `lines.*.tax_rate` | required, numeric, min:0, max:100 |
| `fees` | nullable, array |
| `fees.*.name` | required_with:fees, string, max:100 |
| `fees.*.amount` | required_with:fees, numeric, min:0 |

> `source` and `payment_method` are immutable after creation — not accepted in update.

---

## `RecordCashPaymentRequest`

File: `app/Http/Requests/Order/RecordCashPaymentRequest.php`

### `authorize()`
```php
$order = $this->route('order');
return $this->user()->can('recordCashPayment', $order);
```

### Validation Rules

| Field | Rules | Notes |
|-------|-------|-------|
| `amount` | required, numeric, min:0.01 | Should match `order.grand_total` — validated in service |

> `cash_received_at` is not accepted from the request — service sets it to `now()` internally.
