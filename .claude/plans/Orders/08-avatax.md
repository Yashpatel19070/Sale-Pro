# 08 — AvaTax Integration

> **Layer 4 — Behavior.** Depends on `01-enums.md`, `03-schema.md`, `07-service.md`, `14-events-inventory.md`.

## Scope

Defines how the Orders module integrates with `AvaTaxService` to compute `tax_amount` for:
- Each `order_lines.unit_price`
- Each `order_line_fees.amount`

All in a single AvaTax API call per order edit/create. Returns per-row tax amounts that the request layer pre-fills into the payload before `OrderService::store/update` runs.

**Contract-only file** — no method bodies.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| AvaTax called from a **controller helper endpoint**, not from `OrderService` | Keeps service deterministic — given a payload with `tax_amount`, it just stores |
| Helper endpoint = `POST /admin/orders/calculate-tax` | Single endpoint for both create and edit forms |
| Each `order_line` unit + each `order_line_fee` = one AvaTax `LineItemModel` | AvaTax computes tax per line independently |
| Single AvaTax call per request — N lines + M fees = N+M AvaTax lines | One round-trip, all tax computed atomically |
| For in-store pickup (`shipping_address_id = null`) → `shipTo = shop address` (same as `shipFrom`) | Store-local rate applied (per ex-19 convention) |
| Customer `tax_exempt = true` → skip AvaTax entirely, return zeros | Honor exemption flag without burning API credits |
| AvaTax timeout or exception → return zeros + log warning | Order placement must not fail because of tax service downtime |
| `unit_price` or `fee.amount = 0` → skipped from AvaTax payload, returns 0 for that item | Saves API quota; tax on $0 is $0 |
| Response mapping preserves order — caller can match by index | Lines + fees come back in same order they were sent |
| `customerCode` sent to AvaTax = customer ID as string | AvaTax customer-level rules (future use) |

---

## File locations

```
app/Services/AvaTaxService.php          (existing — extend signature for fees)
app/Http/Controllers/OrderController.php (calculateTax action — covered in 11-controller.md)
```

---

## Helper endpoint contract

### `POST /admin/orders/calculate-tax`

#### Request payload
```json
{
  "customer_id": 19,
  "shipping_address": null,
  "lines": [
    {
      "unit_price": 200.00,
      "sku": "ECM-2024",
      "fees": [
        { "name": "Programming Fee", "amount": 40.00 },
        { "name": "Gas Tuning Fee", "amount": 25.00 }
      ]
    }
  ]
}
```

> `shipping_address` is `null` for in-store pickup → controller substitutes shop address before calling AvaTax.

#### Response payload
```json
{
  "lines": [
    {
      "tax_amount": 16.50,
      "fees": [
        { "tax_amount": 3.30 },
        { "tax_amount": 2.06 }
      ]
    }
  ]
}
```

> Same nested shape as the request — frontend can `result.lines[i].tax_amount` and `result.lines[i].fees[j].tax_amount` directly into the Alpine state.

---

## Flow inside controller `calculateTax` action

1. Authorize: `orders.viewAny`
2. Parse request: `customer_id`, `shipping_address`, `lines[]`
3. Look up customer: if `tax_exempt === true` → return all-zero response, exit
4. Resolve `shipTo`:
   - If `shipping_address` is provided → use it
   - Else (pickup) → use `config('shop.billing')` address. **If shop config is unset (all fields null) → return all-zero response, exit early.**
5. Flatten payload into AvaTax line items:
   - For each `line`: one item with `amount=unit_price`, `itemCode=sku`
   - For each `line.fees[]`: one item with `amount=fee.amount`, `itemCode='FEE-'+name`
6. Call `AvaTaxService::calculateTax($items, $shipTo, (string) $customerId)`
7. Map response back to nested shape (lines → fees)
8. Return JSON

### Error handling
- AvaTax exception → return all-zero response, log warning
- Customer not found → 422 validation error
- Authorize fails → 403

---

## `AvaTaxService::calculateTax()` — signature

### Inputs
- `array $items` — flat array of `[unit_price/amount, sku/code]` pairs (mix of lines + fees)
- `array $shipTo` — `[address_line1, city, state, postal_code, country]`
- `string $customerCode` — customer ID as string

### Returns
- `array` — flat array of `['tax_amount' => float]` in same order as `$items`

### Behavior
- If AvaTax disabled (`config('avatax.enabled') === false`) → returns all zeros
- **If ship_from incomplete** (any of `street`, `city`, `state`, `zip` is empty in `config('avatax.ship_from')`) → returns all zeros, **no API call, no log noise**. This is the operator's opt-out: leave `AVATAX_SHIP_FROM_*` env vars unset and tax stays zero across the system.
- If any item has `amount <= 0` → that item returns `0`, NOT sent to AvaTax
- Builds single `CreateTransactionModel` with type `SalesOrder`
- Calls AvaTax SDK once
- Maps response lines back to input order via `validIndexes`
- Any exception caught → returns all zeros, `Log::warning('AvaTax calculateTax failed', [...])`

