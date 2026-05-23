> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-3 — Counter → Rejected → Customer Takes Core

**Scenario:** Tech rejects core (severe corrosion). Robert takes it back at the counter.

### Data Flow

```
[Robert hands core across counter]
        │
        └──→ core_returns INSERT (counter, pending → received)
             inventory_serials INSERT (CORE-2026-0003, core_received)
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH)

[Tech examines — rejects]
        │
        └──→ core_returns.inspection_result → rejected
             core_returns.rejection_reason  → severe corrosion
             core_returns.status → rejected
             inventory_serials UPDATE (core_received → core_rejected)
             order_core_charges.status → forfeited

[Robert takes core on the spot]
        │
        └──→ inventory_movements INSERT (transfer, TECH-BENCH → NULL, ref=CORE-RTN-2026-003)
             inventory_serials UPDATE (core_rejected → sold)
             core_returns.core_outcome → returned_to_customer
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method   status  result    rejection_reason  core_outcome         refund_pmt
3   CORE-RTN-2026-003   1       counter  closed  rejected  severe corrosion  returned_to_customer NULL
```

**`order_core_charges`**
```
id  status
1   forfeited
```

**`inventory_serials`**
```
serial          status  location  note
CORE-2026-0003  sold    NULL      rejected — returned to Robert at counter
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0003  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-003
101  CORE-2026-0003  transfer      TECH-BENCH  NULL        CORE-RTN-2026-003  returned to customer at counter
```

### Financial Summary
```
net: $340.00  (Robert loses $50 deposit)
```
