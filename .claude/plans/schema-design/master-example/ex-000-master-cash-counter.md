# ex-000 — MASTER — Cash + Counter (In-Store) Order Lifecycle

> **Single source of truth for the cash / counter (in-store) flow.** See [global.md](../global.md) for shared enums, numbering, money + logging rules — this doc does **not** restate them, it uses them.

> _Illustrative — IDs/numbers/timestamps not real. **Schema column sets are canonical** — services/controllers/views build on these._

**Scope:** cash payment · in-store counter (pickup / drop-and-collect) · **no carrier, no shipments**. `source` = `phone` **or** `walk_in` — **identical flow**, only the `source` value differs (walk_in = cx at counter the whole time; phone = called in then comes in). `origin = admin` (staff builds on cx behalf, force-process). Card + shipped lifecycle = separate master (see ex-002).

Every branch below uses its **own order number** — each is internally true (no "alternate timeline" hand-waving). One customer (Marcus Webb) places many orders over time.

---

## 1. The product mix (same across all branches — keeps money identical)

2 units + 3 per-line fees, Houston 8.25% tax (all `tax_amount` from **AvaTax API**):

| thing | base | tax | total |
|---|---|---|---|
| ECM-2024 (Engine Control Module) | 200.00 | 16.50 | **216.50** |
| TCM-2024 (Transmission Control Module) | 180.00 | 14.85 | **194.85** |
| Programming Fee (on ECM) | 40.00 | 3.30 | **43.30** |
| Gas Tuning Fee (on ECM) | 25.00 | 2.06 | **27.06** |
| Programming Fee (on TCM) | 40.00 | 3.30 | **43.30** |
| **grand_total** | | tax Σ **40.01** | **$525.01** |

`line_total = unit_price + tax_amount` · `fee_total = amount + tax_amount` · `grand_total = Σ line_totals + Σ fee_totals + shipping (0)`.

---

## 2. Decision tree (if / else — the whole cash/counter map)

```
PLACE order (pending, unpaid)
  → FORCE-PROCESS (origin=admin): assign + reserve serials  [reserved while UNPAID]
  → ready_for_pickup (event; status stays processing)
  → PAY cash?
       NO  ── cx never pays / no-show ────────────────────────► B1 CANCEL (unpaid)
                                                                  release serials, no money
       YES ── payment_status=paid, AvaTax COMMIT
              → PICKED UP?
                   NO  ── prepaid, never collected ───────────► B2 CANCEL-REFUND (paid)
                                                                  release serials, FULL refund (fees too)
                   YES ── completed (handover), serials sold
                          → POST-SALE event?
                              ├ unit FAULT claim → COMPLAINT pipeline (verify→inspect→decide)
                              │     exam = fault/internal ──────► B3 FREE replacement (old→rebuild)
                              │     exam = no_fault →
                              │         cx ACCEPTS ─────────────► B5 RETURN unit to cx (no $, same unit)
                              │         cx INSISTS on new unit ─► B4 CHARGED replacement (cx pays; old→back_to_stock)
                              │     (any replacement fails again, free OR charged → repeat pipeline → CHAIN via parent_id ─► B-CHAIN)
                              └ CHANGED MIND (no fault claim) ──► B6 RETURN + REFUND (goods back; fees kept)
```

**Gates (hard rules):**
- serials `reserved` at **force-process** (before pay) — `origin ∈ {admin, web_admin}`.
- **nothing leaves / completes until `payment_status = paid`.**
- **cancel** only while goods **not left** (serials `reserved`). Goods left → it's a **return**, not cancel.
- **replacement requires a complaint** (no standalone swap). **No goodwill, no skip.**
- charged replacement → cx **pays before handover**.

---

## 3. Forward lifecycle (the spine) — ORD-2026-0030, full detail

**Flow:** Marcus calls (or walks in), admin builds order, force-processes (2 serials reserved while unpaid), units staged, Marcus pays $525.01 cash at counter, takes both units → completed.

```
[place]   orders INSERT (pending, unpaid) + order_lines + order_line_fees + order_events(order_placed)
          → AvaTax calculateTax() — SalesOrder quote (uncommitted), 5 lines
[process] order_lines UPDATE (serials assigned) · inventory_serials in_stock→reserved (locked)
          orders.status→processing · order_events(processing {forced:true})
[ready]   order_events(ready_for_pickup)   ◄ event only, status stays processing
[pay]     payments INSERT (cash 525.01 paid) · orders.payment_status→paid
          → AvaTax commitInvoice() — SalesInvoice, code = ORD-2026-0030
[done]    inventory_movements INSERT (sale ×2) · inventory_serials reserved→sold
          orders.status→completed, completed_at/by, closed_at · order_events(payment_received, completed)
```

**`customers`**
```
id   name         email              phone         status  tax_exempt
20   Marcus Webb  marcus@example.com 555-200-0002  active  false
```

**`customer_addresses`**
```
id  customer_id  type     line1             city     state  postal  is_default
30  20           billing  1420 Oak Park Dr  Houston  TX     77018   true
```
> In-store pickup → no shipping address row.

