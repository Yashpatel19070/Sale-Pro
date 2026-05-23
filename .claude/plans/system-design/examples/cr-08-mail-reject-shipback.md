> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-8 — Mail → Rejected → We Ship Back

**Scenario:** Robert mails core. Tech rejects (wrong part number). We ship the rejected core back to Robert. We pay the return label.

### Data Flow

```
[Admin creates return + inbound label]
        │
        └──→ core_returns INSERT (mail, status=pending)
             shipments INSERT (shippable_type=core_return, shippable_id=8, direction=inbound, status=label_created)

[Robert drops package at carrier]
        │
        └──→ shipments.status → in_transit

[Package arrives at dock]
        │
        └──→ shipments.status → delivered
             inventory_serials INSERT (CORE-2026-0008, core_received)
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH, ref=CORE-RTN-2026-008)
             core_returns.received_at set
             core_returns.status → received

[Tech examines — rejects]
        │
        └──→ core_returns.inspection_result → rejected
             core_returns.rejection_reason  → wrong part number
             core_returns.status → rejected
             inventory_serials UPDATE (core_received → core_rejected)
             order_core_charges.status → forfeited

[Admin ships rejected core back to Robert]
        │
        └──→ shipments INSERT (outbound, shippable_type=core_return, shippable_id=8)
             inventory_movements INSERT (transfer, TECH-BENCH → NULL, ref=CORE-RTN-2026-008)
             inventory_serials UPDATE (core_rejected → sold)
             core_returns.core_outcome → returned_to_customer
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method  status  result    rejection_reason   core_outcome         refund_pmt
8   CORE-RTN-2026-008   1       mail    closed  rejected  wrong part number  returned_to_customer NULL
```

**`order_core_charges`**
```
id  status
1   forfeited
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking  status
20  core_return     8             inbound    FedEx    FX-30008  delivered  ← Robert ships to us
21  core_return     8             outbound   FedEx    FX-40008  delivered  ← we ship back (we pay)
```

**`inventory_serials`**
```
serial          status  location  note
CORE-2026-0008  sold    NULL      rejected — shipped back to Robert
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0008  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-008  received at dock
101  CORE-2026-0008  transfer      TECH-BENCH  NULL        CORE-RTN-2026-008  shipped back to customer
```

### Financial Summary
```
net: $340.00  (Robert loses $50 deposit; gets old core back; outbound label absorbed by us)
```
