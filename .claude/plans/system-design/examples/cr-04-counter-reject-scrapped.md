> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-4 — Counter → Rejected → Scrapped

**Scenario:** Tech rejects core (cracked housing). Robert does not want it back. Scrapped.

### Data Flow

```
[Same counter intake as CR-3]
[Tech rejects — Robert declines return]
        │
        └──→ order_core_charges.status → forfeited
             inventory_movements INSERT (adjustment, TECH-BENCH → SCRAP-HOLD, ref=CORE-RTN-2026-004)
             inventory_serials UPDATE (core_rejected → scrapped)
             core_returns.core_outcome → scrapped
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method   status  result    rejection_reason  core_outcome  refund_pmt
4   CORE-RTN-2026-004   1       counter  closed  rejected  cracked housing   scrapped      NULL
```

**`order_core_charges`**
```
id  status
1   forfeited
```

**`inventory_serials`**
```
serial          status    location    note
CORE-2026-0004  scrapped  SCRAP-HOLD  scrapped — cracked housing, Robert declined return
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0004  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-004
101  CORE-2026-0004  adjustment    TECH-BENCH  SCRAP-HOLD  CORE-RTN-2026-004  scrapped — Robert declined
```

### Financial Summary
```
net: $340.00  (Robert loses $50 deposit)
```
