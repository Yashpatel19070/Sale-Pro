# AvaTax Integration — Overview

## Goal
Automatically calculate tax per order line using Avalara AvaTax instead of manual Tax % entry.

## Phases

| Phase | Plan file | What it covers |
|-------|-----------|----------------|
| 1 — Setup & Ping | `01-setup.md` | SDK config, `AvaTaxService`, `avatax:ping` command |
| 2 — Tax Calculation | `02-tax.md` | `calculateTax()` on order create/update |
| 3 — Order Integration | `03-order.md` | Wire AvaTax into `OrderService::store()` and `update()` |

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
