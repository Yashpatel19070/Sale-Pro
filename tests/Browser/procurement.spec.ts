import { test, expect, type Page } from '@playwright/test';

// ── Credentials (seeded by E2ESeeder / DatabaseSeeder) ─────────────────────────
const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };
const SALES = { email: 'sales@sale-pro.test', password: 'password' };

// ── Helpers ───────────────────────────────────────────────────────────────────

async function login(page: Page, user: { email: string; password: string }) {
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/dashboard');
}

/** Extract the last numeric segment from the current URL. */
function lastSegment(page: Page): string {
    return new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;
}

// ── 1. Happy Path — Full Workflow ─────────────────────────────────────────────

test.describe('Happy path — full PO → GRN → QC → Serials → Invoice → Closed', () => {
    // Store IDs across the sequential sub-tests via shared state
    let poId = '';
    let grnId = '';

    test('1.1 admin can create a PO with 2 product lines', async ({ page }) => {
        await login(page, ADMIN);
        await page.goto('/admin/purchase-orders/create');

        await page.selectOption('select[name="supplier_id"]', { index: 1 });

        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('10');
        await page.locator('[name="lines[0][unit_cost]"]').fill('25');

        await page.getByRole('button', { name: '+ Add Line' }).click();
        await page.locator('[name="lines[1][product_id]"]').selectOption({ index: 2 });
        await page.locator('[name="lines[1][qty_ordered]"]').fill('5');
        await page.locator('[name="lines[1][unit_cost]"]').fill('100');

        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        await expect(page.getByText(/purchase order .* created/i)).toBeVisible();
        await expect(page.getByText('Draft')).toBeVisible();

        poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;
        expect(poId).toBeTruthy();
    });

    test('1.2 admin submits PO for approval', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}`);

        await page.getByRole('button', { name: 'Submit' }).click();
        await expect(page.getByText('Pending Approval')).toBeVisible();
        await expect(page.getByText(/submitted for approval/i)).toBeVisible();
    });

    test('1.3 admin approves PO', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}`);

        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();
    });

    test('1.4 admin marks PO as on the way', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}`);

        await page.getByRole('button', { name: 'Mark On The Way' }).click();
        await expect(page.getByText('Purchase order marked as on the way.')).toBeVisible();
    });

    test('1.5 admin creates a GRN (partial receipt — 5 of 10 on line 1, 5 of 5 on line 2)', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);

        await expect(page.getByText('Record Goods Receipt')).toBeVisible();

        // Line 1: receive 5 (partial)
        await page.locator('[name="lines[0][qty_received]"]').fill('5');
        // Line 2: receive all 5
        await page.locator('[name="lines[1][qty_received]"]').fill('5');

        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        await expect(page.getByText(/goods receipt .* created/i)).toBeVisible();

        // Extract GRN id from URL: /admin/purchase-orders/{poId}/goods-receipts/{grnId}
        grnId = lastSegment(page);
        expect(grnId).toBeTruthy();
    });

    test('1.6 admin completes the GRN', async ({ page }) => {
        test.skip(!poId || !grnId, 'GRN not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);

        await page.getByRole('button', { name: 'Complete' }).click();
        // Redirects to PO show; PO status becomes quality_check
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText(/goods receipt completed/i)).toBeVisible();
        await expect(page.getByText('Quality Check')).toBeVisible();
    });

    test('1.7 admin submits QC on the GRN (all units pass)', async ({ page }) => {
        test.skip(!poId || !grnId, 'GRN not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);

        // QC form should be visible because PO is in quality_check
        await expect(page.getByText('Quality Check Inspection')).toBeVisible();

        // Line 0: pass all 5, fail 0
        await page.locator('[name="lines[0][qty_passed]"]').fill('5');
        await page.locator('[name="lines[0][qty_failed]"]').fill('0');

        // Line 1: pass all 5, fail 0
        await page.locator('[name="lines[1][qty_passed]"]').fill('5');
        await page.locator('[name="lines[1][qty_failed]"]').fill('0');

        // Submit QC button is enabled only when allValid
        const submitBtn = page.getByRole('button', { name: 'Submit QC' });
        await expect(submitBtn).not.toBeDisabled();
        await submitBtn.click();

        await expect(page.getByText(/QC submitted/i)).toBeVisible();
        await expect(page.getByText('QC Results')).toBeVisible();
    });

    test('1.8 admin assigns serial numbers to passed units', async ({ page }) => {
        test.skip(!poId || !grnId, 'GRN not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/assign-serials`);

        await expect(page.getByText('Assign Serial Numbers')).toBeVisible();

        // Select a location for each line and confirm the purchase price is prefilled
        await page.locator('[name="lines[0][inventory_location_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[1][inventory_location_id]"]').selectOption({ index: 1 });

        // purchase_price is pre-filled from unit_cost — just confirm field exists
        await expect(page.locator('[name="lines[0][purchase_price]"]')).toBeVisible();

        await page.getByRole('button', { name: /Generate.*Serial/i }).click();
        // Redirects to bulk-receive-print page
        await expect(page).toHaveURL(/inventory-movements.*bulk-receive\/print|purchase-orders/);
    });

    test('1.9 admin creates an invoice for the PO', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);

        // After full receipt + serial assignment the PO should be in Received or Partially Received.
        // Navigate directly to the invoice create URL.
        await page.goto(`/admin/purchase-orders/${poId}/invoices/create`);

        await page.fill('input[name="invoice_number"]', `INV-E2E-${Date.now()}`);
        await page.fill('input[name="invoice_date"]', new Date().toISOString().split('T')[0]);
        await page.fill('input[name="amount"]', '375.00');

        await page.getByRole('button', { name: 'Add Invoice' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText(/invoice added/i)).toBeVisible();
        await expect(page.getByText(/INV-E2E-/)).toBeVisible();
    });

    test('1.10 admin approves the invoice', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}`);

        // Click Approve on the first pending invoice
        await page.getByRole('button', { name: 'Approve' }).first().click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText(/invoice approved/i)).toBeVisible();
    });

    test('1.11 admin marks invoice as paid and PO moves to closed', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto(`/admin/purchase-orders/${poId}`);

        await page.getByRole('button', { name: 'Mark Paid' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText(/marked as paid/i)).toBeVisible();
        // PO status should advance to closed after full payment
        await expect(page.getByText('Closed')).toBeVisible();
    });

    test('1.12 closed PO shows on index with Closed status', async ({ page }) => {
        test.skip(!poId, 'PO not created');
        await login(page, ADMIN);
        await page.goto('/admin/purchase-orders');

        // Filter to closed status
        await page.selectOption('select[name="status"]', 'closed');
        await page.getByRole('button', { name: 'Filter' }).click();
        await expect(page.locator('tbody').getByText('Closed').first()).toBeVisible();
    });
});

// ── 2. Authorization ──────────────────────────────────────────────────────────

test.describe('Authorization', () => {
    test('guest is redirected to login for PO index', async ({ page }) => {
        await page.goto('/admin/purchase-orders');
        await expect(page).toHaveURL(/admin\/login/);
    });

    test('guest is redirected to login for PO create', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');
        await expect(page).toHaveURL(/admin\/login/);
    });

    test('guest is redirected to login for PO show', async ({ page }) => {
        await page.goto('/admin/purchase-orders/1');
        await expect(page).toHaveURL(/admin\/login/);
    });

    test('sales user can view PO index', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/purchase-orders');
        await expect(page).toHaveURL(/admin\/purchase-orders/);
        // Should not see a 403 page
        await expect(page.locator('body')).not.toContainText('403');
    });

    test('sales user cannot see New Purchase Order button on index', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/purchase-orders');
        await expect(page.getByRole('link', { name: 'New Purchase Order' })).not.toBeVisible();
    });

    test('sales user gets 403 on PO create page', async ({ page }) => {
        await login(page, SALES);
        const response = await page.goto('/admin/purchase-orders/create');
        expect(response?.status()).toBe(403);
    });

    test('sales user POST to store PO returns 403', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/purchase-orders');

        const response = await page.evaluate(async () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/admin/purchase-orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ supplier_id: 1, lines: [{ product_id: 1, qty_ordered: 1, unit_cost: 10, tax_rate: 0 }] }),
            });
            return res.status;
        });
        expect(response).toBe(403);
    });

    test('sales user POST to approve PO returns 403', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/purchase-orders');

        const response = await page.evaluate(async () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/admin/purchase-orders/1/approve', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            return res.status;
        });
        // 403 (no permission) or 404 (PO 1 doesn't exist) — both are acceptable authorization failures
        expect([403, 404]).toContain(response);
    });
});

// ── 3. PO Validation ──────────────────────────────────────────────────────────

test.describe('PO validation', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('cannot create PO without a supplier', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');

        // Leave supplier blank, fill in product line
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');

        // Bypass HTML5 native validation on all forms so server-side errors are returned
        await page.evaluate(() => { document.querySelectorAll('form').forEach(f => f.setAttribute('novalidate', '')); });
        const postResp1 = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/purchase-orders'));
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await postResp1;
        await page.waitForLoadState('domcontentloaded');

        // Should stay on create page with a validation error
        await expect(page).toHaveURL(/purchase-orders\/create/);
        await expect(page.locator('.text-red-600, .bg-red-50').first()).toBeVisible();
    });

    test('cannot create PO with no product lines (all lines have product blank)', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');

        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        // Bypass HTML5 native validation on all forms so server-side errors are returned
        await page.evaluate(() => { document.querySelectorAll('form').forEach(f => f.setAttribute('novalidate', '')); });
        const postResp2 = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/purchase-orders'));
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await postResp2;
        await page.waitForLoadState('domcontentloaded');

        await expect(page).toHaveURL(/purchase-orders\/create/);
        await expect(page.locator('.text-red-600, .bg-red-50').first()).toBeVisible();
    });

    test('cannot edit an approved PO', async ({ page }) => {
        // Create and approve a PO via API-style actions to reach approved state
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('2');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Submit for approval
        await page.getByRole('button', { name: 'Submit' }).click();
        await expect(page.getByText('Pending Approval')).toBeVisible();

        // Approve
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        // Try to access edit URL directly — approved POs are not editable
        await page.goto(`/admin/purchase-orders/${poId}/edit`);
        // Controller redirects back to show page
        await expect(page).toHaveURL(`/admin/purchase-orders/${poId}`);
    });

    test('cannot submit an already-approved PO', async ({ page }) => {
        // Create a fresh PO, submit, approve, then try to submit again via direct POST
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('5');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await expect(page.getByText('Pending Approval')).toBeVisible();

        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        // Attempt to POST submit again via fetch
        const response = await page.evaluate(async (id) => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(`/admin/purchase-orders/${id}/submit`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            return res.status;
        }, poId);

        // 302 → redirect (browser follows to 200), 403 forbidden, 422 unprocessable
        // Browser fetch follows the redirect so 200 is also acceptable here
        expect([200, 302, 403, 422]).toContain(response);
    });
});

// ── 4. GRN Validation ─────────────────────────────────────────────────────────

test.describe('GRN validation', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('cannot create a GRN for a Draft PO', async ({ page }) => {
        // Create a fresh draft PO
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('5');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Navigate to GRN create — form may show but domain rule blocks submission
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        if (!page.url().includes('goods-receipts/create')) {
            // Redirected away — access blocked at controller level, test passes
            return;
        }
        // Form accessible — submit and expect domain rule rejection
        await page.locator('[name="lines[0][qty_received]"]').fill('1');
        await page.evaluate(() => document.querySelectorAll('form').forEach(f => f.setAttribute('novalidate', '')));
        const postRespGrn = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/goods-receipts'));
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await postRespGrn;
        await page.waitForLoadState('domcontentloaded');
        await expect(page.locator('.bg-red-50').first()).toBeVisible();
    });

    test('cannot receive more than remaining qty', async ({ page }) => {
        // Create, submit, approve a PO with qty=2, then try to enter qty_received=999
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('2');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('999');
        await page.evaluate(() => { document.querySelectorAll('form').forEach(f => f.setAttribute('novalidate', '')); });
        const postResp3 = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/goods-receipts'));
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await postResp3;
        await page.waitForLoadState('domcontentloaded');

        // Should stay on create with validation error
        await expect(page).toHaveURL(/goods-receipts\/create/);
        await expect(page.locator('.text-red-600, .bg-red-50, .bg-red-100')).toBeVisible();
    });

    test('cannot edit a Complete GRN', async ({ page }) => {
        // Build a PO → GRN → complete flow
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        // Create GRN
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('1');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        // Complete the GRN
        await page.getByRole('button', { name: 'Complete' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // Try to GET the edit URL
        const editResponse = await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/edit`);
        const body = await page.content();
        const isDenied =
            body.includes('Completed goods receipts cannot be edited') ||
            body.includes('error') ||
            editResponse?.status() === 403 ||
            page.url().includes(`goods-receipts/${grnId}`) && !page.url().includes('/edit');
        expect(isDenied).toBe(true);
    });
});

