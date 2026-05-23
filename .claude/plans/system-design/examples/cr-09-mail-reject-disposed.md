> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-9 — Mail → Rejected → Disposed

**Scenario:** Tech rejects core (stripped threads). Robert says "just dispose it." No outbound shipment.

### Data Flow

```
[Same mail intake as CR-8 up to rejection]
[Robert agrees to disposal]
        │
        └──→ inventory_movements INSERT (adjustment, TECH-BENCH → SCRAP-HOLD, ref=CORE-RTN-2026-009)
             inventory_serials UPDATE (core_rejected → scrapped)
             core_returns.core_outcome → disposed
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method  status  result    rejection_reason  core_outcome  refund_pmt
9   CORE-RTN-2026-009   1       mail    closed  rejected  stripped threads  disposed      NULL
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking  status
20  core_return     9             inbound    FedEx    FX-30009  delivered  ← no outbound row
```

**`inventory_serials`**
```
serial          status    location    note
CORE-2026-0009  scrapped  SCRAP-HOLD  disposed — Robert agreed, stripped threads
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0009  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-009  received at dock
101  CORE-2026-0009  adjustment    TECH-BENCH  SCRAP-HOLD  CORE-RTN-2026-009  disposed — Robert agreed
```

### Financial Summary
```
net: $340.00  (Robert loses $50 deposit; inbound label absorbed by us)
```
