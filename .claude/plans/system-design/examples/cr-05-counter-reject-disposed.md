> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-5 — Counter → Rejected → Disposed

**Scenario:** Tech rejects (oil-contaminated, hazmat). Robert does not want it back. Formal disposal.

Identical to CR-4. Only `core_outcome` differs. Both land in SCRAP-HOLD; `core_outcome` distinguishes them for reporting.

```
core_returns.core_outcome:  disposed
inventory_serials.status:   scrapped
movement:  adjustment  TECH-BENCH → SCRAP-HOLD  (notes: disposed — hazmat)
```
