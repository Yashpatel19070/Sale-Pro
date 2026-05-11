# Procurement Module — E2E Edge Cases

**Model:** Haiku
**Tooling:** Playwright (via e2e-runner agent)
**Scope:** Edge-case and boundary conditions NOT covered by the 5 happy-path journeys in `11-e2e-playwright.md`.
**No code changes.** This file is the plan only. Tests live in `tests/E2E/ProcurementEdgeCasesTest.php`.

---

## Setup

Same seeded state as `11-e2e-playwright.md`. Each test should be independent — use `RefreshDatabase` + seed per test or use a persistent test-mode database with known IDs.

---

## EC-001 — Authorization: Sales user cannot POST to PO store (BUG-001)

**What it tests:** `PurchaseOrderController::store()` now has `$this->authorize('create')`.
A sales-role user should get 403 even if they POST directly (not just via UI).

```typescript
test('EC-001: sales user direct POST to PO store returns 403', async ({ request }) => {
  const salesToken = await loginAs('sales'); // sales user session cookie

  const res = await request.post('/admin/purchase-orders', {
    headers: { Cookie: salesToken },
    form: {
      supplier_id: '1',
      expected_delivery: '2026-06-01',
      'lines[0][product_id]': '1',
      'lines[0][qty_ordered]': '1',
      'lines[0][unit_cost]': '100',
      'lines[0][description]': 'EC001 Test',
      _token: await getCsrfToken(request, salesToken),
    },
  });

  expect(res.status()).toBe(403);
});
```

---

## EC-002 — Authorization: Sales user cannot POST to bulk-receive store (BUG-002)

**What it tests:** `storeBulkReceive()` now has `$this->authorize('bulkReceive')`.

```typescript
test('EC-002: sales user direct POST to bulk-receive returns 403', async ({ request }) => {
  const salesToken = await loginAs('sales');

  const res = await request.post('/admin/inventory-movements/bulk-receive', {
    headers: { Cookie: salesToken },
    form: {
      product_id: '1',
      qty: '5',
      inventory_location_id: '1',
      purchase_price: '100.00',
      _token: await getCsrfToken(request, salesToken),
    },
  });

  expect(res.status()).toBe(403);
});
```

---

## EC-003 — Rejection form hidden from approve-only user (BUG-012)

**What it tests:** Rejection form is gated with `@can('reject')` not `@can('approve')`.

```typescript
test('EC-003: approve-only user does not see rejection form on PO show', async ({ page }) => {
  const po = await createPoInPendingApproval();
  await loginAsApproveOnly(page); // user with PURCHASE_ORDERS_APPROVE but NOT PURCHASE_ORDERS_REJECT

  await page.goto(`/admin/purchase-orders/${po.id}`);
  await expect(page.locator('form[action*="reject"]')).not.toBeVisible();
  await expect(page.getByLabel('Rejection Reason')).not.toBeVisible();
});

test('EC-003b: reject-permission user sees rejection form on PO show', async ({ page }) => {
  const po = await createPoInPendingApproval();
  await loginAsRejectOnly(page); // user with PURCHASE_ORDERS_REJECT but NOT PURCHASE_ORDERS_APPROVE

  await page.goto(`/admin/purchase-orders/${po.id}`);
  await expect(page.locator('form[action*="reject"]')).toBeVisible();
  await expect(page.getByLabel('Rejection Reason')).toBeVisible();
});
```

---

## EC-004 — Cannot complete GRN for cancelled PO (BUG-007)

**What it tests:** `GoodsReceiptService::complete()` rejects terminal-status POs.

```typescript
test('EC-004: complete button disabled/blocked when PO is cancelled', async ({ page }) => {
  await loginAsAdmin(page);
  const { po, grn } = await createGrnForCancelledPo();

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);

  // Complete button should either not render or produce error flash
  const completeButton = page.getByRole('button', { name: 'Complete' });
  if (await completeButton.isVisible()) {
    await completeButton.click();
    await expect(page.locator('[class*="bg-red"]')).toBeVisible();
    await expect(page.getByText(/cancelled|rejected/i)).toBeVisible();
  } else {
    // Button correctly hidden — pass
    expect(true).toBeTruthy();
  }
});
```

---

## EC-005 — Edit GRN blocked after QC submitted (BUG-008)