**`orders`**
```
id  number         customer_id  source  origin  status     payment_status  shipping  grand_total  created_by
30  ORD-2026-0030  20           phone   admin   completed  paid            0.00      525.01       1

created_at           completed_at         completed_by  closed_at
2026-05-26 09:00:00  2026-05-26 14:00:00  1             2026-05-26 14:00:00

billing snapshot = Marcus Webb / 1420 Oak Park Dr / Houston TX 77018 US   ·   shipping snapshot = NULL (pickup)
```

**`order_lines`**
```
id  order_id  product_listing_id  sku       product_name                 inventory_serial_id  unit_price  tax_amount  line_total
60  30        14                  ECM-2024  Engine Control Module        SN-401               200.00      16.50       216.50
61  30        15                  TCM-2024  Transmission Control Module  SN-501               180.00      14.85       194.85
```

**`order_line_fees`**
```
id  order_line_id  name             amount  tax_amount  fee_total  created_by  created_at
20  60             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
21  60             Gas Tuning Fee   25.00   2.06        27.06      1           2026-05-26 09:00:00
22  61             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
```

**`payments`**
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
100 30        order         30          payment  cash    525.01   paid    2026-05-26 14:00:00   1            1
```

**`order_events`**
```
id  order_id  event             metadata                                                          created_by  created_at
1   30        order_placed      {"lines":2,"skus":["ECM-2024","TCM-2024"],"grand_total":"525.01"} 1           2026-05-26 09:00:00
2   30        processing        {"forced":true,"origin":"admin","serials":["SN-401","SN-501"]}    1           2026-05-26 09:05:00
3   30        ready_for_pickup  {"units":2}                                                       1           2026-05-26 12:00:00
4   30        payment_received  {"method":"cash","amount":"525.01"}                               1           2026-05-26 14:00:00
5   30        completed         {"pickup":true}                                                   1           2026-05-26 14:00:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-401  sold    NULL      with Marcus Webb — ECM, picked up (ORD-2026-0030)
SN-501  sold    NULL      with Marcus Webb — TCM, picked up (ORD-2026-0030)
```

**`inventory_movements`**
```
id   serial  type     from         to           reference      notes
200  SN-401  receive  NULL         Warehouse A  PO-2026-030    initial stock receipt
201  SN-501  receive  NULL         Warehouse A  PO-2026-031    initial stock receipt
202  SN-401  sale     Warehouse A  NULL         ORD-2026-0030  handed to Marcus at counter (14:00)
203  SN-501  sale     Warehouse A  NULL         ORD-2026-0030  handed to Marcus at counter (14:00)
```
> `reserved` = status only, **no movement row**. Only physical move = `sale` at pickup.

**AvaTax:** `calculateTax()` quote at placement → `commitInvoice()` at payment, doc code = `ORD-2026-0030`. Tax rounded 2 dp per line.

**`order_notes`** (human free-text, right-rail feed — WooCommerce/Shopify style; ≠ events ≠ audit)
```
id  order_id  type      body                                  created_by  created_at           deleted_at
1   30        private   Cx called, confirmed pickup time.     1           2026-05-26 09:10:00  NULL
2   30        customer  Units programmed + ready for pickup.  1           2026-05-26 12:05:00  NULL
```
> `type` (PHP enum): `private` (staff-only) · `customer` (shown in portal). **Soft delete** (`deleted_at`, Laravel `SoftDeletes`) = author (own note) + admin; **force delete = admin only** — gated by Spatie permission matrix. Applies to every order in every branch (same feature).

---

## 4. Branch sections — each own order, DELTA only

> Each branch = a fresh order that ran the **spine** (§3) up to the point noted, then diverges. Only the **new / changed** rows shown.

---

### B1 — CANCEL (unpaid) · ORD-2026-0031

**Fork point:** placed + force-processed (serials `reserved`), cx **never pays** (no-show). No money ever.

```
GATE: unpaid + serials reserved (goods never left) → cancel allowed
inventory_serials UPDATE (SN-402, SN-502: reserved → in_stock)   ◄ release, NO movement row
orders.status → cancelled, closed_at set   (payment_status stays unpaid)
order_events INSERT (order_cancelled)
AvaTax — quote never committed → nothing to void
```

**`orders`** (delta)
```
id  number         status     payment_status  grand_total  closed_at
31  ORD-2026-0031  cancelled  unpaid          525.01       2026-05-26 18:00:00
```

**`order_events`** (delta — spine had placed/processing/ready, then:)
```
id  order_id  event            metadata                                                created_by  created_at
4   31        order_cancelled  {"reason":"no_show","serials_released":["SN-402","SN-502"]} 1        2026-05-26 18:00:00
```

**`inventory_serials`**
```
serial  status    location     note
SN-402  in_stock  Warehouse A  ORD-2026-0031 cancelled (no-show) — released
SN-502  in_stock  Warehouse A  ORD-2026-0031 cancelled (no-show) — released
```

> **No `payments`, no `refunds`, no `inventory_movements` (units never moved).** Terminal = `cancelled`, `unpaid`.

---

### B2 — CANCEL-REFUND (paid) · ORD-2026-0032

**Fork point:** placed + processed + **prepaid $525.01 cash** + ready_for_pickup, then cx **never collects**, Day +3 calls "cancel, refund, reason=changed mind." Goods never left.

```
GATE: paid + serials reserved (goods never left) → cancel-refund allowed
refunds INSERT (REF-2026-0001: return_id=NULL, reason=cancel, full)
refund_lines INSERT (full — unit + its fees per line)
payments INSERT (kind=refund, payable_type=refund, cash 525.01 refunded)
AvaTax adjustTransaction — FULL reversal of committed invoice ORD-2026-0032 (−40.01)
inventory_serials UPDATE (SN-403, SN-503: reserved → in_stock)   ◄ no movement row
orders.status → cancelled  (payment_status stays paid)
order_events INSERT (refunded, order_cancelled)
```

**`orders`** (delta — never completed)
```
id  number         status     payment_status  grand_total  completed_at  closed_at
32  ORD-2026-0032  cancelled  paid            525.01       NULL          2026-05-29 11:00:00
```

**`payments`** (prepay + refund)
```
id  order_id  payable_type  payable_id  kind     method  amount   status     received_at           received_by  created_by
110 32        order         32          payment  cash    525.01   paid       2026-05-26 10:00:00   1            1
111 32        refund        1           refund   cash    525.01   refunded   2026-05-29 11:00:00   1            1
```

**`refunds`**
```
id  number         order_id  return_id  reason  total_amount  total_tax  method  status    created_by  created_at
1   REF-2026-0001  32        NULL       cancel  525.01        40.01      cash    refunded  1           2026-05-29 11:00:00
```

**`refund_lines`** (full — unit + its fees; whole order cancelled, nothing rendered)
```
id  refund_id  order_line_id  amount   tax
1   1          62             265.00   21.86     (ECM 200 + Prog 40 + Gas 25)
2   1          63             220.00   18.15     (TCM 180 + Prog 40)
```
> Σ(amount+tax) = 525.01 = full. `amount` includes fees per global `refund_lines.amount` rule (cancel = fees refunded; contrast B6 return = fees kept).

**`order_events`** (delta)
```
id  order_id  event             metadata                                                         created_by  created_at
5   32        refunded          {"refund":"REF-2026-0001","amount":"525.01","method":"cash"}     1           2026-05-29 11:00:00
6   32        order_cancelled   {"reason":"changed_mind","serials_released":["SN-403","SN-503"]} 1           2026-05-29 11:00:00
```

**`inventory_serials`**
```
serial  status    location     note
SN-403  in_stock  Warehouse A  ORD-2026-0032 cancelled — released, never picked up
SN-503  in_stock  Warehouse A  ORD-2026-0032 cancelled — released, never picked up
```

> **No `returns`/`return_lines`** (goods never left) · **no movement row**. Net cash $0. `payment_status` stays `paid` (net = Σ payment − Σ refund).

---

### B3 — COMPLAINT → FREE replacement · ORD-2026-0033

**Fork point:** order **completed** (picked up like spine, serials SN-404 ECM / SN-504 TCM `sold`). Day +7 ECM `SN-404` dead → complaint → tech finds **internal fault** → **free** replacement `SN-414`. Old unit → rebuild.

```
complaint (CMP-2026-0001: line 64 slot, serial SN-404)
  → INTAKE VERIFY (serial matches order, anti-fraud)         ◄ Gate 1
  → inventory_serials (SN-404: sold → under_examination)
  → tech inspect → internal fault                            ◄ Gate 2
  → DECISION: fault → FREE
  → replacements (REP-2026-0001: type=free, parent_id=NULL, complaint_id=1)
  → replacement_lines (line 64, old=SN-404, new=SN-414)
  → inventory_serials (SN-404 → to_rebuild · SN-414 in_stock→reserved→sold)
  → inventory_movements (SN-404 return_in→transfer→Rebuild · SN-414 replacement_out)
  → complaint closed (unit_outcome=rebuild) · replacement completed
  → NO payment (free) · NO shipment (counter)
