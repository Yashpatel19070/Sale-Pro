# Purchase Order Module — E2E Tests Index

| File | Framework | What it covers | Run command |
|------|-----------|----------------|-------------|
| [09-e2e-http.md](09-e2e-http.md) | PHPUnit / Pest | Auth, validation, state transitions, guards, cross-resource side-effects | `XDEBUG_MODE=off vendor/bin/pest tests/E2E/PurchaseOrderE2ETest.php` |

## Test file

`tests/E2E/PurchaseOrderE2ETest.php`

## Coverage areas

- **PO-xx** — Purchase Order lifecycle (create → submit → approve → on-the-way → received → invoiced → closed)
- **GRN-xx** — Goods Receipt lifecycle (create → update → complete), qty validation, PO status side-effects
- **INV-xx** — Invoice lifecycle (create → approve → paid), PO closure side-effects
- **J-xx** — Full cross-module journeys

## Run

```bash
XDEBUG_MODE=off vendor/bin/pest tests/E2E/PurchaseOrderE2ETest.php --no-coverage
```
