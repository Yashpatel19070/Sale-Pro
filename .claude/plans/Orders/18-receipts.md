# 18 — Receipts (Print-Friendly Blade View)

> **Layer 5 — Presentation.** Depends on `04-models.md`, `10-routes.md`, `11-controller.md`, `12-views.md`.

## Scope

Defines the print-friendly receipt view for an order. Browser-renderable HTML that prints cleanly via `window.print()` and can be sent as an email attachment (as HTML).

- New view: `resources/views/orders/receipt.blade.php`
- New route: `GET /admin/orders/{order}/receipt` → `orders.receipt`
- New controller action: `OrderController::receipt(Order $order): View`
- Linked from `show.blade.php` "Print Receipt" button (per `12-views.md`)

**Partial-code file** — layout structure + section breakdown described. Full HTML deferred to implementation. No PHP method bodies.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| **Print-friendly Blade view (Option A)** — NO PDF library | No new dependency (`barryvdh/laravel-dompdf` deferred); browser handles print |
| Receipt view extends a minimal layout (NOT the admin sidebar layout) | Print should not show admin navigation chrome |
| Tailwind `@media print` utilities used to hide screen-only elements | Standard pattern |
| Receipt available for ANY order status via direct URL | Customer may need receipt at any time |
| Show page button "Print Receipt" displayed only when `status === Complete` | UI guides admin; URL still works for other statuses |
| Receipt is read-only — no buttons, no actions, no JS state | Designed to print as-is |
| Inline `window.print()` button visible on screen only — hidden in print | Quality-of-life for admin |
| Customer info shown: name, email, phone | Standard receipt content |
| Shop letterhead at top — name + address pulled from `config('shop.billing')`. **Wrapped in `@if(config('shop.billing.first_name'))`; if shop config unset, the entire letterhead block is omitted and the receipt jumps straight to the "Receipt" header.** | Single source of truth + multi-tenant friendly |
| Per-line fees rendered nested under each line in the receipt | Matches show page layout (per `12-views.md`) |
| `tax_amount` shown per row (unit + each fee), not summed | Customer sees exactly what tax was applied where |
| Grand total prominently displayed at the bottom | Bold, larger font |
| Payment info shown if at least one payment exists | Cash amount + received-at timestamp |
| No customer signature line | Out of scope for ex-19 (walk-in cash, no signature required) |
| Footer: "Thank you" message + return policy reference (optional) | Standard receipt practice |

---

## File locations

```
resources/views/orders/receipt.blade.php
resources/views/layouts/receipt.blade.php          (NEW minimal print layout)
```

Controller + route additions:

```
app/Http/Controllers/OrderController.php           (add receipt method)
routes/web.php                                      (add orders.receipt route)
```

---

## New route (added to `10-routes.md` spec)

```php
Route::get('orders/{order}/receipt', [OrderController::class, 'receipt'])
    ->name('orders.receipt');
```

> Added inside the admin middleware group, AFTER `Route::resource('orders', ...)` and other `{order}` action routes.

---

## New controller action (extends `11-controller.md`)

### `receipt(Order $order): View`

| Aspect | Value |
|--------|-------|
| Authorize | `view` on `$order` |
| Eager loads | `customer`, `lines.lineFees`, `lines.inventorySerial`, `payments`, `createdBy` |
| View data | `order` (loaded) |
| Returns | `view('orders.receipt', compact('order'))` |
| Layout | Uses `<x-receipt-layout>` (minimal — no admin chrome) |

---

## New minimal layout: `layouts/receipt.blade.php`

Lightweight layout for print-ready pages. Contains:

- `<!DOCTYPE html>` + meta tags
- Tailwind CSS link (compiled bundle)
- `<title>{{ $title ?? 'Receipt' }}</title>`
- `@media print` CSS rules:
  - Hide `.no-print` elements (e.g., the print button)
  - Force black text on white background
  - Remove shadows / hover styles
- `@yield('content')`

Used only for receipts (and possibly future printable views — labels, packing slips, etc.).

---

## `orders/receipt.blade.php` — section structure

### Container
`max-w-3xl mx-auto py-8 px-6 print:p-0` — narrower than show page; print-friendly margins.

### Section 1: Shop letterhead (top)

```
┌──────────────────────────────────────────────────┐
│       NPC SALES PRO LLC                          │
│       5426 N Shepherd Dr                         │
│       Houston, TX 77091                          │
│       sales@npcsalespro.com · (713) 555-0100     │
└──────────────────────────────────────────────────┘
```

> Pulled from `config('shop.billing')` — same source as `OrderService::resolveBillingSnapshot()` for consistency.
>
> **When unconfigured:** if `config('shop.billing.first_name')` is falsy (env vars unset), this entire section is suppressed — the view emits no shop name, address, email, or phone. The receipt opens directly with Section 2 (Receipt header).

### Section 2: Receipt header

| Field | Source |
|-------|--------|
| Title | "RECEIPT" |
| Order # | `$order->number` (e.g., `ORD-2026-0019`) |
| Date | `$order->created_at->format('M j, Y g:i A')` |
| Status badge | `$order->status->label()` (small, top-right) |

### Section 3: Customer info