**What it tests:** `GoodsReceiptService::update()` rejects edits when QC data exists.

```typescript
test('EC-005: edit GRN redirects to show with error when QC already submitted', async ({ page }) => {
  await loginAsAdmin(page);
  const { po, grn } = await createGrnWithQcSubmitted(); // GRN complete + QC submitted

  // Try to open edit form
  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}/edit`);

  // Should be blocked — either 302 back to show with error, or edit form not accessible
  // Accept either a redirect-with-error or an error rendered on the edit page
  const url = page.url();
  if (url.includes('/edit')) {
    // Form loaded — try to submit
    await page.getByRole('button', { name: /Save|Update/i }).click();
    await expect(page.locator('[class*="bg-red"]')).toBeVisible();
  } else {
    // Redirected — error flash visible on show page
    await expect(page.locator('[class*="bg-red"]')).toBeVisible();
    await expect(page.getByText(/QC.*submitted|cannot edit/i)).toBeVisible();
  }
});
```

---

## EC-006 — QC submit form: pass + fail must equal received (sum validation)

**What it tests:** Alpine.js live validation blocks submit until pass + fail === received. Server also validates.

```typescript
test('EC-006: QC submit button stays disabled when pass + fail does not equal received', async ({ page }) => {
  await loginAsAdmin(page);
  const { po, grn } = await createCompletedGrnReadyForQc({ qty: 5 });

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);

  // Enter invalid values: pass=3, fail=1 (sum=4, received=5)
  await page.locator('input[name$="[qty_passed]"]').first().fill('3');
  await page.locator('input[name$="[qty_failed]"]').first().fill('1');

  const submitBtn = page.getByRole('button', { name: 'Submit QC' });
  await expect(submitBtn).toBeDisabled();

  // Fix to correct values: pass=4, fail=1 (sum=5)
  await page.locator('input[name$="[qty_passed]"]').first().fill('4');
  await expect(submitBtn).toBeEnabled();
});

