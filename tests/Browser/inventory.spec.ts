import { test, expect, type Page } from '@playwright/test';

// ── Credentials seeded by E2ESeeder ──────────────────────────────────────────
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

// ── Auth ──────────────────────────────────────────────────────────────────────

test.describe('Auth', () => {
    test('admin can log in and reach dashboard', async ({ page }) => {
        await login(page, ADMIN);
        await expect(page).toHaveURL(/admin\/dashboard/);
    });

    test('sales can log in and reach dashboard', async ({ page }) => {
        await login(page, SALES);
        await expect(page).toHaveURL(/admin\/dashboard/);
    });

    test('wrong password shows validation error', async ({ page }) => {
        await page.goto('/admin/login');
        await page.fill('input[name="email"]', ADMIN.email);
        await page.fill('input[name="password"]', 'wrong');
        await page.click('button[type="submit"]');
        await expect(page.getByText(/credentials/i)).toBeVisible();
    });
});

// ── Stock Dashboard ───────────────────────────────────────────────────────────

test.describe('Stock Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('shows stock nav link', async ({ page }) => {
        await expect(page.getByRole('link', { name: 'Stock' }).first()).toBeVisible();
    });

    test('dashboard shows WIDGET-001 with qty 3', async ({ page }) => {
        await page.goto('/admin/inventory');
        await expect(page.getByText('WIDGET-001')).toBeVisible();
        await expect(page.getByText('Widget Alpha')).toBeVisible();

        // Find the row for WIDGET-001 and check qty
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await expect(row.getByText('3')).toBeVisible();
    });

    test('dashboard shows WIDGET-002 with qty 1', async ({ page }) => {
        await page.goto('/admin/inventory');
        const row = page.locator('tr', { has: page.getByText('WIDGET-002') });
        await expect(row.getByText('1')).toBeVisible();
    });

    test('sold serial is excluded from dashboard count', async ({ page }) => {
        await page.goto('/admin/inventory');
        // WIDGET-001 has 3 in_stock + 1 sold — dashboard must show 3, not 4
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await expect(row.getByText('4')).not.toBeVisible();
    });

    test('sales role can view stock dashboard', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/inventory');
        await expect(page).toHaveURL(/admin\/inventory/);
        await expect(page.getByText('WIDGET-001')).toBeVisible();
    });
});

// ── Stock by SKU ──────────────────────────────────────────────────────────────

test.describe('Stock by SKU', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('clicking View on WIDGET-001 drills into SKU page', async ({ page }) => {
        await page.goto('/admin/inventory');
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await row.getByRole('link', { name: 'View' }).click();
        await expect(page).toHaveURL(/inventory\/\d+/);
        await expect(page.getByText('WIDGET-001')).toBeVisible();
    });

    test('SKU page shows L1 with 2 units and L2 with 1 unit', async ({ page }) => {
        await page.goto('/admin/inventory');
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await row.getByRole('link', { name: 'View' }).click();

        const l1Row = page.locator('tr', { has: page.getByText('L1') });
        await expect(l1Row.getByText('2')).toBeVisible();

        const l2Row = page.locator('tr', { has: page.getByText('L2') });
        await expect(l2Row.getByText('1')).toBeVisible();
    });

    test('total on hand card shows 3', async ({ page }) => {
        await page.goto('/admin/inventory');
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await row.getByRole('link', { name: 'View' }).click();
        await expect(page.getByText('Total On Hand')).toBeVisible();
        // The card summary shows the number 3 near "Total On Hand"
        await expect(page.locator('p').filter({ hasText: 'Total On Hand' }).getByText('3')).toBeVisible();
    });

    test('back link returns to stock overview', async ({ page }) => {
        await page.goto('/admin/inventory');
        const row = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await row.getByRole('link', { name: 'View' }).click();
        await page.getByRole('link', { name: '← Stock Overview' }).click();
        await expect(page).toHaveURL(/admin\/inventory$/);
    });
});

// ── Serials at SKU + Location ─────────────────────────────────────────────────

test.describe('Serials at SKU + Location', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('clicking View Serials for L1 shows SN-E2E-001 and SN-E2E-002', async ({ page }) => {
        await page.goto('/admin/inventory');
        const skuRow = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await skuRow.getByRole('link', { name: 'View' }).click();

        const l1Row = page.locator('tr', { has: page.getByText('L1') });
        await l1Row.getByRole('link', { name: 'View Serials' }).click();

        await expect(page.getByText('SN-E2E-001')).toBeVisible();
        await expect(page.getByText('SN-E2E-002')).toBeVisible();
        // SN-E2E-003 is at L2 — must not appear here
        await expect(page.getByText('SN-E2E-003')).not.toBeVisible();
    });

    test('each serial row has a Detail link', async ({ page }) => {
        await page.goto('/admin/inventory');
        const skuRow = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await skuRow.getByRole('link', { name: 'View' }).click();
        const l1Row = page.locator('tr', { has: page.getByText('L1') });
        await l1Row.getByRole('link', { name: 'View Serials' }).click();

        const snRow = page.locator('tr', { has: page.getByText('SN-E2E-001') });
        await expect(snRow.getByRole('link', { name: 'Detail' })).toBeVisible();
    });
});