### Tests covered (new)
- `it_returns_zeros_when_ship_from_incomplete` — sets `config('avatax.ship_from.street', null)`, asserts no API call (mock), result is zeros

> **Note:** The existing `AvaTaxService::calculateTax()` (built in earlier work) already has this shape. Only minor changes needed: ensure `itemCode` can carry fee codes (`FEE-Programming`, `FEE-GasTuning`) without rejection.

---

## Tax-exempt customer flow

```
Frontend posts to /admin/orders/calculate-tax
        │
        ├── Customer.tax_exempt === true ?
        │    └── YES → Skip AvaTax, return:
        │             { lines: [{tax_amount: 0, fees: [{tax_amount: 0}, ...]}] }
        │
        └── NO → Continue to AvaTax flow
```

### Tests covered
- `it_returns_zeros_when_customer_is_tax_exempt` (in `AvaTaxServiceTest.php` — already exists)

---

## ex-19 cross-reference

| ex-19 fact | AvaTax helper behavior |
|------------|------------------------|
| Customer Rachel `tax_exempt = false` (line 63) | AvaTax IS called |
| `shipping_address = null` (line 80-82 NULL snapshot) | shipTo = shop address (Houston) |
| 1 line + 2 fees (lines 89-90, 97-99) | 3 AvaTax line items in one call |
| `tax_amount = 16.50` on line (line 89) | Returned by AvaTax for `unit_price=200, sku=ECM-2024` |
| `tax_amount = 3.30` on Programming Fee (line 98) | Returned by AvaTax for `amount=40, code=FEE-Programming` |
| `tax_amount = 2.06` on Gas Tuning Fee (line 99) | Returned by AvaTax for `amount=25, code=FEE-GasTuning` |
| All at 8.25% (Houston local rate) | Because shipTo = shipFrom = shop address |

---

## Frontend integration (covered in `12-views.md`)

The create/edit form's Alpine `fetchAllLineTax()` method:
1. Builds payload with all lines + their fees
2. POSTs to `/admin/orders/calculate-tax`
3. Receives nested response
4. Updates `line.tax_amount` AND `line.fees[i].tax_amount` for each line in Alpine state
5. Re-computes display `line_total` and `fee_total` on each row

Debounced 400ms to avoid hammering AvaTax on every keystroke.

---

## Dependencies

**Depends on:**
- `01-enums.md` — `PaymentMethod`
- `03-schema.md` — `customers.tax_exempt` column
- `07-service.md` — request layer pre-fills `tax_amount` before service is called
- Existing: `AvaTaxService` class, `config('avatax.enabled')`, AvaTax SDK package

**Depended on by:**
- `11-controller.md` — defines the `calculateTax` controller action
- `12-views.md` — Alpine `fetchAllLineTax()` POSTs to this endpoint
- `09-requests.md` — `unit_price` and `tax_amount` validated together (no requirement they match — AvaTax handled it)

---

## Validation gates

- [ ] Helper endpoint route is `POST /admin/orders/calculate-tax`
- [ ] Authorize check is `orders.viewAny`
- [ ] Customer `tax_exempt` short-circuits BEFORE AvaTax call
- [ ] In-store pickup uses shop address as shipTo
- [ ] Each line + each fee is a separate AvaTax line item
- [ ] Response shape matches request shape (nested lines → fees)
- [ ] AvaTax exception caught → zeros + log (no 500)
- [ ] `unit_price <= 0` or `fee.amount <= 0` items return `tax_amount: 0` (skip AvaTax)
- [ ] `customerCode` sent as string (cast from int)
- [ ] Frontend debounced 400ms (covered in `12-views.md`)

---

## Cross-check vs Layer 1 + 2 + 3

| Layer 1 truth | AvaTax helper provides |
|---------------|------------------------|
| `03-schema.md` — `order_lines.tax_amount` populated by service | Helper pre-fills payload before service receives it |
| `03-schema.md` — `order_line_fees.tax_amount` populated by service | Same — helper pre-fills nested fee tax |
| `14-events-inventory.md` — service is deterministic | AvaTax kept outside service to preserve determinism |
| `07-service.md` — `store()` does NOT call AvaTax | Controller helper called separately before form submit |
| `15-tests.md` — fixture amounts (16.50, 3.30, 2.06) | Pre-computed in fixtures, tests don't hit real AvaTax |
| `15-tests.md` — `ex19Payload()` helper has `tax_amount` keys | Matches what controller helper would have written into the form |

No gaps. AvaTax integration is fully decoupled from service logic.