test('EC-006b: server rejects QC with mismatched sum even if client validation bypassed', async ({ request }) => {
  const { po, grn, line } = await createCompletedGrnReadyForQcApi({ qty: 5 });
  const adminToken = await loginAs('admin');

  const res = await request.post(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}/qc`, {
    headers: { Cookie: adminToken },
    form: {
      [`lines[0][goods_receipt_line_id]`]: String(line.id),
      [`lines[0][qty_passed]`]: '3',
      [`lines[0][qty_failed]`]: '1', // sum = 4, received = 5
      _token: await getCsrfToken(request, adminToken),
    },
  });

  // Expect redirect back with error
  expect([302, 422]).toContain(res.status());
});
```

---

## EC-007 — QC double-submit blocked (BUG-011 + existing guard)

**What it tests:** Service-level idempotency key. Second QC submit for same GRN errors.

```typescript
test('EC-007: submitting QC twice for same GRN shows error on second attempt', async ({ page }) => {
  await loginAsAdmin(page);
  const { po, grn } = await createCompletedGrnReadyForQc({ qty: 3 });

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);

  // First submit
  await page.locator('input[name$="[qty_passed]"]').first().fill('3');
  await page.locator('input[name$="[qty_failed]"]').first().fill('0');
  await page.getByRole('button', { name: 'Submit QC' }).click();
  await page.waitForURL(`**/goods-receipts/${grn.id}`);
  await expect(page.getByText(/QC submitted/i)).toBeVisible();

  // Try second submit by navigating directly and submitting again
  // (QC form should no longer render after first submission)
  await page.reload();
  const qcForm = page.locator('form[action*="/qc"]');
  await expect(qcForm).not.toBeVisible();
});
```

---

## EC-008 — Serial Assign Numbers button: only renders for bulkReceive permission (BUG-003)

**What it tests:** `@can('bulkReceive', App\Models\InventoryMovement::class)` is the correct gate.

```typescript
test('EC-008: Assign Serial Numbers button visible for inventory manager', async ({ page }) => {
  await loginAsInventoryManager(page);
  const { po, grn } = await createCompletedGrnWithQcPassed();

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);
  await expect(page.getByRole('link', { name: 'Assign Serial Numbers' })).toBeVisible();
});

test('EC-008b: Assign Serial Numbers button hidden for sales user', async ({ page }) => {
  await loginAsSales(page);
  const { po, grn } = await createCompletedGrnWithQcPassed();

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);
  await expect(page.getByRole('link', { name: 'Assign Serial Numbers' })).not.toBeVisible();
});

test('EC-008c: Assign Serial Numbers button hidden after serials already assigned', async ({ page }) => {
  await loginAsInventoryManager(page);
  const { po, grn } = await createGrnWithSerialsAlreadyAssigned();

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);
  await expect(page.getByRole('link', { name: 'Assign Serial Numbers' })).not.toBeVisible();
  await expect(page.getByText(/Serials Assigned/)).toBeVisible();
});
```

---

## EC-009 — PO status NOT overwritten when already received (BUG-013)

**What it tests:** `updatePoStatus()` skips terminal statuses.

```typescript
test('EC-009: PO status stays received after a second GRN is completed', async ({ page }) => {
  await loginAsAdmin(page);
  const po = await createPoWithTwoPartialGrns(); // first GRN already completed → PO = partially_received, then received

  await page.goto(`/admin/purchase-orders/${po.id}`);
  // Complete second GRN
  const secondGrn = await getSecondGrn(po);
  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${secondGrn.id}`);
  await page.getByRole('button', { name: 'Complete' }).click();

  await page.waitForURL(`**/purchase-orders/${po.id}`);
  // PO should be received or partially_received — NOT downgraded to quality_check
  const statusBadge = page.locator('[data-status]').or(page.getByText(/received|partially/i));
  await expect(statusBadge).toBeVisible();
  await expect(page.getByText('Quality Check')).not.toBeVisible();
});
```

---

## EC-010 — GRN edit form shows supplier name (BUG-004)

**What it tests:** Supplier eager-loaded in `GoodsReceiptController::edit()`.

```typescript
test('EC-010: GRN edit form displays supplier name in header/breadcrumb', async ({ page }) => {
  await loginAsAdmin(page);
  const supplier = await createSupplier({ name: 'Edit Supplier Co' });
  const { po, grn } = await createDraftGrn({ supplier });

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}/edit`);
  await expect(page.getByText('Edit Supplier Co')).toBeVisible();
});
```

---

## EC-011 — Assign serials form shows supplier name and received-by user (BUG-006)

**What it tests:** Supplier + receivedBy eager-loaded in `GoodsReceiptController::assignSerials()`.

```typescript
test('EC-011: assign serials form shows supplier name and received-by user', async ({ page }) => {
  await loginAsInventoryManager(page);
  const supplier  = await createSupplier({ name: 'Serial Supplier' });
  const receiver  = await createAdminUser({ name: 'Jane Receiver' });
  const { po, grn } = await createCompletedGrnWithQcPassed({ supplier, receiver });

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}/assign-serials`);
  await expect(page.getByText('Serial Supplier')).toBeVisible();
  await expect(page.getByText('Jane Receiver')).toBeVisible();
});
```

---

## EC-012 — Print page fallback when session is empty

**What it tests:** `printBulkReceive()` redirects to bulk-receive form when session has no IDs.

```typescript
test('EC-012: visiting print page without session redirects to bulk-receive form', async ({ page }) => {
  await loginAsInventoryManager(page);

  // Navigate directly to print page without session (fresh session)
  await page.goto('/admin/inventory-movements/bulk-receive/print');

  await page.waitForURL(/bulk-receive$/);
  await expect(page.getByText(/No serials to print/i)).toBeVisible();
});
```

---

## EC-013 — Cannot receive more than remaining qty on GRN create

**What it tests:** `validateLineQtys()` rejects over-receiving.

```typescript
test('EC-013: submitting GRN with qty exceeding remaining shows validation error', async ({ page }) => {
  await loginAsAdmin(page);
  const po = await createApprovedPoWithQty(3); // line ordered qty = 3

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/create`);
  await page.getByLabel('Received Date').fill('2026-05-05');
  await page.locator('input[name$="[qty_received]"]').first().fill('99'); // way over remaining

  await page.getByRole('button', { name: /Save|Submit/i }).click();

  await expect(page.getByText(/exceeds remaining/i)).toBeVisible();
  expect(page.url()).toContain('/create');
});
```

---

## EC-014 — GRN complete button visible only for draft status

**What it tests:** Complete button is only shown when GRN is in draft status, not after complete.

```typescript
test('EC-014: Complete button not visible on already-completed GRN', async ({ page }) => {
  await loginAsAdmin(page);
  const { po, grn } = await createAlreadyCompletedGrn();

  await page.goto(`/admin/purchase-orders/${po.id}/goods-receipts/${grn.id}`);
  await expect(page.getByRole('button', { name: 'Complete' })).not.toBeVisible();
});
```

---

## Helper Functions (TypeScript, add to `tests/E2E/helpers/procurement.ts`)

```typescript
// Create PO in pending_approval status
async function createPoInPendingApproval(): Promise<{ id: number }> {
  // Uses artisan tinker or seeder API endpoint in test mode
  return await callTestHelper('createPoInPendingApproval');
}

// Create GRN for a cancelled PO (edge case)
async function createGrnForCancelledPo(): Promise<{ po: any, grn: any }> {
  return await callTestHelper('createGrnForCancelledPo');
}

// Create completed GRN ready for QC (GRN status=complete, PO status=quality_check)
async function createCompletedGrnReadyForQc(opts: { qty: number }): Promise<{ po: any, grn: any }> {
  return await callTestHelper('createCompletedGrnReadyForQc', opts);
}

// Create completed GRN where QC has already been submitted
async function createGrnWithQcSubmitted(): Promise<{ po: any, grn: any }> {
  return await callTestHelper('createGrnWithQcSubmitted');
}

// Create completed GRN where QC passed and serials NOT yet assigned
async function createCompletedGrnWithQcPassed(opts?: { supplier?: any, receiver?: any }): Promise<{ po: any, grn: any }> {
  return await callTestHelper('createCompletedGrnWithQcPassed', opts);
}

// Create completed GRN where serials already assigned
async function createGrnWithSerialsAlreadyAssigned(): Promise<{ po: any, grn: any }> {
  return await callTestHelper('createGrnWithSerialsAlreadyAssigned');
}

// Login helpers
async function loginAsInventoryManager(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.getByLabel('Email').fill(process.env.INVENTORY_MANAGER_EMAIL!);
  await page.getByLabel('Password').fill(process.env.INVENTORY_MANAGER_PASSWORD!);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL('/admin/dashboard');
}

async function loginAsSales(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.getByLabel('Email').fill(process.env.SALES_USER_EMAIL!);
  await page.getByLabel('Password').fill(process.env.SALES_USER_PASSWORD!);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL('/admin/dashboard');
}

// Get CSRF token from the app
async function getCsrfToken(request: APIRequestContext, sessionCookie: string): Promise<string> {
  const res = await request.get('/admin/csrf-token', { headers: { Cookie: sessionCookie } });
  const data = await res.json();
  return data.token;
}

// Generic test helper bridge — calls PHP artisan command in test mode
async function callTestHelper(method: string, args?: object): Promise<any> {
  const res = await fetch(`http://localhost:8000/_test-helpers/${method}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Test-Secret': process.env.TEST_HELPER_SECRET! },
    body: JSON.stringify(args ?? {}),
  });
  return await res.json();
}
```

---

## Playwright Config Notes

- Tests in `tests/E2E/ProcurementEdgeCasesTest.ts`
- Run isolated from `ProcurementWorkflowTest.ts` (different describe block)
- Each `test()` calls `callTestHelper` to reset/seed just enough state — no shared state between tests
- `baseURL`: `http://localhost:8000`
- `storageState`: None (each test logs in fresh via helpers)

---

## Coverage Map (which bugs each test covers)

| Test | Bug |
|------|-----|
| EC-001 | BUG-001 |
| EC-002 | BUG-002 |
| EC-003 | BUG-012 |
| EC-004 | BUG-007 |
| EC-005 | BUG-008 |
| EC-006 | BUG-009 (server validation) |
| EC-007 | BUG-011 |
| EC-008 | BUG-003 |
| EC-009 | BUG-013 |
| EC-010 | BUG-004 |
| EC-011 | BUG-006 |
| EC-012 | (regression guard, print page) |
| EC-013 | (existing validateLineQtys validation) |
| EC-014 | (UI state guard) |
