> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-10 — Fraud Blocked

**Scenario:** Robert brings a "core" to the counter. Physical serial on the unit is SN-2026-999 — one of our own starter motors, `status=sold`. Fraud detected. Return blocked.

### Fraud Check Flow

```
[Admin checks serial on the physical unit]
        │
        └──→ inventory_serials WHERE serial_number = 'SN-2026-999'
             Found: status=sold, pattern=SN-xxx (product serial — not CORE-xxx)
             → FRAUD DETECTED — block

[No core_receive movement — no CORE-xxx serial assigned]
        │
        └──→ core_returns INSERT (status=closed, inspection_result=rejected,
                rejection_reason='fraud — serial SN-2026-999 matches our inventory')
             order_core_charges.status → forfeited
```

### Schema Data

**`core_returns`**
```
id  number               occ_id  method   status    result    rejection_reason                              core_outcome  refund_pmt
10  CORE-RTN-2026-010    1       counter  closed    rejected  fraud — serial SN-2026-999 matches our inventory NULL        NULL
```

**`order_core_charges`**
```
id  status
1   forfeited
```

> No `inventory_serials` INSERT. No `inventory_movements` INSERT. Never assign a CORE-xxx serial to a unit blocked for fraud.

### Financial Summary
```
net: $340.00  (Robert loses $50 deposit)
```