```

**`complaints`**
```
id  number         order_id  order_line_id  serial  status  examination_result  unit_outcome  issue_description  unit_received_at     examined_by  examination_notes         closed_at            closed_by  created_by  created_at
1   CMP-2026-0001  33        64             SN-404  closed  internal_issues     rebuild       ECM dead           2026-06-02 10:00:00  3            Internal fault confirmed  2026-06-02 12:00:00  1          1           2026-06-02 10:00:00
```

**`replacements`**
```
id  number         order_id  parent_id  complaint_id  type  charge  pay_status  status     created_by  created_at
1   REP-2026-0001  33        NULL       1             free  0.00    none        completed  1           2026-06-02 11:00:00
```

**`replacement_lines`**
```
id  replacement_id  order_line_id  sku       product_name           old_serial  new_serial
1   1               64             ECM-2024  Engine Control Module  SN-404      SN-414
```

**`order_events`** (delta — after spine completed events)
```
id  order_id  event              metadata                                                          created_by  created_at
6   33        complaint_opened   {"complaint":"CMP-2026-0001","serial":"SN-404"}                   1           2026-06-02 10:00:00
7   33        complaint_examined {"result":"internal_issues"}                                      3           2026-06-02 11:00:00
8   33        replacement_issued {"rep":"REP-2026-0001","old":"SN-404","new":"SN-414","free":true} 1           2026-06-02 11:30:00
9   33        complaint_closed   {"outcome":"rebuild"}                                             1           2026-06-02 12:00:00
```

**`inventory_serials`**
```
serial  status      location      note
SN-404  to_rebuild  Rebuild Area  CMP-2026-0001 — internal fault
SN-414  sold        NULL          with Marcus Webb — REP-2026-0001 (free)
SN-504  sold        NULL          with Marcus Webb — TCM, untouched
```

**`inventory_movements`** (delta)
```
id   serial  type             from         to            reference      notes
210  SN-404  return_in        NULL         Receiving     CMP-2026-0001  returned at counter
211  SN-404  transfer         Receiving    Rebuild Area  CMP-2026-0001  internal fault → rebuild
212  SN-414  replacement_out  Warehouse A  NULL          REP-2026-0001  free replacement, counter
```

> **No `payments`** (free). Chain: this rep `parent_id=NULL` (root); a 2nd failure would chain `parent_id=1` (see global chain rule).

---

### B4 — COMPLAINT → CHARGED replacement (no_fault, cx insists) · ORD-2026-0034

**Fork point:** completed order (ECM SN-405 / TCM SN-505 `sold`). Cx claims ECM `SN-405` dead → tech finds **NO fault** (cx wrong) → cx **still insists** on new unit → **charged** replacement `SN-415` ($216.50 cash). Old good unit → back to stock.

```
complaint (CMP-2026-0002: line 66, serial SN-405) → verify → under_examination
  → tech inspect → NO fault (fully works)                    ◄ Gate 2
  → cx INSISTS → DECISION: no_fault → CHARGED
  → replacements (REP-2026-0002: type=charged, charge=216.50, pay_status=unpaid)
  → AvaTax calculateTax() quote on charged unit
  → GATE: cx PAYS FIRST → payments (payable_type=replacement, cash 216.50 paid)
       → AvaTax commitInvoice() code=REP-2026-0002 · replacement pay_status=paid
  → replacement_lines (line 66, old=SN-405, new=SN-415)
  → inventory_serials (SN-405 → in_stock back_to_stock · SN-415 → sold)
  → complaint closed (unit_outcome=back_to_stock)
