# AvaTax Integration — Overview

## Goal

Replace the manual `tax_rate × unit_price` calculation in `OrderService::buildLines()` with
real-time tax calculation from Avalara AvaTax. `order_lines` already has `tax_rate` and
`tax_amount` columns — no schema changes needed. AvaTax simply populates those fields.

---

## Package

```
composer require avalara/avataxclient
```

- Package: `avalara/avataxclient` (official Avalara SDK — avadev org, Apache 2.0)
- Latest: `26.5.0` (May 2026, monthly releases — most active)
- **Do NOT use** `oscar-team/avatax-laravel` — it's a thin third-party wrapper that pins you
  to `^25.6` and adds no meaningful abstraction over what we write ourselves.
- PHP ≥ 5.5.9, requires `guzzlehttp/guzzle ~6|~7` (already in Laravel)

---

## SDK Key Classes

```
Avalara\AvaTaxClient           — main HTTP client
Avalara\TransactionBuilder     — fluent builder for create-transaction calls
Avalara\DocumentType           — constants: C_SALESINVOICE, C_SALESORDER
```

### Client initialisation

```php
$client = new AvaTaxClient('sale-pro', '1.0', gethostname(), config('services.avatax.environment'));
$client->withSecurity(config('services.avatax.account_number'), config('services.avatax.license_key'));
```

### TransactionBuilder — key methods

| Method | Purpose |
|--------|---------|
| `new TransactionBuilder($client, $companyCode, $docType, $customerCode)` | Start a transaction |
| `->withTransactionCode($code)` | Set unique code (use order number) |
| `->withAddress('ShipFrom', $line1, null, null, $city, $state, $zip, $country)` | Seller/warehouse address |
| `->withAddress('ShipTo', $line1, null, null, $city, $state, $zip, $country)` | Customer delivery address |
| `->withLine($amount, $qty, $itemCode, $taxCode)` | Add a line item |
| `->withCommit()` | Set `commit = true` (records permanently in AvaTax) |
| `->create()` | POST to API, returns `TransactionModel` (stdClass) |

### TransactionModel — fields we use

| Field | Type | Meaning |
|-------|------|---------|
| `$t->totalTax` | float | Total tax across all lines |
| `$t->lines[N]->tax` | float | Tax amount for line N |
| `$t->lines[N]->details[0]->rate` | float | Effective tax rate for line N |
| `$t->code` | string | Transaction code (echoes back our order number) |

### commitTransaction

```php
$client->commitTransaction(
    $companyCode,
    $transactionCode,   // order number
    DocumentType::C_SALESINVOICE,
    '',                 // $include
    ['commit' => true]  // CommitTransactionModel
);
```

### voidTransaction

```php
$client->voidTransaction(
    $companyCode,
    $transactionCode,   // order number
    DocumentType::C_SALESINVOICE,
    '',                 // $include
    ['code' => 'DocVoided']  // VoidTransactionModel — voidReasonCode
);
```

---

## Two-Phase Transaction Strategy

| Phase | When | SDK call | Document type | commit flag |
|-------|------|----------|---------------|-------------|
| Estimate | Order creation | `TransactionBuilder->create()` | `C_SALESINVOICE` | `false` (provisional) |
| Commit | Payment received | `commitTransaction()` | `C_SALESINVOICE` | `true` (permanent) |
| Void | Order cancelled | `voidTransaction()` | `C_SALESINVOICE` | n/a |

Using `SalesInvoice` with `commit=false` at creation (not `SalesOrder`) so we have
a single transaction code throughout the lifecycle and `commitTransaction()` is a
one-call operation.

---

## Design Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Wrap SDK | `AvaTaxService` class | PHP patterns rule: wrap third-party SDKs, `OrderService` never touches SDK |
| Tax column on `orders` | Add `tax decimal(12,2)` | Visible for display, reports, and future refund calculations |
| `subtotal` meaning | Change to **pre-tax** (sum of `unit_price` only) | With `tax` as a separate column, subtotal should be pre-tax |
| `grand_total` formula | `subtotal + tax + fees + shipping` | Matches industry standard, each component visible |
| HTTP call in DB transaction | **Never** — call AvaTax before opening transaction | DB transactions must be short; HTTP latency would lock rows |
| `lines.*.tax_rate` in request | Make **nullable** | When `AVATAX_ENABLED=false`, callers supply their own rate (fallback) |
| Failure at order creation | **Hard fail** — throw exception | Never accept an order with unknown tax amount |
| Fallback mode | `AVATAX_ENABLED=false` → use request-supplied `tax_rate` | Allows sandbox/test operation without Avalara credentials |
| Commit timing | After `recordCashPayment()` DB commit, **outside** transaction | Follows same pattern as all future payment methods |

---

## Impact Summary

Files that change:

| File | Change type |
|------|-------------|
| `config/services.php` | Add `avatax` block |
| `.env` / `.env.example` | Add 7 AvaTax env keys |
| `app/Services/AvaTaxService.php` | **New** — SDK wrapper |
| `app/Providers/AppServiceProvider.php` | Bind `AvaTaxService` in container |
| `app/Services/OrderService.php` | Inject `AvaTaxService`, fix `buildLines()`, fix `create()`, add commit call |
| `app/Http/Requests/Order/CreateOrderRequest.php` | `lines.*.tax_rate` → nullable |
| `tests/Unit/AvaTaxServiceTest.php` | **New** — unit tests with mocked HTTP |
| `tests/Unit/OrderServiceTest.php` | Update — mock `AvaTaxService` |

---

## Phases

| Phase | Files | Plan file |
|-------|-------|-----------|
| 1 — Package & Config | `composer.json`, `config/services.php`, `.env.example` | ✅ done |
| ~~2 — Schema~~| ~~dropped — `order_lines` already has `tax_rate` + `tax_amount`~~ | — |
| 2 — AvaTaxService | `app/Services/AvaTaxService.php`, `AppServiceProvider` | [02-service.md](02-service.md) |
| 3 — Integration | `OrderService`, `CreateOrderRequest` | [03-integration.md](03-integration.md) |
| 4 — Tests | `AvaTaxServiceTest`, `OrderServiceTest` updates | [04-tests.md](04-tests.md) |