// ── Transfer Movement ─────────────────────────────────────────────────────────

test.describe('Transfer Movement', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('admin can transfer SN-E2E-001 from L1 to L2', async ({ page }) => {
        await page.goto('/admin/inventory-movements/create');

        // Select Transfer type
        await page.check('input[name="type"][value="transfer"]');

        // Select serial
        await page.selectOption('select[name="inventory_serial_id"]', { label: /SN-E2E-001/ });

        // Select from location
        await page.selectOption('select[name="from_location_id"]', { label: /L1/ });

        // Select to location
        await page.selectOption('select[name="to_location_id"]', { label: /L2/ });

        await page.click('button[type="submit"]');

        // Should redirect to movements index with success flash
        await expect(page).toHaveURL(/inventory-movements/);
        await expect(page.getByText(/SN-E2E-001/)).toBeVisible();
    });

    test('after transfer L1 shows 1 unit and L2 shows 2 units for WIDGET-001', async ({ page }) => {
        await page.goto('/admin/inventory');
        const skuRow = page.locator('tr', { has: page.getByText('WIDGET-001') });
        await skuRow.getByRole('link', { name: 'View' }).click();

        const l1Row = page.locator('tr', { has: page.getByText('L1') });
        await expect(l1Row.getByText('1')).toBeVisible();

        const l2Row = page.locator('tr', { has: page.getByText('L2') });
        await expect(l2Row.getByText('2')).toBeVisible();
    });
});

// ── Sale Movement ─────────────────────────────────────────────────────────────

test.describe('Sale Movement', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('admin can sell SN-E2E-004 from L1', async ({ page }) => {
        await page.goto('/admin/inventory-movements/create');

        await page.check('input[name="type"][value="sale"]');
        await page.selectOption('select[name="inventory_serial_id"]', { label: /SN-E2E-004/ });
        await page.selectOption('select[name="from_location_id"]', { label: /L1/ });

        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/inventory-movements/);
        await expect(page.getByText(/SN-E2E-004/)).toBeVisible();
    });

    test('sold serial no longer appears on stock dashboard', async ({ page }) => {
        await page.goto('/admin/inventory');
        // WIDGET-002 had 1 serial (SN-E2E-004) — now sold, so WIDGET-002 row is gone
        await expect(page.getByText('WIDGET-002')).not.toBeVisible();
    });
});

// ── Adjustment Movement ───────────────────────────────────────────────────────

test.describe('Adjustment Movement', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('admin can mark SN-E2E-002 as damaged', async ({ page }) => {
        await page.goto('/admin/inventory-movements/create');

        await page.check('input[name="type"][value="adjustment"]');
        await page.selectOption('select[name="inventory_serial_id"]', { label: /SN-E2E-002/ });
        await page.selectOption('select[name="adjustment_status"]', 'damaged');

        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/inventory-movements/);
        await expect(page.getByText(/SN-E2E-002/)).toBeVisible();
    });

    test('sales role cannot see adjustment option in create form', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/inventory-movements/create');
        await expect(page.locator('input[name="type"][value="adjustment"]')).not.toBeVisible();
    });

    test('sales role gets 403 posting adjustment directly', async ({ page }) => {
        await login(page, SALES);
        await page.goto('/admin/inventory-movements/create');

        // Force-submit an adjustment via fetch bypassing the UI
        const response = await page.evaluate(async () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/admin/inventory-movements', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ type: 'adjustment', inventory_serial_id: 1, adjustment_status: 'damaged' }),
            });
            return res.status;
        });

        expect(response).toBe(403);
    });
});

// ── Movement History (immutability) ───────────────────────────────────────────

test.describe('Movement History', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('movement index lists recorded movements', async ({ page }) => {
        await page.goto('/admin/inventory-movements');
        await expect(page).toHaveURL(/inventory-movements/);
        // Movements from the Transfer, Sale, Adjustment tests above
        await expect(page.locator('table')).toBeVisible();
    });

    test('no edit or delete links on any movement row', async ({ page }) => {
        await page.goto('/admin/inventory-movements');
        await expect(page.getByRole('link', { name: /edit/i })).not.toBeVisible();
        await expect(page.getByRole('button', { name: /delete/i })).not.toBeVisible();
    });

    test('movement edit route returns 404', async ({ page }) => {
        const response = await page.goto('/admin/inventory-movements/1/edit');
        expect(response?.status()).toBe(404);
    });
});