```

**`complaints`**
```
id  number         order_id  order_line_id  serial  status  examination_result  unit_outcome   issue_description  unit_received_at     examined_by  examination_notes            closed_at            closed_by  created_by  created_at
2   CMP-2026-0002  34        66             SN-405  closed  no_fault_found      back_to_stock  ECM dead (claim)   2026-06-05 10:00:00  3            Fully functional, no defect  2026-06-05 12:00:00  1          1           2026-06-05 10:00:00
```

**`replacements`**
```
id  number         order_id  parent_id  complaint_id  type     charge  pay_status  status     created_by  created_at
2   REP-2026-0002  34        NULL       2             charged  216.50  paid        completed  1           2026-06-05 11:30:00
```

**`replacement_lines`**
```
id  replacement_id  order_line_id  sku       product_name           old_serial  new_serial
2   2               66             ECM-2024  Engine Control Module  SN-405      SN-415
```

**`payments`** (the charged replacement — `payable_type=replacement`)
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
120 34        replacement   2           payment  cash    216.50   paid    2026-06-05 11:30:00   1            1
```
> Paid **before** handover. AvaTax committed against REP number.

**`order_events`** (delta)
```
id  order_id  event              metadata                                                              created_by  created_at
6   34        complaint_opened   {"complaint":"CMP-2026-0002","serial":"SN-405"}                       1           2026-06-05 10:00:00
7   34        complaint_examined {"result":"no_fault_found"}                                           3           2026-06-05 11:00:00
8   34        replacement_issued {"rep":"REP-2026-0002","old":"SN-405","new":"SN-415","charged":true}  1           2026-06-05 11:30:00
9   34        complaint_closed   {"outcome":"back_to_stock"}                                           1           2026-06-05 12:00:00
```

**`inventory_serials`**
```
serial  status    location     note
SN-405  in_stock  Warehouse A  CMP-2026-0002 — no fault, back to stock (cx claim wrong)
SN-415  sold      NULL         with Marcus Webb — REP-2026-0002 (charged)
SN-505  sold      NULL         with Marcus Webb — TCM, untouched
```

**`inventory_movements`** (delta)
```
id   serial  type             from         to            reference      notes
220  SN-405  return_in        NULL         Receiving     CMP-2026-0002  returned at counter
221  SN-405  transfer         Receiving    Tech Area     CMP-2026-0002  inspect
222  SN-405  adjustment       Tech Area    Warehouse A   CMP-2026-0002  no fault → back to stock
223  SN-415  replacement_out  Warehouse A  NULL          REP-2026-0002  charged replacement, counter
```

---

