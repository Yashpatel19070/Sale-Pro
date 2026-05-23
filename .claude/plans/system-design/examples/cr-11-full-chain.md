> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## CR-11 — Full Chain

**Scenario:** Robert has two orders (ORD-2026-030 and ORD-2026-045, both with core charge). He:
1. Tries fraud on ORD-030 → blocked
2. Mails his real core on ORD-030 → accepted → refund
3. Reclaims within 30 days → re-charged → takes core back (`CORE-2026-0011`, status=sold)
4. Buys again (ORD-045) — same core charge
5. Mails the same physical core back for ORD-045
6. Fraud check finds `CORE-2026-0011` (CORE-xxx pattern, not product serial) → checks prior `core_returns` → ALLOWED → new serial `CORE-2026-0012` assigned
7. Accepted → 30-day expires → rebuild

---

### ORD-2026-045 — Second order

```
orders:
id  number        customer_id  source   status      payment_status  subtotal  fees   core_charges  shipping  grand_total
45  ORD-2026-045  5            walk_in  processing  paid            250.00    25.00  50.00         15.00     340.00

order_core_charges:
id  order  order_line  description           amount  total   status
10  45     45          Core — Starter Motor  50.00   50.00   outstanding

payments:
id  order  payable_type  payable_id  method  amount  status
40  45     order         45          cash    340.00  paid
```

---

### Step A — Fraud attempt on ORD-030 (CR-10)

```
CORE-RTN-2026-010: rejected/fraud
order_core_charges id=1: forfeited
Admin manually resets order_core_charge id=1 → outstanding (Robert has a real core to return)
```

---

### Step B — Real mail return on ORD-030

```
core_returns: CORE-RTN-2026-011, method=mail, occ_id=1

[Robert mails real core — inbound shipment FX-30011]
        │
        └──→ inventory_serials INSERT (CORE-2026-0011, core_received)
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH, ref=CORE-RTN-2026-011)
             core_returns CORE-RTN-2026-011: status=received

[Tech accepts]
        │
        └──→ core_returns: status=accepted, expires_at set
             inventory_serials CORE-2026-0011 → core_accepted
             inventory_movements INSERT (transfer, TECH-BENCH → CORE-HOLD, ref=CORE-RTN-2026-011)
             order_core_charges id=1 → refunded
             payments id=31: payable_type=core_return, payable_id=11, cash_back, $50, refunded
```

---

### Step C — Robert reclaims (day 10)

```
[Robert calls — wants core mailed back]
        │
        └──→ payments id=32: payable_type=core_return, payable_id=11, cash, $50, paid  ← re-charge
             shipments INSERT (outbound, shippable_type=core_return, shippable_id=11, tracking=FX-40011)
             inventory_movements INSERT (transfer, CORE-HOLD → NULL, ref=CORE-RTN-2026-011)
             inventory_serials CORE-2026-0011 → sold
             core_returns CORE-RTN-2026-011: core_outcome=returned_to_customer, status=closed
```

```
shipments (ORD-030 core):
id  shippable_type  shippable_id  direction  tracking  status
20  core_return     11            inbound    FX-30011  delivered  ← Robert ships to us
21  core_return     11            outbound   FX-40011  delivered  ← we ship back to Robert
```

---

### Step D — Robert mails same physical core for ORD-045

Three months pass. Robert mails the same core (CORE-2026-0011 label still on it) for ORD-2026-045.

```
[Admin creates CORE-RTN-2026-020 on ORD-045]
[Package arrives — admin scans CORE-2026-0011]
        │
        └──→ inventory_serials WHERE serial_number = 'CORE-2026-0011'
             Found: status=sold, pattern=CORE-xxx (not a product serial)
             → Check prior core_returns: CORE-RTN-2026-011, customer=Robert, outcome=returned_to_customer ✓
             → ALLOWED — it is Robert's own reclaimed core

             inventory_serials INSERT (CORE-2026-0012, core_received)  ← NEW serial, never reuse 0011
             inventory_movements INSERT (core_receive, NULL → TECH-BENCH, ref=CORE-RTN-2026-020)
             core_returns CORE-RTN-2026-020: status=received

[Tech accepts]
        │
        └──→ core_returns CORE-RTN-2026-020: status=accepted, expires_at set
             inventory_serials CORE-2026-0012 → core_accepted
             inventory_movements INSERT (transfer, TECH-BENCH → CORE-HOLD, ref=CORE-RTN-2026-020)
             order_core_charges id=10 → refunded
             payments id=41: payable_type=core_return, payable_id=20, cash_back, $50, refunded

[30 days — scheduled job]
        │
        └──→ inventory_movements INSERT (transfer, CORE-HOLD → REBUILD, ref=CORE-RTN-2026-020)
             inventory_serials CORE-2026-0012 → core_in_rebuild
             core_returns CORE-RTN-2026-020: core_outcome=rebuild, status=closed
```

---

### Full Chain — Combined Data

**`core_returns`**
```
id  number               occ_id   method   status    result    core_outcome         refund_pmt  notes
10  CORE-RTN-2026-010    1(030)   counter  closed    rejected  NULL                 NULL        fraud — SN-2026-999
11  CORE-RTN-2026-011    1(030)   mail     closed    accepted  returned_to_customer 31          real return, reclaimed day 10
20  CORE-RTN-2026-020    10(045)  mail     closed    accepted  rebuild              41          same physical core, new serial CORE-0012
```

**`inventory_serials`**
```
serial          status          location  note
CORE-2026-0011  sold            NULL      reclaimed by Robert — CORE-RTN-2026-011 — DO NOT REUSE
CORE-2026-0012  core_in_rebuild REBUILD   ORD-2026-045 — CORE-RTN-2026-020
```

**`inventory_movements`**
```
-- CORE-RTN-2026-011 (ORD-030 real return):
100  CORE-2026-0011  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-011  received at dock
101  CORE-2026-0011  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-011  accepted
102  CORE-2026-0011  transfer      CORE-HOLD   NULL        CORE-RTN-2026-011  shipped back — Robert reclaimed day 10

-- CORE-RTN-2026-020 (ORD-045 — same physical core, new serial):
200  CORE-2026-0012  core_receive  NULL        TECH-BENCH  CORE-RTN-2026-020  new serial — prior CORE-0011 found, allowed
201  CORE-2026-0012  transfer      TECH-BENCH  CORE-HOLD   CORE-RTN-2026-020  accepted
202  CORE-2026-0012  transfer      CORE-HOLD   REBUILD     CORE-RTN-2026-020  30-day expired — auto job
```

**`payments`**
```
-- ORD-030:
30  (030)  order        30   cash       340.00  paid      ← original purchase
31  (030)  core_return  11   cash_back   50.00  refunded  ← accept CORE-RTN-011
32  (030)  core_return  11   cash        50.00  paid      ← re-charge on reclaim

-- ORD-045:
40  (045)  order        45   cash       340.00  paid      ← original purchase
41  (045)  core_return  20   cash_back   50.00  refunded  ← accept CORE-RTN-020
```

### Financial Summary
```
ORD-030: paid $340 + $50 re-charge, refunded $50   → net $340
ORD-045: paid $340, refunded $50                    → net $290
Total:   $630  (Robert has two new parts + old core back; one core goes to rebuild)
```