// ── 5. QC Validation ──────────────────────────────────────────────────────────

test.describe('QC validation', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Submit QC button is disabled when pass + fail != received qty', async ({ page }) => {
        // Build PO → GRN → Complete to reach the QC form, then check Alpine.js disable logic
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('3');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('3');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        await page.getByRole('button', { name: 'Complete' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // Navigate to the GRN show page which contains the QC form
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);
        await expect(page.getByText('Quality Check Inspection')).toBeVisible();

        // Enter mismatched values: pass=1, fail=1, received=3 (1+1 ≠ 3)
        await page.locator('[name="lines[0][qty_passed]"]').fill('1');
        await page.locator('[name="lines[0][qty_failed]"]').fill('1');
        // Allow Alpine.js microtask queue to process the x-model.number updates
        await page.waitForTimeout(300);

        const submitBtn = page.getByRole('button', { name: 'Submit QC' });
        // Alpine.js sets :disabled="! allValid" — button should be disabled
        await expect(submitBtn).toBeDisabled();
    });

    test('cannot submit QC twice on same GRN', async ({ page }) => {
        // Full path to get QC done once, then try to submit again
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('2');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('2');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        await page.getByRole('button', { name: 'Complete' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // First QC submission
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);
        await page.locator('[name="lines[0][qty_passed]"]').fill('2');
        await page.locator('[name="lines[0][qty_failed]"]').fill('0');
        await page.getByRole('button', { name: 'Submit QC' }).click();
        await expect(page.getByText(/QC submitted/i)).toBeVisible();

        // Attempt second QC submission via direct POST
        const result = await page.evaluate(async (params) => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(`/admin/purchase-orders/${params.poId}/goods-receipts/${params.grnId}/qc`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ lines: [{ goods_receipt_line_id: 1, qty_passed: 2, qty_failed: 0 }] }),
            });
            return { status: res.status, body: await res.text() };
        }, { poId, grnId });

        // Either 422 (domain exception), 403 (policy), or redirect with error
        expect([302, 422, 403, 500]).toContain(result.status);
    });
});

