import { test, expect } from '@playwright/test';

const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
const MANAGER = { email: 'manager@sale-pro.test', password: 'password' };
const SUPER_ADMIN = { email: 'super-admin@sale-pro.test', password: 'password' };
const WAREHOUSE = { email: 'warehouse@sale-pro.test', password: 'password' };

async function login(page, credentials) {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', credentials.email);
  await page.fill('input[type="password"]', credentials.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/admin/dashboard');
}

// Auth
test('PO-B01: Admin sees Purchase Orders nav link', async ({ page }) => {
  await login(page, ADMIN);
  await expect(page.locator('a:has-text("Purchase Orders")')).toBeVisible();
});

test('PO-B02: Procurement can access index', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders');
  await expect(page.locator('table')).toBeVisible();
  expect(page.url()).toContain('/admin/purchase-orders');
});

test('PO-B03: Warehouse gets 403 page', async ({ page }) => {
  await login(page, WAREHOUSE);
  const response = await page.goto('/admin/purchase-orders');
  expect(response?.status()).toBe(403);
});

// Create PO - Happy Path
test('PO-B10: Create PO with 1 line', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders');
  await page.click('button:has-text("New Purchase Order")');

  await page.selectOption('select[name="supplier_id"]', { index: 1 });
  await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  await page.fill('input[name="lines[0][unit_price]"]', '100');
  await page.click('button[type="submit"]');

  await page.waitForURL('/admin/purchase-orders/*');
  await expect(page.locator('text=/PO-\\d{4}-\\d{4}/')).toBeVisible();
  await expect(page.locator('text=Draft')).toBeVisible();
});

test('PO-B11: Create PO with 2 lines', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders/create');

  await page.selectOption('select[name="supplier_id"]', { index: 1 });
  await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  await page.fill('input[name="lines[0][unit_price]"]', '100');

  await page.click('button:has-text("Add Line")');
  await page.fill('input[name="lines[1][qty_ordered]"]', '10');
  await page.fill('input[name="lines[1][unit_price]"]', '50');

  await page.click('button[type="submit"]');
  await page.waitForURL('/admin/purchase-orders/*');

  const lineRows = await page.locator('table tbody tr').count();
  expect(lineRows).toBe(2);
});

test('PO-B12: Skip flags visible on form', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders/create');

  await expect(page.locator('input[type="checkbox"][name="skip_tech"]')).toBeVisible();
  await expect(page.locator('input[type="checkbox"][name="skip_qa"]')).toBeVisible();
});

test('PO-B14: qty > 10000 shows validation error', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders/create');

  await page.selectOption('select[name="supplier_id"]', { index: 1 });
  await page.fill('input[name="lines[0][qty_ordered]"]', '99999');
  await page.fill('input[name="lines[0][unit_price]"]', '100');
  await page.click('button[type="submit"]');

  await expect(page.locator('text=/max.*10000/')).toBeVisible();
});

test('PO-B15: price < 0.01 shows validation error', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders/create');

  await page.selectOption('select[name="supplier_id"]', { index: 1 });
  await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  await page.fill('input[name="lines[0][unit_price]"]', '0');
  await page.click('button[type="submit"]');

  await expect(page.locator('text=/validation|error/')).toBeVisible();
});

// Confirm PO
test('PO-B20: Confirm button visible on draft PO', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders');

  // Find draft PO and click it
  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();

  await expect(page.locator('button:has-text("Confirm")')).toBeVisible();
});

test('PO-B21: Confirm moves to open status', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();

  await page.click('button:has-text("Confirm")');
  await page.click('button:has-text("Confirm")'); // Modal confirm

  await expect(page.locator('text=Open')).toBeVisible();
});

test('PO-B22: Edit hidden after confirm', async ({ page }) => {
  await login(page, PROCUREMENT);
  await page.goto('/admin/purchase-orders');

  const openRow = page.locator('text=Open').first();
  await openRow.click();

  await expect(page.locator('button:has-text("Edit")')).not.toBeVisible();
});

test('PO-B23: Confirm button hidden on open PO', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const openRow = page.locator('text=Open').first();
  await openRow.click();

  await expect(page.locator('button:has-text("Confirm")')).not.toBeVisible();
});

// Cancel PO
test('PO-B30: Cancel shows notes textarea', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();
  await page.click('button:has-text("Cancel")');

  await expect(page.locator('textarea[name="cancel_notes"]')).toBeVisible();
});

test('PO-B31: Cancel with valid reason succeeds', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();
  await page.click('button:has-text("Cancel")');

  await page.fill('textarea[name="cancel_notes"]', 'Supplier unable to deliver');
  await page.click('button[type="submit"]');

  await expect(page.locator('text=Cancelled')).toBeVisible();
  await expect(page.locator('text=Supplier unable to deliver')).toBeVisible();
});

test('PO-B32: Cancel with short reason shows error', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();
  await page.click('button:has-text("Cancel")');

  await page.fill('textarea[name="cancel_notes"]', 'ok');
  await page.click('button[type="submit"]');

  await expect(page.locator('text=/validation|error/')).toBeVisible();
});

// Reopen PO
test('PO-B40: Reopen button visible on closed PO', async ({ page }) => {
  await login(page, MANAGER);
  await page.goto('/admin/purchase-orders');

  const closedRow = page.locator('text=Closed').first();
  if (closedRow) {
    await closedRow.click();
    await expect(page.locator('button:has-text("Reopen")')).toBeVisible();
  }
});

// Index - Search & Filter
test('PO-B50: Search by PO number', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/purchase-orders');

  const firstPoNumber = await page.locator('table tbody tr:first-child td:first-child').textContent();
  if (firstPoNumber) {
    await page.fill('input[type="search"]', firstPoNumber.slice(0, 5));
    await page.press('input[type="search"]', 'Enter');

    await expect(page.locator('table')).toBeVisible();
  }
});

test('PO-B51: Filter by status=draft', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/purchase-orders');

  await page.selectOption('select[name="status"]', { label: 'Draft' });
  await page.click('button:has-text("Filter")');

  const rows = await page.locator('table tbody tr').count();
  if (rows > 0) {
    await expect(page.locator('text=Draft')).toBeVisible();
  }
});

test('PO-B60: Lines table visible on show page', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/purchase-orders');

  const firstRow = page.locator('table tbody tr:first-child');
  await firstRow.click();

  await expect(page.locator('table')).toBeVisible();
  await expect(page.locator('text=/Product|Qty|Price/')).toBeVisible();
});

test('PO-B62: Action buttons match status', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/purchase-orders');

  const draftRow = page.locator('text=Draft').first();
  await draftRow.click();

  await expect(page.locator('button:has-text("Confirm")')).toBeVisible();
  await expect(page.locator('button:has-text("Edit")')).toBeVisible();
  await expect(page.locator('button:has-text("Cancel")')).toBeVisible();
});