### B5 — COMPLAINT → no_fault, cx ACCEPTS → SAME unit returned to cx · ORD-2026-0035

**Fork point:** completed order (ECM SN-406 / TCM SN-506 `sold`). Cx reports ECM `SN-406` faulty, drops it → tech finds **no fault** → cx **accepts** (no charged replacement) → **same unit returned**. No replacement, no charge, no new serial.

```
complaint (CMP-2026-0003: line 68, serial SN-406) → verify → under_examination
  → tech inspect → NO fault                                  ◄ Gate 2
  → cx ACCEPTS → return SAME unit
  → inventory_serials (SN-406: under_examination → sold, back to same cx)
  → complaint closed (unit_outcome=returned_to_customer)
  → NO replacement · NO payment · NO new serial
```

**`complaints`**
```
id  number         order_id  order_line_id  serial  status  examination_result  unit_outcome          issue_description   unit_received_at     examined_by  examination_notes                   closed_at            closed_by  created_by  created_at
3   CMP-2026-0003  35        68             SN-406  closed  no_fault_found      returned_to_customer  ECM not turning on  2026-06-08 09:00:00  3            Fully functional — no defect found  2026-06-09 10:00:00  1          1           2026-06-08 09:00:00
```

**`order_events`** (delta)
```
id  order_id  event              metadata                                          created_by  created_at
6   35        complaint_opened   {"complaint":"CMP-2026-0003","serial":"SN-406"}   1           2026-06-08 09:00:00
7   35        complaint_examined {"result":"no_fault_found"}                       3           2026-06-08 14:00:00
8   35        complaint_closed   {"outcome":"returned_to_customer"}                1           2026-06-09 10:00:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-406  sold    NULL      with Marcus Webb — no fault, returned (CMP-2026-0003)
SN-506  sold    NULL      with Marcus Webb — TCM, untouched
```

**`inventory_movements`** (delta — round-trip, same unit)
```
id   serial  type        from         to            reference      notes
230  SN-406  return_in   NULL         Receiving     CMP-2026-0003  dropped at counter for exam
231  SN-406  transfer    Receiving    Tech Area     CMP-2026-0003  inspect
232  SN-406  adjustment  Tech Area    NULL          CMP-2026-0003  no fault → returned to cx
```

> **No `replacements`, `payments`, `returns`, `shipments`.** Serial round-trip `sold → under_examination → sold`. (If cx had **insisted** → B4 charged replacement instead.)

---

### B6 — RETURN + REFUND (changed mind, partial) · ORD-2026-0036

**Fork point:** completed order (ECM SN-407 / TCM SN-507 `sold`). Cx **changed mind on the TCM** `SN-507`, brings it back (Day +10). Not a fault → **return for refund**. Unit fine → back to stock, **cash refund $194.85** (TCM line_total). Keeps the ECM. **Fees kept** (service rendered).

```
return (RET-2026-0001: line 71 slot, serial SN-507, complaint_id=NULL)
  → VERIFY serial matches order (anti-fraud)                 ◄ Gate 1
  → inventory_serials (SN-507: sold → under_examination)
  → INSPECT condition → good                                 ◄ Gate 2
  → APPROVE → goods in
  → refunds (REF-2026-0002: return_id=1, reason=return, TCM line_total)
  → refund_lines (line 71, amount=180.00 unit only, tax=14.85)   ◄ fees KEPT
  → payments (kind=refund, payable_type=refund, cash 194.85 refunded)
  → AvaTax adjustTransaction (original invoice, −14.85 unit tax only)
  → inventory_serials (SN-507: under_examination → in_stock, back_to_stock)
  → return closed
```

**`returns`** (goods)
```
id  number         order_id  complaint_id  reason         status  created_by  created_at
1   RET-2026-0001  36        NULL          changed_mind   closed  1           2026-06-08 10:00:00
```

**`return_lines`**
```
id  return_id  order_line_id  serial  condition  restock
1   1          71             SN-507  good       back_to_stock
```

**`refunds`** (money — `return_id` set)
```
id  number         order_id  return_id  reason  total_amount  total_tax  method  status    created_by  created_at
2   REF-2026-0002  36        1          return  194.85        14.85      cash    refunded  1           2026-06-08 10:30:00
```

**`refund_lines`** (TCM only, **unit + tax only — fees kept**)
```
id  refund_id  order_line_id  amount   tax
3   2          71             180.00   14.85
```
> `amount` = **unit only** (fees = service rendered → kept) per global `refund_lines.amount` rule. Contrast B2 cancel = unit + fees.

**`payments`** (the refund)
```
id  order_id  payable_type  payable_id  kind     method  amount   status     received_at           received_by  created_by
130 36        refund        2           refund   cash    194.85   refunded   2026-06-08 10:30:00   1            1
```

**`order_events`** (delta)
```
id  order_id  event             metadata                                        created_by  created_at
6   36        return_requested  {"return":"RET-2026-0001","serial":"SN-507"}    1           2026-06-08 10:00:00
7   36        refunded          {"amount":"194.85","method":"cash"}             1           2026-06-08 10:30:00
8   36        return_closed     {"restock":"back_to_stock"}                     1           2026-06-08 10:30:00
```

