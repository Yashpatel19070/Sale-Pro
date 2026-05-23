> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-6 — Mail → Accepted → Rebuild

**Scenario:** Robert lives far. Admin generates an inbound label and emails it. Robert ships core. Tech accepts. 30-day auto rebuild.

### Data Flow

```
[Admin creates return + inbound label]
        │
        └──→ core_returns INSERT (mail, status=pending)
             shipments INSERT (shippable_type=core_return, shippable_id=6, direction=inbound, status=label_created)

[Robert drops package at carrier]
        │
        └──→ shipments.status → in_transit

[Package arrives at dock]
        │
        └──→ shipments.status → delivered
             inventory_serials INSERT (CORE-2026-0006, core_received)
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH, ref=CORE-RTN-2026-006)
             core_returns.received_at set
             core_returns.status → received

[Tech accepts]
        │
        └──→ core_returns.status → accepted
             core_returns.inspected_at set, expires_at = inspected_at + 30 days
             inventory_serials UPDATE (core_received → core_accepted)
             inventory_movements INSERT (transfer, TECH-BENCH → CORE-HOLD, ref=CORE-RTN-2026-006)
             order_core_charges.status → refunded
             payments INSERT (payable_type=core_return, cash_back, $50, refunded)

[30 days — scheduled job]
        │
        └──→ inventory_movements INSERT (transfer, CORE-HOLD → REBUILD, ref=CORE-RTN-2026-006)
             inventory_serials UPDATE (core_accepted → core_in_rebuild)
             core_returns.core_outcome → rebuild
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method  status  received_at       inspected_at      expires_at        result    core_outcome  refund_pmt
6   CORE-RTN-2026-006   1       mail    closed  2026-06-03 09:00  2026-06-03 14:00  2026-07-03 14:00  accepted  rebuild       31
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking  status     received_at
20  core_return     6             inbound    FedEx    FX-30006  delivered  2026-06-03 09:00
```

> `customer_address_id = NULL` on inbound return shipments — no delivery address needed.

**`inventory_serials`**
```
serial          status          location  note
CORE-2026-0006  core_in_rebuild REBUILD   Robert mail return — ORD-2026-030
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0006  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-006  received at dock
101  CORE-2026-0006  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-006  tech accepted
102  CORE-2026-0006  transfer      CORE-HOLD   REBUILD     CORE-RTN-2026-006  30-day expired — auto job
```

**`payments`**
```
id  order  payable_type  payable_id  method     amount  status
30  30     order         30          cash       340.00  paid
31  30     core_return   6           cash_back   50.00  refunded
```

### Financial Summary
```
net: $290.00
```