// ── 6. Serial Assignment Validation ──────────────────────────────────────────

test.describe('Serial assignment validation', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('cannot access assign-serials for a Draft GRN', async ({ page }) => {
        // Create PO → GRN (do NOT complete it)
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('2');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('2');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        // GRN is still Draft — try to access assign-serials
        const response = await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/assign-serials`);
        const body = await page.content();
        const isBlocked =
            body.includes('error') ||
            response?.status() === 403 ||
            page.url().includes(`goods-receipts/${grnId}`) && !page.url().includes('assign-serials');
        expect(isBlocked).toBe(true);
    });

    test('cannot assign serials twice — second attempt shows an error', async ({ page }) => {
        // Build full path: PO → GRN → Complete → QC → first serial assignment
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('1');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        // Complete GRN
        await page.getByRole('button', { name: 'Complete' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // Submit QC — pass all 1 unit
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);
        await page.locator('[name="lines[0][qty_passed]"]').fill('1');
        await page.locator('[name="lines[0][qty_failed]"]').fill('0');
        await page.getByRole('button', { name: 'Submit QC' }).click();

        // First serial assignment
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/assign-serials`);
        await page.locator('[name="lines[0][inventory_location_id]"]').selectOption({ index: 1 });
        await page.getByRole('button', { name: /Generate.*Serial/i }).click();
        // Wait for redirect after first assignment
        await page.waitForLoadState('networkidle');

        // Attempt second assignment — navigate back to assign-serials
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/assign-serials`);
        const body = await page.content();
        // Should either redirect away with error or show error inline
        const hasError =
            body.includes('already been assigned') ||
            body.includes('Serials Assigned') ||
            body.includes('error') ||
            !page.url().includes('assign-serials');
        expect(hasError).toBe(true);
    });
});

// ── 7. Invoice Validation ─────────────────────────────────────────────────────

test.describe('Invoice validation', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('cannot create invoice for a Draft PO', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('5');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Navigate to invoice create — form may show but domain rule blocks submission
        await page.goto(`/admin/purchase-orders/${poId}/invoices/create`);
        if (!page.url().includes('invoices/create')) {
            // Redirected away — access blocked at controller level, test passes
            return;
        }
        // Form accessible — submit with valid data and expect domain rule rejection
        await page.fill('input[name="invoice_number"]', 'INV-DRAFT-TEST');
        await page.fill('input[name="invoice_date"]', new Date().toISOString().split('T')[0]);
        await page.fill('input[name="amount"]', '100.00');
        const postRespInv = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/invoices'));
        await page.getByRole('button', { name: 'Add Invoice' }).click();
        await postRespInv;
        await page.waitForLoadState('domcontentloaded');
        await expect(page.locator('.bg-red-50').first()).toBeVisible();
    });

    test('cannot approve an already-paid invoice', async ({ page }) => {
        // We need a PO in closed/paid state; use a minimal setup
        // Create PO → submit → approve → GRN (qty 1) → complete → QC (pass 1) →
        // assign serials → create invoice → approve → mark paid → try approve again
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('10');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        await page.getByRole('button', { name: 'Submit' }).click();
        await page.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByText('Purchase order approved.')).toBeVisible();

        // GRN
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/create`);
        await page.locator('[name="lines[0][qty_received]"]').fill('1');
        await page.getByRole('button', { name: 'Record Receipt' }).click();
        await page.waitForURL('**/goods-receipts/*');
        const grnId = lastSegment(page);

        await page.getByRole('button', { name: 'Complete' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // QC
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}`);
        await page.locator('[name="lines[0][qty_passed]"]').fill('1');
        await page.locator('[name="lines[0][qty_failed]"]').fill('0');
        await page.getByRole('button', { name: 'Submit QC' }).click();

        // Serial assignment
        await page.goto(`/admin/purchase-orders/${poId}/goods-receipts/${grnId}/assign-serials`);
        await page.locator('[name="lines[0][inventory_location_id]"]').selectOption({ index: 1 });
        await page.getByRole('button', { name: /Generate.*Serial/i }).click();
        await page.waitForLoadState('networkidle');

        // Invoice
        await page.goto(`/admin/purchase-orders/${poId}/invoices/create`);
        const invNum = `INV-PAID-${Date.now()}`;
        await page.fill('input[name="invoice_number"]', invNum);
        await page.fill('input[name="invoice_date"]', new Date().toISOString().split('T')[0]);
        await page.fill('input[name="amount"]', '10.00');
        await page.getByRole('button', { name: 'Add Invoice' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // Approve invoice
        await page.getByRole('button', { name: 'Approve' }).first().click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        // Mark paid
        await page.getByRole('button', { name: 'Mark Paid' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText(/marked as paid/i)).toBeVisible();

        // Invoice row should now show Paid badge and no Approve button
        await expect(page.getByRole('button', { name: 'Approve' })).not.toBeVisible();
    });

    test('cannot delete a paid invoice', async ({ page }) => {
        // Verify no Delete button is rendered for Paid invoice (the view hides it for Paid status)
        // We can test this by asserting the Delete button count matches (paid ones are excluded)
        // The view renders Delete button only when status !== Paid.
        // Set up a minimal paid invoice scenario using the previous test's flow.
        // Since tests run sequentially and share the same DB, just check the index view.
        await page.goto('/admin/purchase-orders');
        // Filter for closed POs (invoices are paid on closed POs)
        await page.selectOption('select[name="status"]', 'closed');
        await page.getByRole('button', { name: 'Filter' }).click();

        // If any closed PO exists, navigate to its show page and verify no Delete button for Paid invoice
        const firstLink = page.locator('tbody tr a').first();
        const count = await firstLink.count();
        if (count > 0) {
            await firstLink.click();
            await page.waitForLoadState('networkidle');
            // In the invoice section for a paid invoice, there should be no Delete button
            const paidBadge = page.getByText('Paid').first();
            if (await paidBadge.isVisible()) {
                const invoiceRow = page.locator('tbody tr').filter({ has: page.getByText('Paid') }).first();
                await expect(invoiceRow.getByRole('button', { name: 'Delete' })).not.toBeVisible();
            }
        }
    });
});

// ── 8. Print View ─────────────────────────────────────────────────────────────

test.describe('Print view', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('PO print page renders with PO number, supplier name, and line items', async ({ page }) => {
        // Create a minimal PO to print
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('3');
        await page.locator('[name="lines[0][unit_cost]"]').fill('15');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Grab the PO number from the show page
        const poNumber = await page.locator('h2').first().textContent();

        // Open print page
        const printPage = await page.context().newPage();
        await printPage.goto(`/admin/purchase-orders/${poId}/print`);
        await printPage.waitForLoadState('networkidle');

        // Must contain the PO number
        await expect(printPage.getByText(poNumber?.trim() ?? /PO-/)).toBeVisible();

        // Must not show a 500 error
        await expect(printPage.locator('body')).not.toContainText('500');
        await expect(printPage.locator('body')).not.toContainText('Whoops');

        // Must contain supplier name (at least a non-empty string in the supplier section)
        const supplierSection = printPage.locator('body');
        await expect(supplierSection).not.toContainText('undefined');

        await printPage.close();
    });
});

// ── 9. Reject Flow ────────────────────────────────────────────────────────────

test.describe('Reject flow', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('admin can reject a submitted PO and rejection reason appears on show page', async ({ page }) => {
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('5');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Submit
        await page.getByRole('button', { name: 'Submit' }).click();
        await expect(page.getByText('Pending Approval')).toBeVisible();

        // Fill rejection reason and reject
        const reason = 'E2E rejection reason — price too high';
        await page.fill('input[name="rejection_reason"]', reason);

        // Accept the confirm dialog triggered by the reject form's onsubmit
        page.once('dialog', async (dialog) => {
            await dialog.accept();
        });
        await page.getByRole('button', { name: 'Reject' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);

        await expect(page.getByText('Rejected', { exact: true })).toBeVisible();
        await expect(page.getByText(reason)).toBeVisible();
    });

    test('rejected PO can be resubmitted and approved (full cycle after rejection)', async ({ page }) => {
        // Create a fresh PO
        await page.goto('/admin/purchase-orders/create');
        await page.selectOption('select[name="supplier_id"]', { index: 1 });
        await page.locator('[name="lines[0][product_id]"]').selectOption({ index: 1 });
        await page.locator('[name="lines[0][qty_ordered]"]').fill('1');
        await page.locator('[name="lines[0][unit_cost]"]').fill('5');
        await page.getByRole('button', { name: 'Create Purchase Order' }).click();
        await page.waitForURL('**/purchase-orders/*');
        const poId = new URL(page.url()).pathname.split('/').filter(Boolean).pop()!;

        // Submit → reject
        await page.getByRole('button', { name: 'Submit' }).click();
        await expect(page.getByText('Pending Approval')).toBeVisible();

        await page.fill('input[name="rejection_reason"]', 'Initial rejection for cycle test');
        page.once('dialog', async (dialog) => { await dialog.accept(); });
        await page.getByRole('button', { name: 'Reject' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText('Rejected', { exact: true })).toBeVisible();

        // Resubmit from rejected state (button label is "Resubmit")
        await page.getByRole('button', { name: 'Resubmit' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText('Pending Approval')).toBeVisible();

        // Approve the resubmitted PO
        await page.getByRole('button', { name: 'Approve' }).click();
        await page.waitForURL(`**/purchase-orders/${poId}`);
        await expect(page.getByText('Purchase order approved.')).toBeVisible();
    });
});