**`inventory_serials`**
```
serial  status    location     note
SN-407  sold      NULL         with Marcus Webb — ECM kept
SN-507  in_stock  Warehouse A  RET-2026-0001 — returned good, back to stock
```

**`inventory_movements`** (delta)
```
id   serial  type        from         to           reference      notes
240  SN-507  return_in   NULL         Receiving    RET-2026-0001  brought back at counter
241  SN-507  transfer    Receiving    Tech Area    RET-2026-0001  condition check
242  SN-507  adjustment  Tech Area    Warehouse A  RET-2026-0001  good → back to stock
```

> **Financial:** order $525.01 − refund $194.85 (TCM line_total) = **net $330.16** (ECM + all fees kept). **goods = `returns` · money = `refunds`** (linked via `refunds.return_id`).

---

### B-CHAIN — Replacement CHAIN (multi-round, one slot) · ORD-2026-0037

**Fork point:** completed order (ECM `SN-417` line 72 / TCM `SN-517` line 73 `sold`). The **ECM keeps failing** — 3 rounds on the **same slot** (line 72). Each round = full complaint pipeline; replacements chain via `parent_id`.

1. **R1** Day +7: `SN-417` dead → internal fault → **free** rep `SN-427` (old → rebuild)
2. **R2** Day +14: `SN-427` dead → internal fault → **free** rep `SN-437` (old → rebuild)
3. **R3** Day +21: cx claims `SN-437` dead → **no fault (cx lying)** → cx insists → **charged** rep `SN-447` $216.50 (old `SN-437` good → back_to_stock)

> Demonstrates: `parent_id` self-ref chain · root `order_id` on every rep (flat count, no recursion) · slot = line 72, serial = whichever unit fills it now (`SN-417→427→437→447`).

**`complaints`** (one per round; `order_line_id=72` every round, `serial` = unit at claim time)
```
id  number         order_id  order_line_id  serial  status  examination_result  unit_outcome   issue_description  examined_by  closed_at            created_at
4   CMP-2026-0004  37        72             SN-417  closed  internal_issues     rebuild        ECM dead           3            2026-06-02 12:00:00  2026-06-02 10:00:00
5   CMP-2026-0005  37        72             SN-427  closed  internal_issues     rebuild        ECM dead           3            2026-06-09 12:00:00  2026-06-09 10:00:00
6   CMP-2026-0006  37        72             SN-437  closed  no_fault_found      back_to_stock  ECM dead (claim)   3            2026-06-16 12:00:00  2026-06-16 10:00:00
```
> (cols `unit_received_at`/`examination_notes`/`closed_by`/`created_by` omitted for space — same schema as B3.)

**`replacements`** (chain via `parent_id`; root `order_id=37` on all)
```
id  number         order_id  parent_id  complaint_id  type     charge   pay_status  status     created_at
3   REP-2026-0003  37        NULL       4             free     0.00     none        completed  2026-06-02 11:00:00
4   REP-2026-0004  37        3          5             free     0.00     none        completed  2026-06-09 11:00:00
5   REP-2026-0005  37        4          6             charged  216.50   paid        completed  2026-06-16 11:30:00
```
> `parent_id`: REP-0004→0003, REP-0005→0004. **Count chain = `SELECT count(*) FROM replacements WHERE order_id=37` → 3** (flat, no recursive CTE). Scales to N rounds with no depth cost / no hot-row. `created_by=1` (staff) on all — column omitted for space (same schema as B3).

**`replacement_lines`**
```
id  replacement_id  order_line_id  sku       product_name           old_serial  new_serial
3   3               72             ECM-2024  Engine Control Module  SN-417      SN-427
4   4               72             ECM-2024  Engine Control Module  SN-427      SN-437
5   5               72             ECM-2024  Engine Control Module  SN-437      SN-447
```

