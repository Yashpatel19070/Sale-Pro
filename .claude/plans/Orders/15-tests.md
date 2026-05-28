# 15 — Tests (TDD Spec)

> **Layer 2.** Depends on all Layer 1 files (`01-enums.md`, `02-permissions.md`, `03-schema.md`, `14-events-inventory.md`).
>
> **This file IS the contract.** Every test below is a checkbox. Implementation files (07-service, 11-controller, etc.) must make every test pass. No test = no requirement.

## Scope

Defines:
- Every Pest **unit test** for `OrderService` (Layer 4)
- Every Pest **feature test** for `OrderController` (Layer 4)
- The fixture data each test uses (ex-19 as canonical)

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Pest framework, not PHPUnit | Project standard (per `tests/Pest.php`) |
| `RefreshDatabase` trait on every test | Clean DB per test |
| Tests use ex-19 fixture data verbatim (Rachel Park, ECM-2024, SN-200, $286.86 total) | Single concrete scenario keeps tests greppable |
| Unit tests = `tests/Unit/OrderServiceTest.php` | One file per service |
| Feature tests = `tests/Feature/OrderControllerTest.php` | One file per controller |
| Each test name = `it_<expected behavior>` | Pest convention |
| Permission seeded via `OrderPermissionSeeder` in `beforeEach` | Matches Customer module pattern |
| Roles assigned via `givePermissionTo([...])` per test | Lets each test pick role |

---

## Fixture builder helpers (defined in test file, used everywhere)

```php
// tests/Pest.php or top of test file
function ex19Customer(): Customer
{
    return Customer::factory()->create([
        'name'  => 'Rachel Park',
        'email' => 'rachel@example.com',
        'phone' => '555-190-0001',
        'tax_exempt' => false,
    ]);
}

function ex19Listing(): ProductListing
{
    $product = Product::factory()->create([
        'sku'  => 'ECM-2024',
        'name' => 'Engine Control Module',
    ]);
    return ProductListing::factory()->active()->for($product)->create();
}

function ex19Serial(ProductListing $listing, InventoryLocation $location): InventorySerial
{
    return InventorySerial::factory()
        ->inStock()
        ->atLocation($location)
        ->forProduct($listing->product)
        ->create(['serial_number' => 'SN-200']);
}

function ex19Payload(int $customerId, int $listingId): array
{
    return [
        'customer_id'   => $customerId,
        'source'        => 'walk_in',
        'payment_method'=> 'cash',
        'billing_address_id'  => null,   // service will fill shop address
        'shipping_address_id' => null,   // pickup
        'shipping' => 0,
        'lines' => [
            [
                'product_listing_id' => $listingId,
                'unit_price'         => 200.00,
                'tax_amount'         => 16.50,
                'fees' => [
                    ['name' => 'Programming Fee', 'amount' => 40.00, 'tax_amount' => 3.30],
                    ['name' => 'Gas Tuning Fee',  'amount' => 25.00, 'tax_amount' => 2.06],
                ],
            ],
        ],
    ];
}
```

> Tax values are pre-computed (mocking AvaTax). AvaTax integration tests in `tests/Unit/AvaTaxServiceTest.php` verify the live calculation separately.

---

## Unit tests — `tests/Unit/OrderServiceTest.php`

### `store(array $data, User $createdBy): Order`

#### Order header
- ☐ `it_creates_order_with_walk_in_source`
- ☐ `it_creates_order_with_pending_status`
- ☐ `it_creates_order_with_unpaid_payment_status`
- ☐ `it_sets_created_by_to_acting_user`
- ☐ `it_generates_order_number_in_format_ORD-{year}-{seq}`

#### Billing & shipping snapshots
- ☐ `it_sets_billing_snapshot_to_shop_address_for_cash` — when `config('shop.billing.*')` is set, billing_first_name etc. equal those config values
- ☐ `it_sets_billing_snapshot_to_null_when_shop_config_unset` — when `config('shop.billing.first_name', etc.)` are null, all 10 billing_* columns persist as NULL (no hardcoded "NPC Sales Pro LLC")
- ☐ `it_sets_shipping_snapshot_to_null_for_pickup` — all 10 shipping_* columns NULL
- ☐ `it_sets_shipped_at_and_shipped_by_to_null`
- ☐ `it_sets_delivered_at_and_delivered_by_to_null`

