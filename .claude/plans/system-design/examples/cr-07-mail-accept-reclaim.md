> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-7 — Mail → Accepted → Customer Reclaims → We Ship Back

**Scenario:** Same as CR-6 up to accepted and refund. Day 12 Robert calls: "Mail my old core back." Admin re-charges $50 and ships outbound.

### Data Flow (from reclaim step)

```
[Day 12 — Robert calls]
        │
        └──→ payments INSERT (payable_type=core_return, cash, $50, paid)  ← re-charge
             shipments INSERT (shippable_type=core_return, shippable_id=7, direction=outbound)
             inventory_movements INSERT (transfer, CORE-HOLD → NULL, ref=CORE-RTN-2026-007)
             inventory_serials UPDATE (core_accepted → sold)
             core_returns.core_outcome → returned_to_customer
             core_returns.status → closed
```

### Schema Data

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking  status
20  core_return     7             inbound    FedEx    FX-30007  delivered  ← Robert ships to us
21  core_return     7             outbound   FedEx    FX-40007  delivered  ← we ship back to Robert
```

**`inventory_serials`**
```
serial          status  location  note
CORE-2026-0007  sold    NULL      reclaimed by Robert — shipped back day 12
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0007  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-007  received at dock
101  CORE-2026-0007  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-007  accepted
102  CORE-2026-0007  transfer      CORE-HOLD   NULL        CORE-RTN-2026-007  shipped back to Robert
```

**`payments`**
```
id  order  payable_type  payable_id  method     amount  status
31  30     core_return   7           cash_back   50.00  refunded  ← auto on accept
32  30     core_return   7           cash        50.00  paid      ← re-charge on reclaim
```

### Financial Summary
```
net: $340.00  (Robert has new part + old core; outbound label cost absorbed by us)
```
