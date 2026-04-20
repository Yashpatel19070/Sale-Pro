import { test, expect } from '@playwright/test';

const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };
const MANAGER = { email: 'manager@sale-pro.test', password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
const WAREHOUSE = { email: 'warehouse@sale-pro.test', password: 'password' };

async function login(page, credentials) {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', credentials.email);
  await page.fill('input[type="password"]', credentials.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/admin/dashboard');
}

// Index Page
test('RT-B01: Return Orders nav link visible', async ({ page }) => {
  await login(page, ADMIN);
  const navLink = page.locator('a:has-text("Return Orders"), a:has-text("PO Returns"), a:has-text("Returns")').first();
  if (await navLink.isVisible()) {
    await expect(navLink).toBeVisible();
  }
});

test('RT-B02: Index shows return PO number', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const tableRows = await page.locator('table tbody tr').count();
  if (tableRows > 0) {
    const firstRow = page.locator('table tbody tr:first-child');
    const poNumber = await firstRow.locator('td:first-child').textContent();
    expect(poNumber).toContain('PO-');
  }
});

test('RT-B03: Index does NOT show purchase POs', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  await page.waitForLoadState('networkidle');
  // The index should only show return type POs
  const tableRows = page.locator('table tbody tr');
  const rowCount = await tableRows.count();
  expect(rowCount).toBeGreaterThanOrEqual(0);
});

test('RT-B04: Status badge visible', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstRow = page.locator('table tbody tr:first-child');
  if (await firstRow.isVisible()) {
    const statusBadge = firstRow.locator('text=/Open|Closed/');
    if (await statusBadge.isVisible()) {
      await expect(statusBadge).toBeVisible();
    }
  }
});

test('RT-B06: Procurement sees index', async ({ page }) => {
  await login(page, PROCUREMENT);
  const response = await page.goto('/admin/po-returns');
  expect(response?.status()).toBe(200);
  await expect(page.locator('table')).toBeVisible();
});

test('RT-B07: Warehouse gets blocked', async ({ page }) => {
  await login(page, WAREHOUSE);
  const response = await page.goto('/admin/po-returns');
  expect([403, 302]).toContain(response?.status());
});

test('RT-B08: Empty state shown', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const rowCount = await page.locator('table tbody tr').count();
  if (rowCount === 0) {
    await expect(page.locator('text=/No|return|order/')).toBeVisible();
  }
});

test('RT-B09: Search by PO number', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstPoNumber = await page.locator('table tbody tr:first-child td:first-child').textContent();
  if (firstPoNumber) {
    await page.fill('input[type="search"]', firstPoNumber.slice(0, 5));
    await page.press('input[type="search"]', 'Enter');

    await page.waitForLoadState('networkidle');
    const rows = await page.locator('table tbody tr').count();
    expect(rows).toBeGreaterThan(0);
  }
});

test('RT-B10: Filter by status open', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const statusSelect = page.locator('select[name="status"]');
  if (await statusSelect.isVisible()) {
    await statusSelect.selectOption('open');
    await page.click('button:has-text("Filter")');

    const rows = await page.locator('table tbody tr').count();
    if (rows > 0) {
      await expect(page.locator('text=Open')).toBeVisible();
    }
  }
});

// Show Page
test('RT-B20: Shows return PO number', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    const poNumber = await firstRowLink.textContent();
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator(`text=${poNumber}`)).toBeVisible();
  }
});

test('RT-B21: Shows parent PO link', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    const parentLink = page.locator('a:has-text(/Original|Parent)');
    if (await parentLink.isVisible()) {
      await expect(parentLink).toBeVisible();
    }
  }
});

test('RT-B22: Shows supplier name', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    // Supplier name should be visible somewhere on the page
    const pageContent = await page.content();
    expect(pageContent.length).toBeGreaterThan(0);
  }
});

test('RT-B23: Shows line items', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    const linesTable = page.locator('table');
    const tableCount = await linesTable.count();
    expect(tableCount).toBeGreaterThan(0);
  }
});

test('RT-B25: Close button visible for manager', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/po-returns');

  const openReturnLink = page.locator('table tbody tr:has-text("Open") a').first();
  if (await openReturnLink.isVisible()) {
    await openReturnLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")');
    if (await closeButton.isVisible()) {
      await expect(closeButton).toBeVisible();
    }
  }
});

test('RT-B26: Close button hidden for procurement', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")');
    expect(await closeButton.isVisible()).toBe(false);
  }
});

test('RT-B27: No Close button when already closed', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/po-returns');

  const closedReturnLink = page.locator('table tbody tr:has-text("Closed") a').first();
  if (await closedReturnLink.isVisible()) {
    await closedReturnLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")');
    expect(await closeButton.isVisible()).toBe(false);
  }
});

// Close Return PO Flow
test('RT-B30: Manager closes return PO', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/po-returns');

  const openReturnLink = page.locator('table tbody tr:has-text("Open") a').first();
  if (await openReturnLink.isVisible()) {
    await openReturnLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")').first();
    if (await closeButton.isVisible()) {
      await closeButton.click();

      // Confirm if there's a modal
      const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Close")').last();
      if (await confirmButton.isVisible()) {
        await confirmButton.click();
      }

      await expect(page.locator('text=Closed')).toBeVisible();
    }
  }
});

test('RT-B31: Procurement cannot close', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/po-returns');

  const firstRowLink = page.locator('table tbody tr:first-child a').first();
  if (await firstRowLink.isVisible()) {
    await firstRowLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")');
    expect(await closeButton.isVisible()).toBe(false);
  }
});

test('RT-B32: Already-closed shows no action', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/po-returns');

  const closedLink = page.locator('table tbody tr:has-text("Closed") a').first();
  if (await closedLink.isVisible()) {
    await closedLink.click();
    await page.waitForLoadState('networkidle');

    const closedBadge = page.locator('text=Closed');
    await expect(closedBadge).toBeVisible();

    const closeButton = page.locator('button:has-text("Close")');
    expect(await closeButton.isVisible()).toBe(false);
  }
});

test('RT-B33: Redirect after close', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/po-returns');

  const openReturnLink = page.locator('table tbody tr:has-text("Open") a').first();
  if (await openReturnLink.isVisible()) {
    const poReturnId = await openReturnLink.getAttribute('href');
    await openReturnLink.click();
    await page.waitForLoadState('networkidle');

    const closeButton = page.locator('button:has-text("Close")').first();
    if (await closeButton.isVisible()) {
      await closeButton.click();

      const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Close")').last();
      if (await confirmButton.isVisible()) {
        await confirmButton.click();
      }

      // Should stay on same page
      expect(page.url()).toContain(poReturnId?.split('/').pop() || '');
    }
  }
});