#### Order lines
- ☐ `it_creates_order_line_row_per_payload_line`
- ☐ `it_snapshots_sku_and_product_name_from_listing`
- ☐ `it_leaves_inventory_serial_id_null_at_store` — serial NOT allocated yet (allocation moved to recordCashPayment per #6); column persists as NULL
- ☐ `it_keeps_serial_status_as_in_stock_after_store` — serial.status unchanged, only allocated via FK
- ☐ `it_computes_line_total_as_unit_price_plus_tax_amount` — 200 + 16.50 = 216.50
- ☐ `it_does_not_store_tax_rate_on_order_line` — column doesn't exist

#### Order line fees
- ☐ `it_creates_order_line_fee_row_per_payload_fee` — 2 rows for ex-19
- ☐ `it_sets_fee_total_as_amount_plus_tax_amount` — 40 + 3.30 = 43.30; 25 + 2.06 = 27.06
- ☐ `it_sets_created_by_on_each_fee_row`
- ☐ `it_cascades_delete_fees_when_line_deleted` — DB-level CASCADE check

#### Totals
- ☐ `it_computes_grand_total_as_sum_of_line_totals_plus_fee_totals_plus_shipping`
  - 216.50 + 43.30 + 27.06 + 0 = 286.86
- ☐ `it_stores_only_shipping_and_grand_total_on_orders_table` — no subtotal/fees/tax columns

#### Inventory side effects
- ☐ `it_does_not_create_inventory_movement_on_store` — zero movement rows
- ☐ `it_does_not_change_serial_status_on_store` — stays `in_stock`

#### Events
- ☐ `it_inserts_order_placed_event_in_same_transaction`
- ☐ `it_sets_order_placed_metadata_to_correct_shape` — `{sku, product_name, grand_total}`
- ☐ `it_sets_event_created_by_to_acting_user`

#### Payments
- ☐ `it_does_not_create_payment_row_on_store`

#### Transaction safety
- ☐ `it_rolls_back_all_changes_if_line_creation_fails`
- ☐ `it_rolls_back_all_changes_if_fee_creation_fails`
- ☐ `it_rolls_back_all_changes_if_event_insert_fails`

#### Edge cases
- ☐ `it_throws_DomainException_when_no_in_stock_serial_available`
- ☐ `it_throws_when_unique_constraint_blocks_double_allocation`

---

### `recordCashPayment(Order $order, array $data, User $createdBy): Payment`

#### Payment row
- ☐ `it_creates_payment_row_with_method_cash`
- ☐ `it_sets_payment_status_to_paid`
- ☐ `it_sets_cash_received_at_to_now`
- ☐ `it_sets_payment_amount_to_grand_total`
- ☐ `it_sets_payment_created_by_to_acting_user`
- ☐ `it_sets_payable_type_to_order` — via morph map (not FQN class name)

#### Order state
- ☐ `it_sets_orders_payment_status_to_paid`
- ☐ `it_advances_orders_status_from_pending_to_processing`

#### Inventory side effects
- ☐ `it_does_not_create_inventory_movement_on_payment`
- ☐ `it_does_not_change_serial_status_on_payment` — stays `in_stock`
- ☐ `it_allocates_serial_when_recording_payment` — `order_lines.inventory_serial_id` was NULL after store; becomes the allocated serial id after recordCashPayment

#### Events
- ☐ `it_inserts_payment_received_event_in_same_transaction`
- ☐ `it_sets_payment_received_metadata_to_correct_shape` — `{method, amount, shipping}`

#### Transaction safety
- ☐ `it_rolls_back_all_changes_if_payment_insert_fails`
- ☐ `it_rolls_back_all_changes_if_event_insert_fails`
- ☐ `it_rolls_back_allocation_when_payment_step_fails` — if allocation succeeds but later step throws, serial stays unassigned

#### Edge cases
- ☐ `it_throws_DomainException_when_order_already_paid`
- ☐ `it_throws_DomainException_when_order_status_not_pending`
- ☐ `it_throws_DomainException_when_amount_does_not_match_grand_total`
- ☐ `it_throws_DomainException_when_no_in_stock_serial_at_payment` — NEW (moved from store)

---

### `complete(Order $order, User $completedBy): Order`

#### Order state
- ☐ `it_sets_orders_status_from_processing_to_complete`
- ☐ `it_does_not_touch_payment_or_lines_or_fees`

#### Inventory side effects (REQUIRED)
- ☐ `it_creates_inventory_movement_with_type_sale`
- ☐ `it_sets_movement_from_location_to_serials_current_location`
- ☐ `it_sets_movement_to_location_to_null`
- ☐ `it_sets_movement_reference_to_order_number`
- ☐ `it_changes_serial_status_from_in_stock_to_sold`

#### Events
- ☐ `it_inserts_completed_event_in_same_transaction`
- ☐ `it_sets_completed_metadata_to_empty_object`

#### Transaction safety
- ☐ `it_rolls_back_all_changes_if_inventory_movement_fails`
- ☐ `it_rolls_back_all_changes_if_serial_update_fails`

#### Edge cases
- ☐ `it_throws_DomainException_when_order_status_not_processing`
- ☐ `it_throws_DomainException_when_serial_not_in_stock` — defense in depth

---

### `update(Order $order, array $data): Order`

- ☐ `it_updates_order_when_status_is_pending`
- ☐ `it_recalculates_grand_total_after_line_changes`
- ☐ `it_recalculates_line_total_when_line_changed`
- ☐ `it_recalculates_fee_total_when_fee_changed`
- ☐ `it_throws_DomainException_when_order_not_pending`

---

### `delete(Order $order): void`

- ☐ `it_calls_audit_log_BEFORE_delete` — `AuditLogService::log($order, 'deleted')` fires inside transaction, before the row is removed
- ☐ `it_permanently_deletes_order_row` — `assertDatabaseMissing`
- ☐ `it_cascades_delete_to_order_lines` — `assertDatabaseMissing`
- ☐ `it_cascades_delete_to_order_line_fees` — `assertDatabaseMissing`
- ☐ `it_cascades_delete_to_order_events` — `assertDatabaseMissing`
- ☐ `it_cascades_delete_to_payments` — `assertDatabaseMissing`
- ☐ `it_does_not_cascade_delete_to_users_or_customers` — those rows remain
- ☐ `it_throws_DomainException_when_order_not_pending`

---

### Integration tests (full lifecycle)

- ☐ `it_shows_correct_serial_status_at_each_stage`
  - after `store()` → serial `in_stock`, movements = 0
  - after `recordCashPayment()` → serial `in_stock` still, movements = 0
  - after `complete()` → serial `sold`, movements = 1
- ☐ `it_shows_correct_order_events_at_each_stage`
  - after `store()` → 1 event (`order_placed`)
  - after `recordCashPayment()` → 2 events
  - after `complete()` → 3 events
- ☐ `it_atomic_invariant_holds_at_every_stage`
  - if `order_events.order_placed` exists → `orders` row + `order_lines` + `order_line_fees` all exist
  - if `order_events.payment_received` exists → `payments` row exists + `orders.payment_status=paid`
  - if `order_events.completed` exists → `inventory_movements.sale` exists + `serial.status=sold`

---

## Feature tests — `tests/Feature/OrderControllerTest.php`

### Setup (shared via `beforeEach`)
```php
beforeEach(function () {
    $this->seed(OrderPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'orders.viewAny','orders.view','orders.create','orders.update',
        'orders.delete','orders.recordPayment','orders.complete',
    ]);

    $this->sales = User::factory()->create();
    $this->sales->givePermissionTo([
        'orders.viewAny','orders.view','orders.create','orders.update',
        'orders.recordPayment','orders.complete',
    ]); // NO orders.delete

    $this->customer = ex19Customer();
    $this->location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $this->listing  = ex19Listing();
    $this->serial   = ex19Serial($this->listing, $this->location);
});
```

---

### `index` (`GET /admin/orders`)
- ☐ `admin_can_view_orders_index` — assertOk
- ☐ `sales_can_view_orders_index` — assertOk
- ☐ `user_without_orders_viewAny_cannot_view_index` — assertForbidden

### `show` (`GET /admin/orders/{order}`)
- ☐ `admin_can_view_order_show`
- ☐ `show_page_has_record_payment_modal_when_unpaid` — response contains `data-testid="record-payment-modal"`
- ☐ `show_page_omits_record_payment_modal_when_paid` — response does NOT contain `data-testid="record-payment-modal"`
- ☐ `user_without_orders_view_cannot_view_show` — assertForbidden

### `create` (`GET /admin/orders/create`)
- ☐ `admin_can_view_create_form`
- ☐ `create_form_has_new_address_modal` — response contains `data-testid="new-address-modal"` and `data-testid="new-address-button"`
- ☐ `user_without_orders_create_cannot_view_create_form` — assertForbidden

### `storeCustomerAddress` (`POST /admin/orders/customer-addresses`)
- ☐ `admin_can_store_customer_address_via_json` — POST with valid payload + Accept: application/json; assert 201 + JSON shape `{id, label, summary, address_line1, city, state, postal_code, country}`
- ☐ `store_customer_address_returns_json_errors_on_validation_failure` — POST with empty body; assert 422 + JSON error bag
- ☐ `user_without_orders_create_cannot_store_customer_address` — assertForbidden

### `store` (`POST /admin/orders`)
- ☐ `admin_can_create_walk_in_cash_order_with_per_line_fees`
  - POST ex19Payload
  - assertRedirect to `orders.show`
  - assertDatabaseHas orders: source=walk_in, status=pending, payment_status=unpaid, grand_total=286.86
  - assertDatabaseHas orders: billing_first_name="NPC Sales Pro LLC", shipping_first_name=null
  - assertDatabaseHas order_lines: serial allocated, line_total=216.50
  - assertDatabaseHas order_line_fees (2 rows): Programming Fee 43.30, Gas Tuning Fee 27.06
  - assertDatabaseHas order_events: event=order_placed
  - assertDatabaseMissing inventory_movements
- ☐ `store_fails_validation_when_lines_array_empty`
- ☐ `store_fails_validation_when_customer_id_missing`
- ☐ `store_fails_validation_when_fee_name_missing`
- ☐ `user_without_orders_create_cannot_post_store` — assertForbidden

### `edit` (`GET /admin/orders/{order}/edit`)
- ☐ `admin_can_edit_pending_order`
- ☐ `edit_form_prefills_all_fields_from_existing_order` — response HTML contains `window.__existingOrder = {…}` with `customer_id`, `source`, `lines[].product_listing_id`, `lines[].unit_price`, `lines[].tax_amount`, `lines[].fees[].name`, `lines[].fees[].amount` all matching the persisted order; uses table layout (`data-testid="items-table"`)
- ☐ `edit_redirects_to_show_when_order_not_pending`
- ☐ `user_without_orders_update_cannot_view_edit` — assertForbidden

### `update` (`PUT /admin/orders/{order}`)
- ☐ `admin_can_update_pending_order`
- ☐ `update_fails_when_order_not_pending` — redirect with error
- ☐ `user_without_orders_update_cannot_post_update` — assertForbidden

### `destroy` (`DELETE /admin/orders/{order}`)
- ☐ `admin_can_hard_delete_pending_order`
  - assertRedirect to `orders.index`
  - assertDatabaseMissing orders, order_lines, order_line_fees, order_events
  - assertDatabaseHas audit_logs: action='deleted', auditable_type='Order', auditable_id=$order->id
- ☐ `destroy_fails_when_order_not_pending`
- ☐ `sales_cannot_destroy_order` — assertForbidden (sales role lacks `orders.delete`)
- ☐ `user_without_orders_delete_cannot_destroy` — assertForbidden

### `recordCashPayment` (`POST /admin/orders/{order}/cash-payment`)
- ☐ `admin_can_record_cash_payment`
  - assertDatabaseHas payments: method=cash, status=paid, amount=286.86
  - assertDatabaseHas orders: payment_status=paid, status=processing
  - assertDatabaseHas order_events: event=payment_received
  - assertDatabaseMissing inventory_movements (still none)
- ☐ `record_cash_payment_fails_when_order_already_paid`
- ☐ `record_cash_payment_fails_when_amount_does_not_match_grand_total`
- ☐ `user_without_orders_recordPayment_cannot_record_payment` — assertForbidden

### `complete` (`POST /admin/orders/{order}/complete`)
- ☐ `admin_can_complete_order`
  - assertDatabaseHas orders: status=complete
  - assertDatabaseHas inventory_movements: type=sale, reference=order.number
  - assertDatabaseHas inventory_serials: status=sold
  - assertDatabaseHas order_events: event=completed
- ☐ `complete_fails_when_order_not_processing`
- ☐ `user_without_orders_complete_cannot_complete` — assertForbidden

### `receipt` (`GET /admin/orders/{order}/receipt`)
- ☐ `receipt_shows_shop_letterhead_when_shop_config_set` — `config(['shop.billing.first_name' => 'ACME'])`; response contains "ACME"
- ☐ `receipt_omits_shop_letterhead_when_shop_config_unset` — `config(['shop.billing.first_name' => null, ...])`; response does NOT contain any shop name, does NOT contain "NPC Sales Pro LLC"

---

## AvaTax shop-config tests — `tests/Unit/AvaTaxServiceTest.php`

- ☐ `it_returns_zeros_when_ship_from_incomplete` — `config(['avatax.ship_from.street' => null])`; calling `calculateTax()` returns all zeros, AvaTax SDK is NOT called (mock the client and assert no invocation)

---

## Test counts (for tracking)

| Section | Tests |
|---------|------:|
| `OrderService::store()` | 29 |
| `OrderService::recordCashPayment()` | 14 |
| `OrderService::complete()` | 11 |
| `OrderService::update()` | 5 |
| `OrderService::delete()` | 8 |
| Integration | 3 |
| **Unit total** | **70** |
| `AvaTaxService` (new shop-config test) | +1 |
| Feature tests | 25 + 2 receipt = **27** |
| **Grand total** | **98** |

Implementation target: **94/94 passing** before module is "done".

---

## Dependencies

**Depends on:**
- `01-enums.md` — assertions use enum cases
- `02-permissions.md` — `givePermissionTo()` slugs
- `03-schema.md` — `assertDatabaseHas/Missing` column names
- `14-events-inventory.md` — every test asserts a row from its truth table

**Depended on by:**
- `04-models.md`, `05-factories.md`, `06-policy.md` — provide test scaffolding
- `07-service.md` — implementation must pass every unit test
- `09-requests.md` — validation messages tested
- `11-controller.md` — implementation must pass every feature test
- `16-audit-log.md` — `it_calls_audit_log_BEFORE_delete` enforces integration

---

## Validation gates

- [ ] Every decision in Layer 1 has at least one test asserting it
- [ ] Every edge case in Layer 1 has at least one test asserting it
- [ ] Every permission has a positive (allowed) + negative (forbidden) test
- [ ] Every event has a test asserting metadata shape + side effects
- [ ] Every CASCADE behavior has a `assertDatabaseMissing` test
- [ ] Every `DomainException` throw point has a test
- [ ] Every atomic transaction has a rollback test (force failure → assertDatabaseMissing)

---

## Cross-check vs Layer 1

| Layer 1 source | Tests asserting it |
|----------------|--------------------|
| `01-enums.md` — 3 OrderStatus cases | `store` (Pending), `recordCashPayment` (→Processing), `complete` (→Complete) |
| `01-enums.md` — 2 PaymentStatus cases | `store` (Unpaid), `recordCashPayment` (→Paid) |
| `01-enums.md` — 3 OrderEvent cases | 3 separate tests per event |
| `02-permissions.md` — 7 permissions | 7 forbidden tests + 7 positive tests |
| `02-permissions.md` — sales lacks orders.delete | `sales_cannot_destroy_order` |
| `03-schema.md` — no subtotal/fees/tax on orders | `it_stores_only_shipping_and_grand_total_on_orders_table` |
| `03-schema.md` — fee_total stored | `it_sets_fee_total_as_amount_plus_tax_amount` |
| `03-schema.md` — CASCADE behaviors | 5 cascade-delete tests under `destroy` |
| `03-schema.md` — no deleted_at | `it_permanently_deletes_order_row` (assertDatabaseMissing, not assertSoftDeleted) |
| `14-events-inventory.md` — atomic transaction rule | 6 rollback tests |
| `14-events-inventory.md` — no movement on store/payment | 4 `assertDatabaseMissing inventory_movements` checks |
| `14-events-inventory.md` — movement only on complete | `it_creates_inventory_movement_with_type_sale` |
| `14-events-inventory.md` — serial allocated at store but status unchanged | 2 separate tests |
| `14-events-inventory.md` — metadata shapes | 3 metadata-shape tests |

Every decision has at least one test. No gaps.
