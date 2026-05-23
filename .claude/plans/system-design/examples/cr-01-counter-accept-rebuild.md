> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-1 — Counter → Accepted → Rebuild

**Scenario:** Robert brings his old core to the counter. Tech accepts it. Refund fires automatically. Robert never comes back in 30 days. Scheduled job moves core to rebuild.

### Data Flow

```
[Robert hands core across counter]
        │
        └──→ core_returns INSERT (method=counter, status=pending)
             core_returns.status → received
             inventory_serials INSERT (CORE-2026-0001, status=core_received)
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH, ref=CORE-RTN-2026-001)

[Tech examines — accepts]
        │
        └──→ core_returns.inspection_result → accepted
             core_returns.inspected_at = 2026-06-01 11:00
             core_returns.expires_at   = 2026-07-01 11:00
             core_returns.status → accepted
             inventory_serials UPDATE (core_received → core_accepted)
             inventory_movements INSERT (transfer, TECH-BENCH → CORE-HOLD, ref=CORE-RTN-2026-001)
             order_core_charges.status → refunded
             payments INSERT (payable_type=core_return, cash_back, $50.00, refunded)
             core_returns.refund_payment_id set

[30 days — scheduled job]
        │
        └──→ inventory_movements INSERT (transfer, CORE-HOLD → REBUILD, ref=CORE-RTN-2026-001)
             inventory_serials UPDATE (core_accepted → core_in_rebuild)
             core_returns.core_outcome → rebuild
             core_returns.status → closed
```

### Schema Data

**`core_returns`**
```
id  number              occ_id  method   status  received_at       inspected_at      expires_at        result    core_outcome  refund_pmt
1   CORE-RTN-2026-001   1       counter  closed  2026-06-01 10:00  2026-06-01 11:00  2026-07-01 11:00  accepted  rebuild       31
```

**`order_core_charges`**
```
id  status
1   refunded
```

**`inventory_serials`**
```
serial          status          location  note
CORE-2026-0001  core_in_rebuild REBUILD   Robert King — ORD-2026-030
```

**`inventory_movements`**
```
id   serial          type          from        to          reference          notes
100  CORE-2026-0001  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-001  received at counter
101  CORE-2026-0001  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-001  tech accepted
102  CORE-2026-0001  transfer      CORE-HOLD   REBUILD     CORE-RTN-2026-001  30-day expired — auto job
```

**`payments`**
```
id  order  payable_type  payable_id  method     amount  status
30  30     order         30          cash       340.00  paid
31  30     core_return   1           cash_back   50.00  refunded
```

### Financial Summary
```
original:  $340.00
refunded:   $50.00
net:       $290.00  (Robert has new part; core goes to rebuild)
```
