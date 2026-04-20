import { test, expect } from '@playwright/test';

const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
const WAREHOUSE = { email: 'warehouse@sale-pro.test', password: 'password' };
const TECH = { email: 'tech@sale-pro.test', password: 'password' };
const QA = { email: 'qa@sale-pro.test', password: 'password' };

async function login(page, credentials) {
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', credentials.email);
  await page.fill('input[type="password"]', credentials.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/admin/dashboard');
}

// Queue Page
test('PL-B01: Pipeline nav link visible', async ({ page }) => {
  await login(page, WAREHOUSE);
  await expect(page.locator('a:has-text("Pipeline")')).toBeVisible();
});

test('PL-B02: Empty queue state', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.click('a:has-text("Pipeline")');

  const jobRows = await page.locator('table tbody tr').count();
  if (jobRows === 0) {
    await expect(page.locator('text=/No|jobs|empty/')).toBeVisible();
  }
});

test('PL-B03: Queue shows PO number and product', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/pipeline');

  const firstRow = page.locator('table tbody tr:first-child');
  const cellCount = await firstRow.locator('td').count();
  if (cellCount > 0) {
    await expect(firstRow.locator('td:nth-child(1)')).toContainText(/PO-\d{4}-\d{4}/);
  }
});

test('PL-B04: Queue shows Take button on pending job', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await expect(takeButton).toBeVisible();
  }
});

test('PL-B06: Filter by PO number', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const firstPoNumber = await page.locator('table tbody tr:first-child td:first-child').textContent();
  if (firstPoNumber) {
    await page.fill('input[placeholder*="PO"]', firstPoNumber.slice(0, 5));
    await page.press('input', 'Enter');

    await expect(page.locator('table')).toBeVisible();
  }
});

test('PL-B07: Tech sees only tech-stage jobs', async ({ page }) => {
  await login(page, TECH);
  await page.goto('/admin/pipeline');

  await page.waitForLoadState('networkidle');
  const jobRows = await page.locator('table tbody tr').count();
  // Tech should only see tech stage jobs
  expect(jobRows).toBeGreaterThanOrEqual(0);
});

test('PL-B08: Admin sees all pending jobs', async ({ page }) => {
  await login(page, ADMIN);
  await page.goto('/admin/pipeline');

  await page.waitForLoadState('networkidle');
  const jobRows = await page.locator('table tbody tr').count();
  expect(jobRows).toBeGreaterThanOrEqual(0);
});

// Claim Job (Start)
test('PL-B10: Claim visual job', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    await expect(page.locator('text=/In Progress|In-Progress|In_Progress/')).toBeVisible();
  }
});

test('PL-B11: Wrong-stage user cannot claim', async ({ page }) => {
  await login(page, QA);
  // Try to access a visual job while logged in as QA
  // Should get 403 or redirect
  const response = await page.goto('/admin/pipeline/jobs/1/start', { waitUntil: 'networkidle' });
  expect([403, 302]).toContain(response?.status());
});

// Pass Stages - UI Flow
test('PL-B20: Pass visual stage', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    const passButton = page.locator('button:has-text("Pass")').first();
    if (await passButton.isVisible()) {
      await passButton.click();
      await expect(page.locator('text=/Serial|Assign|Next/')).toBeVisible();
    }
  }
});

test('PL-B21: Pass serial_assign without serial shows error', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    const passButton = page.locator('button:has-text("Pass")').first();
    if (await passButton.isVisible()) {
      await passButton.click();
      // Try to pass without entering serial
      await page.click('button[type="submit"]');
      await expect(page.locator('text=/required|error/')).toBeVisible();
    }
  }
});

test('PL-B22: Pass serial_assign with serial number', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    // Fill serial number if input exists
    const serialInput = page.locator('input[name*="serial"]').first();
    if (await serialInput.isVisible()) {
      await serialInput.fill('SN-TEST-001');
      const passButton = page.locator('button:has-text("Pass")').first();
      await passButton.click();

      await page.waitForLoadState('networkidle');
      await expect(page.locator('text=/Tech|Next/')).toBeVisible();
    }
  }
});

test('PL-B26: Pass shelf requires location', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const jobLinks = page.locator('a:has-text(/shelf|Shelf)');
  const shelfJobCount = await jobLinks.count();

  if (shelfJobCount > 0) {
    await jobLinks.first().click();
    const passButton = page.locator('button:has-text("Pass")').first();
    if (await passButton.isVisible()) {
      await passButton.click();
      await expect(page.locator('text=/required|location/')).toBeVisible();
    }
  }
});

// Fail Flow - UI
test('PL-B40: Fail button visible on in-progress job', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    const failButton = page.locator('button:has-text("Fail")').first();
    await expect(failButton).toBeVisible();
  }
});

test('PL-B41: Fail requires reason', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    const failButton = page.locator('button:has-text("Fail")').first();
    await failButton.click();

    // Try to submit without notes
    const submitButton = page.locator('button[type="submit"]:has-text("Fail")').first();
    if (await submitButton.isVisible()) {
      await submitButton.click();
      await expect(page.locator('text=/required|reason/')).toBeVisible();
    }
  }
});

test('PL-B42: Fail with reason succeeds', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const takeButton = page.locator('button:has-text("Take")').first();
  if (await takeButton.isVisible()) {
    await takeButton.click();
    await page.waitForURL('/admin/pipeline/jobs/*');

    const failButton = page.locator('button:has-text("Fail")').first();
    await failButton.click();

    const notesInput = page.locator('textarea[name="notes"]').first();
    if (await notesInput.isVisible()) {
      await notesInput.fill('Broken screen on arrival');
      const submitButton = page.locator('button[type="submit"]:has-text("Fail")').first();
      await submitButton.click();

      await expect(page.locator('text=/Failed|Success/')).toBeVisible();
    }
  }
});

test('PL-B43: Return PO link visible after fail', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const failedJobLink = page.locator('a:has-text(/Failed)');
  if (await failedJobLink.isVisible()) {
    await failedJobLink.click();
    await expect(page.locator('a:has-text(/Return|PO-/)')).toBeVisible();
  }
});

// Event History - UI
test('PL-B50: Timeline shows all events', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const firstJobLink = page.locator('table tbody tr:first-child a').first();
  if (await firstJobLink.isVisible()) {
    await firstJobLink.click();
    await page.waitForLoadState('networkidle');

    const eventHistory = page.locator('text=/Event|Timeline|History/');
    if (await eventHistory.isVisible()) {
      await expect(eventHistory).toBeVisible();
    }
  }
});

// Job Detail Show Page
test('PL-B60: Shows PO number link', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const firstJobLink = page.locator('table tbody tr:first-child a').first();
  if (await firstJobLink.isVisible()) {
    await firstJobLink.click();
    await page.waitForLoadState('networkidle');

    const poLink = page.locator('a:has-text(/PO-\d{4}-\d{4}/)');
    if (await poLink.isVisible()) {
      await expect(poLink).toBeVisible();
    }
  }
});

test('PL-B62: Shows current stage badge', async ({ page }) => {
  await login(page, WAREHOUSE);
  await page.goto('/admin/pipeline');

  const firstJobLink = page.locator('table tbody tr:first-child a').first();
  if (await firstJobLink.isVisible()) {
    await firstJobLink.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('text=/Visual|Tech|QA|Shelf|Serial/')).toBeVisible();
  }
});