| Field | Source |
|-------|--------|
| Name | `$order->customer->name` |
| Email | `$order->customer->email` |
| Phone | `$order->customer->phone` |

### Section 4: Line items table (read-only, NO inputs)

```
| Description                  | Qty | Unit Price | Tax    | Subtotal |
|------------------------------|-----|-----------|--------|----------|
| Engine Control Module        |  1  | $200.00   | $16.50 | $216.50  |
|   └ Programming Fee          |     | $ 40.00   | $ 3.30 | $ 43.30  |
|   └ Gas Tuning Fee           |     | $ 25.00   | $ 2.06 | $ 27.06  |
|------------------------------|-----|-----------|--------|----------|
```

> Per-line fees indented under their parent line with `└` glyph or visual indent. Qty column always shows `1` (per-line basis).

### Section 5: Totals (right-aligned, below table)

```
                              Sum of line totals:     $216.50
                              Sum of fee totals:    + $ 70.36
                              Shipping:             + $  0.00
                              ─────────────────────────────────
                              GRAND TOTAL:            $286.86
```

> "GRAND TOTAL" bold, larger font. Matches the receipt-style math from ex-19.

### Section 6: Payment info (if payment exists)

| Field | Source |
|-------|--------|
| Payment method | `Cash` (from `$order->payments->first()->method->label()`) |
| Amount paid | `$286.86` |
| Date | `2026-05-25 10:05 AM` (from `cash_received_at`) |
| Status | `PAID` |

### Section 7: Footer

```
                Thank you for your business!
       Returns accepted within 30 days with receipt.
                For support: sales@npcsalespro.com
```

### Section 8: Print button (screen only, hidden in print)

```html
<div class="no-print mt-6 text-center">
    <button onclick="window.print()" class="...">Print Receipt</button>
</div>
```

> `.no-print` class hidden via `@media print { .no-print { display: none; } }` in layout.

---

## ex-19 rendered receipt

Given ex-19's data (line 73, 89-90, 97-99, 123-124), the receipt renders exactly:

```
NPC SALES PRO LLC
5426 N Shepherd Dr
Houston, TX 77091
sales@npcsalespro.com · (713) 555-0100

────────────────────────────────────────────
RECEIPT                            [COMPLETE]
Order ORD-2026-0019
May 25, 2026 · 10:00 AM
────────────────────────────────────────────

Customer:
Rachel Park
rachel@example.com · 555-190-0001

Items:
─────────────────────────────────────────────
Engine Control Module
  Unit Price $200.00 · Tax $16.50 = $216.50

  └ Programming Fee   $40.00 + $3.30 = $43.30
  └ Gas Tuning Fee    $25.00 + $2.06 = $27.06
─────────────────────────────────────────────

                  Line totals:    $216.50
                  Fee totals:   + $ 70.36
                  Shipping:     + $  0.00
                  ─────────────────────────
                  GRAND TOTAL:    $286.86

Payment:
Cash · $286.86 · May 25 10:05 AM · PAID

           Thank you for your business!
```

---

## Dependencies

**Depends on:**
- `04-models.md` — `Order` and its relations
- `10-routes.md` — `orders.receipt` route registration
- `11-controller.md` — `receipt` controller action
- `12-views.md` — show page links to receipt
- Existing: `config('shop.billing')` (per `07-service.md`)

**Depended on by:**
- `12-views.md` — show page "Print Receipt" button links here
- Future: email integration could attach this HTML to customer notification (out of scope)

---

## Validation gates

- [ ] Route `GET /admin/orders/{order}/receipt` registered as `orders.receipt`
- [ ] Controller `receipt()` action authorizes `view` on `$order`
- [ ] Minimal print layout exists at `resources/views/layouts/receipt.blade.php`
- [ ] `.no-print` class hidden via `@media print` CSS
- [ ] Receipt view does NOT extend admin sidebar layout
- [ ] All amounts formatted as `$X.XX`
- [ ] Per-line fees indented under their parent line
- [ ] Shop info pulled from `config('shop.billing')` (NOT hardcoded in Blade)
- [ ] Letterhead block wrapped in `@if(config('shop.billing.first_name'))` — disappears entirely when shop unconfigured
- [ ] Footer "support email" line wrapped in `@if(config('shop.billing.email'))` — shows only when configured
- [ ] No JS state (Alpine `x-data`) — pure read-only display
- [ ] `window.print()` button visible only on screen
- [ ] Renders cleanly when status is `pending`, `processing`, or `complete`

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | Receipt provides |
|--------|------------------|
| `03-schema.md` `orders.grand_total` | Bold final number |
| `03-schema.md` `order_lines.line_total` | "Subtotal" per line |
| `03-schema.md` `order_line_fees.fee_total` | Per-fee subtotal under each line |
| `03-schema.md` `payments.amount` | Payment section |
| `04-models.md` `Order::customer`, `Order::lines`, `OrderLine::lineFees`, `Order::payments` | All eager-loaded by controller |
| `12-views.md` "Print Receipt" button on show page | Links to `orders.receipt` |
| ex-19 final state (lines 165-175) | Receipt content matches the rendered timeline |

No gaps. Receipt provides a clean print artifact for any order at any status.