**`payments`** (only R3 charged)
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
140 37        replacement   5           payment  cash    216.50   paid    2026-06-16 11:30:00   1            1
```
> R1/R2 free → no payment. R3 charged → pay before handover, `payable_type=replacement`, AvaTax commit code = `REP-2026-0005`.

**`order_events`** (order 37 — per round: opened→examined→issued→closed; 12 rows, ids 6–17)
```
id  order_id  event              metadata                                                              created_at
6   37        complaint_opened   {"complaint":"CMP-2026-0004","serial":"SN-417"}                       2026-06-02 10:00:00
7   37        complaint_examined {"result":"internal_issues"}                                          2026-06-02 11:00:00
8   37        replacement_issued {"rep":"REP-2026-0003","old":"SN-417","new":"SN-427","free":true}     2026-06-02 11:30:00
9   37        complaint_closed   {"outcome":"rebuild"}                                                 2026-06-02 12:00:00
10  37        complaint_opened   {"complaint":"CMP-2026-0005","serial":"SN-427"}                       2026-06-09 10:00:00
11  37        complaint_examined {"result":"internal_issues"}                                          2026-06-09 11:00:00
12  37        replacement_issued {"rep":"REP-2026-0004","old":"SN-427","new":"SN-437","free":true}     2026-06-09 11:30:00
13  37        complaint_closed   {"outcome":"rebuild"}                                                 2026-06-09 12:00:00
14  37        complaint_opened   {"complaint":"CMP-2026-0006","serial":"SN-437"}                       2026-06-16 10:00:00
15  37        complaint_examined {"result":"no_fault_found"}                                           2026-06-16 11:00:00
16  37        replacement_issued {"rep":"REP-2026-0005","old":"SN-437","new":"SN-447","charged":true}  2026-06-16 11:30:00
17  37        complaint_closed   {"outcome":"back_to_stock"}                                           2026-06-16 12:00:00
```
> `created_by` omitted for space — `=1` (staff) on all rows **except** the 3 `complaint_examined` rows (`=3`, tech). Same schema as B3/B4.

**`inventory_serials`** (final)
```
serial  status      location      note
SN-417  to_rebuild  Rebuild Area  CMP-2026-0004 — internal fault
SN-427  to_rebuild  Rebuild Area  CMP-2026-0005 — internal fault
SN-437  in_stock    Warehouse A   CMP-2026-0006 — no fault, back to stock (cx claim wrong)
SN-447  sold        NULL          with Marcus Webb — REP-2026-0005 (charged)
SN-517  sold        NULL          with Marcus Webb — TCM, untouched
```

**`inventory_movements`** (per round — same pattern as B3/B4, omitted for space):
- **R1/R2 (faulty):** `return_in` (counter→Receiving) → `transfer` (Receiving→**Rebuild Area**) → `replacement_out` (new unit, Warehouse A→cx) — 3 rows each.
- **R3 (no_fault good):** `return_in` → `transfer` (Receiving→**Tech Area**) → `adjustment` (Tech→Warehouse A, back_to_stock) → `replacement_out` — 4 rows.
- **~10 rows total.**

**Chain view + canonical timeline**
```
SN-417 → SN-427 → SN-437 → SN-447         (ECM slot, line 72)
         REP-0003  REP-0004  REP-0005
parent:  NULL      3         4
type:    free      free      charged
exam:    fault     fault     no_fault (cx lying)
old→:    rebuild   rebuild   back_to_stock
```
| round | date | complaint | serial in slot | exam | rep | type | old → | new → cx |
|---|---|---|---|---|---|---|---|---|
| R1 | 2026-06-02 | CMP-2026-0004 | SN-417 | internal_issues | REP-2026-0003 | free | rebuild | SN-427 |
| R2 | 2026-06-09 | CMP-2026-0005 | SN-427 | internal_issues | REP-2026-0004 | free | rebuild | SN-437 |
| R3 | 2026-06-16 | CMP-2026-0006 | SN-437 | no_fault_found | REP-2026-0005 | charged | back_to_stock | SN-447 |

> **Scalability:** chain depth is unbounded — every rep carries root `order_id` + `parent_id`. "How many reps on this order" = one flat `count(*)` (no recursion). DB sequence for numbers (never `max()+1`), serial lock order fixed, movements append-only → **no deadlock / no hot-row** at millions of rows.

---

## 5. State machine (cash/counter)

**`orders.status`:** `pending → processing → completed` · or `→ cancelled` (terminal). Never back.
**`orders.payment_status`:** `unpaid → paid` (one-way; refund does NOT flip it back — net via payments).
**`inventory_serials.status`** (5 states: `in_stock` · `reserved` · `sold` · `under_examination` · `to_rebuild`):
```
in_stock ──force-process──► reserved ──handover (paid)──► sold
   ▲                          │                            │
   └──── cancel (release) ────┘                            │ post-sale: return_in
                                                           ▼
                                                   under_examination
                                                           │ inspect
                ┌──────────────────────────────┼──────────────────────────────┐
        fault → to_rebuild        no_fault + good → in_stock        no_fault + accept → sold
           (B3 / B-CHAIN)      (B4 charged · B6 return = "back_to_stock")   (B5, same unit to cx)
