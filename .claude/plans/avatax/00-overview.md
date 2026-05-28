# AvaTax Integration — Overview

## Goal
Automatically calculate tax per order line using Avalara AvaTax instead of manual Tax % entry.

## Phases

| Phase | Plan file | What it covers |
|-------|-----------|----------------|
| 1 — Setup & Ping | `01-setup.md` | SDK config, `AvaTaxService`, `avatax:ping` command |
| 2 — Tax Calculation | `02-tax.md` | `calculateTax()` on order create/update |
| 3 — Order Integration | `03-order.md` | Commit `SalesInvoice` to AvaTax from `OrderService::recordCashPayment()` after the DB transaction |
| 4 — Customer Lifecycle | `04-customer.md` | Queued `SyncCustomerToAvaTaxJob` on Customer create/update; adds `avatax_customer_id`, `tax_identification_number`, `exemption_certificate_number`, `entity_use_code`, `avatax_synced_at` columns |
| ~~5 — Void on Cancel~~ | — | **Deferred to v2** — when an order is cancelled, would call AvaTax `voidTransaction()`. Skipped for v1 by product decision (2026-05-27). |
| ~~6 — Adjust on Refund~~ | — | **Deferred to v2** — when a return/refund is recorded, would call AvaTax `adjustTransaction()`. Skipped for v1 by product decision (2026-05-27). |

## v1 Status: shippable

Module is feature-complete for v1: calculate at quote, commit on payment, customer lifecycle, ISO-2 country validation, graceful handling of SDK error responses. Void/Adjust are v2.

## Credentials (from .env)

| Key | Env var |
|-----|---------|
| Account number | `AVATAX_ACCOUNT_NUMBER` |
| License key | `AVATAX_LICENSE_KEY` |
| Company code | `AVATAX_COMPANY_CODE` |
| Environment | `AVATAX_ENVIRONMENT` (`sandbox` or `production`) |
| Enabled flag | `AVATAX_ENABLED` |
| Ship-from address | `AVATAX_SHIP_FROM_*` |

## SDK
Package already installed: `avalara/avataxclient ^26.5.0`

## Config file
`config/avatax.php` — reads all env vars above.
Bound in `AppServiceProvider` as singleton: `AvaTaxService::class`.
