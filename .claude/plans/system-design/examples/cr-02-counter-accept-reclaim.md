> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-2 — Counter → Accepted → Customer Reclaims

**Scenario:** Same as CR-1 through acceptance and refund. Day 15 Robert returns: "I want my old core back." Admin re-charges $50. Core handed over.

### Data Flow (from reclaim step)

```
[Day 15 — Robert comes back for core]
        │
        └──→ payments INSERT (payable_type=core_return, cash, $50.00, paid)  ← re-charge
             inventory_movements INSERT (transfer, CORE-HOLD → NULL, ref=CORE-RTN-2026-002)
             inventory_serials UPDATE (core_accepted → sold)
             core_returns.core_outcome → returned_to_customer
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method   status  result    core_outcome         refund_pmt
2   CORE-RTN-2026-002   1       counter  closed  accepted  returned_to_customer 31
```

**`inventory_serials`**
```
serial          status  location  note
CORE-2026-0002  sold    NULL      reclaimed by Robert — day 15
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0002  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-002  received at counter
101  CORE-2026-0002  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-002  tech accepted
102  CORE-2026-0002  transfer      CORE-HOLD   NULL        CORE-RTN-2026-002  Robert reclaimed at counter
```

**`payments`**
```
id  order  payable_type  payable_id  method     amount  status
30  30     order         30          cash       340.00  paid
31  30     core_return   2           cash_back   50.00  refunded  ← auto on accept
32  30     core_return   2           cash        50.00  paid      ← re-charge on reclaim
```

### Financial Summary
```
original:    $340.00
refunded:     $50.00
re-charged:   $50.00
net:         $340.00  (Robert has new part + old core; round-trip deposit)
```