```
> `back_to_stock` / `rebuild` = **actions** (resulting status = `in_stock` / `to_rebuild`), not status values. 5 statuses total.

| status | payment_status | when | serials | terminal? |
|---|---|---|---|---|
| pending | unpaid | placed | in_stock | no |
| processing | unpaid | force-processed | reserved (locked) | no |
| processing | paid | prepaid (B2) | reserved | no |
| completed | paid | handover (spine) | sold | yes (then post-sale B3–B6, B-CHAIN) |
| cancelled | unpaid | no-show (B1) | released → in_stock | yes |
| cancelled | paid | cancel-refund (B2) | released → in_stock | yes |

---

## 6. Invariants (all guardrails — one list)

**Order / payment**
- `grand_total = Σ line_totals + Σ fee_totals + shipping`
- cash `payment.amount` = `grand_total` **exactly** (no partial), one settling payment, USD
- **`status=completed` requires `payment_status=paid`** (handover only when paid)
- pickup = all-or-nothing (all units handed over together)

**Force-process / serials**
- `origin ∈ {admin, web_admin}` → serials `reserved` **before** payment (process decision drives it)
- a serial is `reserved` by **at most one** order (locked while staged)
- `reserved → in_stock` (cancel) and `reserved → sold` (handover): the only **movement row** is the `sale` at handover — reserve/release write no movement row

**Cancel / return / refund**
- **cancel** only while goods **not left** (serials `reserved`); goods left → **return**, not cancel
- cancel **unpaid** → no money (B1) · cancel **paid** → **full refund incl. fees** (B2), `refunds.reason=cancel`, `return_id=NULL`
- **return** = goods back → `returns` + `return_lines`; **refund** = money → `refunds` + `refund_lines`; linked via `refunds.return_id`
- return refund = returned line `line_total` (unit + tax); **per-line fees + charged-rep fees = non-refundable** (service rendered) — B6
- `refund_lines.amount`: cancel → unit + fees (B2) · return → unit only (B6) — per global rule
- refund issued **only after** unit received + condition decided (return gate)
- `orders.payment_status` stays `paid` after refund; **net money = Σ `payments` `kind=payment` − Σ `kind=refund`**

**Complaint / replacement**
- **replacement requires a complaint** (`replacements.complaint_id` NOT NULL) — pipeline only, **no skip, no goodwill**
- pipeline fixed: complaint → intake verify (anti-fraud) → tech inspect → decision
- decision: `fault_found`/`internal_issues` → **free** (old → rebuild) · `no_fault_found` + accepts → **return to cx** (same unit, no $) · `no_fault_found` + insists → **charged** (old → back_to_stock, cx pays)
- charged replacement → cx **pays before handover** (`pay_status=paid` before `replacement_out`); payment `payable_type=replacement`; AvaTax doc code = REP number
- chain: `replacements.parent_id` self-ref; every rep carries root `order_id` → flat count, no recursion
- `complaint.order_line_id` = slot; `serial` = current unit in slot

**AvaTax**
- all `tax_amount` from AvaTax API, rounded 2 dp per line
- `calculateTax()` quote at placement → `commitInvoice()` at payment (doc code = order/REP number)
- unpaid cancel → quote never committed → nothing to void · paid cancel/return → `adjustTransaction` on committed invoice
- free replacement = no sale → no AvaTax

**Logs (no overlap)**
- `order_events` = admin/user timeline (lifecycle + complaint/replacement/return milestones)
- `activity_log` (spatie) = developer audit (CRUD, causer)
- `order_notes` = human free-text (`private`/`customer`, soft delete)

---

## 7. Branch → TDD test matrix (build plan)

> One `/create` TDD run per branch. This master section = the fixture + assertions for each.

| Branch | Scenario | Key assertions (feature + unit tests) |
|---|---|---|
| **Spine** | place → force-process → pay cash → complete (pickup) | serials reserve before pay; `completed` blocked until `paid`; grand_total math; AvaTax quote→commit; sale movement at handover |
| **B1** | cancel unpaid (no-show) | serials `reserved → in_stock`; no payment row; no movement row; status `cancelled`/`unpaid`; AvaTax nothing to void |
| **B2** | cancel-refund (paid, not collected) | full refund incl. fees; `refunds.reason=cancel`, `return_id=NULL`; `payments` refund row; AvaTax full adjust; payment_status stays `paid`; net $0 |
| **B3** | complaint → free replacement | complaint required; fault → `free`; old → `to_rebuild`; new reserve→sold; no payment; events 4× |
| **B4** | complaint → charged replacement | no_fault + insist → `charged`; **pay before handover**; `payable_type=replacement`; old → `back_to_stock`; AvaTax REP commit |
| **B5** | complaint → no_fault, return to cx | same unit `sold→under_examination→sold`; no replacement/payment/new serial; `unit_outcome=returned_to_customer` |
| **B6** | return + refund (changed mind) | `returns`+`return_lines` (goods) + `refunds`+`refund_lines` (money) linked; refund = unit+tax only, **fees kept**; serial good → `back_to_stock`; AvaTax unit-tax adjust |
| **B-CHAIN** | replacement chain (3 rounds) | `parent_id` self-ref links rounds; root `order_id` on every rep; flat `count(*)` = chain length (no recursion); free→free→charged; slot serial walks SN-417→427→437→447 |

**Implementation order:** Spine → B1 → B2 → B3 → B5 → B4 → B6 → B-CHAIN (simple → complex; replacement before charged; chain last).

---

## 8. Relationship to the focused examples

This master is the **complete cash/counter map**. The focused `ex-*` docs are **kept** as single-scenario fixtures (deeper prose per case):

| Branch here | Focused example |
|---|---|
| Spine + B1 | ex-001 (pickup, cash, cancel fork) |
| B2 | ex-006 (cancel-refund) |
| B3 / B4 / B-CHAIN | ex-003 (replacement chain, free+charged) |
| B5 | ex-004 (no-fault return-to-cx) |
| B6 | ex-005 (return + refund) |

**ex-002** (card + shipped) = different family, not in this master. **global.md** = shared rulebook (enums, numbering, money) — used by all. Nothing deleted; master + examples + global stay consistent (same locked schema + numbering).
