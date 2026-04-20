# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: purchase-orders.spec.ts >> PO-B01: Admin sees Purchase Orders nav link
- Location: tests/Browser/purchase-orders.spec.ts:18:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator:  locator('a:has-text("Purchase Orders")')
Expected: visible
Received: hidden
Timeout:  5000ms

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for locator('a:has-text("Purchase Orders")')
    9 × locator resolved to <a href="http://localhost:8000/admin/purchase-orders" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">Purchase Orders</a>
      - unexpected value "hidden"

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - navigation [ref=e3]:
      - generic [ref=e5]:
        - generic [ref=e6]:
          - link [ref=e8] [cursor=pointer]:
            - /url: http://localhost:8000/admin/dashboard
            - img [ref=e9]
          - generic [ref=e11]:
            - link "Dashboard" [ref=e12] [cursor=pointer]:
              - /url: http://localhost:8000/admin/dashboard
            - link "Customers" [ref=e13] [cursor=pointer]:
              - /url: http://localhost:8000/admin/customers
            - button "Catalog" [ref=e15] [cursor=pointer]:
              - text: Catalog
              - img [ref=e16]
            - button "Inventory" [ref=e19] [cursor=pointer]:
              - text: Inventory
              - img [ref=e20]
            - button "Procurement" [ref=e23] [cursor=pointer]:
              - text: Procurement
              - img [ref=e24]
            - button "Admin" [ref=e27] [cursor=pointer]:
              - text: Admin
              - img [ref=e28]
        - button "System Admin" [ref=e33] [cursor=pointer]:
          - generic [ref=e34]: System Admin
          - img [ref=e36]
    - banner [ref=e38]:
      - heading "Dashboard" [level=2] [ref=e40]
    - main [ref=e41]:
      - generic [ref=e45]: You're logged in!
  - generic [ref=e48]:
    - generic [ref=e50]:
      - generic [ref=e51] [cursor=pointer]:
        - generic: Request
      - generic [ref=e52] [cursor=pointer]:
        - generic: Timeline
      - generic [ref=e53] [cursor=pointer]:
        - generic: Views
        - generic [ref=e54]: "31"
      - generic [ref=e55] [cursor=pointer]:
        - generic: Queries
        - generic [ref=e56]: "10"
      - generic [ref=e57] [cursor=pointer]:
        - generic: Models
        - generic [ref=e58]: "81"
      - generic [ref=e59] [cursor=pointer]:
        - generic: Gate
        - generic [ref=e60]: "21"
      - generic [ref=e61] [cursor=pointer]:
        - generic: Cache
        - generic [ref=e62]: "4"
    - generic [ref=e63]:
      - generic [ref=e70] [cursor=pointer]:
        - generic [ref=e71]: "2"
        - generic [ref=e72]: GET /admin/dashboard
      - generic [ref=e73] [cursor=pointer]:
        - generic: 24.26ms
      - generic [ref=e75] [cursor=pointer]:
        - generic: 3MB
      - generic [ref=e77] [cursor=pointer]:
        - generic: 13.x
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | 
  3   | const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };
  4   | const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
  5   | const MANAGER = { email: 'manager@sale-pro.test', password: 'password' };
  6   | const SUPER_ADMIN = { email: 'super-admin@sale-pro.test', password: 'password' };
  7   | const WAREHOUSE = { email: 'warehouse@sale-pro.test', password: 'password' };
  8   | 
  9   | async function login(page, credentials) {
  10  |   await page.goto('/admin/login');
  11  |   await page.fill('input[type="email"]', credentials.email);
  12  |   await page.fill('input[type="password"]', credentials.password);
  13  |   await page.click('button[type="submit"]');
  14  |   await page.waitForURL('/admin/dashboard');
  15  | }
  16  | 
  17  | // Auth
  18  | test('PO-B01: Admin sees Purchase Orders nav link', async ({ page }) => {
  19  |   await login(page, ADMIN);
> 20  |   await expect(page.locator('a:has-text("Purchase Orders")')).toBeVisible();
      |                                                               ^ Error: expect(locator).toBeVisible() failed
  21  | });
  22  | 
  23  | test('PO-B02: Procurement can access index', async ({ page }) => {
  24  |   await login(page, PROCUREMENT);
  25  |   await page.goto('/admin/purchase-orders');
  26  |   await expect(page.locator('table')).toBeVisible();
  27  |   expect(page.url()).toContain('/admin/purchase-orders');
  28  | });
  29  | 
  30  | test('PO-B03: Warehouse gets 403 page', async ({ page }) => {
  31  |   await login(page, WAREHOUSE);
  32  |   const response = await page.goto('/admin/purchase-orders');
  33  |   expect(response?.status()).toBe(403);
  34  | });
  35  | 
  36  | // Create PO - Happy Path
  37  | test('PO-B10: Create PO with 1 line', async ({ page }) => {
  38  |   await login(page, PROCUREMENT);
  39  |   await page.goto('/admin/purchase-orders');
  40  |   await page.click('button:has-text("New Purchase Order")');
  41  | 
  42  |   await page.selectOption('select[name="supplier_id"]', { index: 1 });
  43  |   await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  44  |   await page.fill('input[name="lines[0][unit_price]"]', '100');
  45  |   await page.click('button[type="submit"]');
  46  | 
  47  |   await page.waitForURL('/admin/purchase-orders/*');
  48  |   await expect(page.locator('text=/PO-\\d{4}-\\d{4}/')).toBeVisible();
  49  |   await expect(page.locator('text=Draft')).toBeVisible();
  50  | });
  51  | 
  52  | test('PO-B11: Create PO with 2 lines', async ({ page }) => {
  53  |   await login(page, PROCUREMENT);
  54  |   await page.goto('/admin/purchase-orders/create');
  55  | 
  56  |   await page.selectOption('select[name="supplier_id"]', { index: 1 });
  57  |   await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  58  |   await page.fill('input[name="lines[0][unit_price]"]', '100');
  59  | 
  60  |   await page.click('button:has-text("Add Line")');
  61  |   await page.fill('input[name="lines[1][qty_ordered]"]', '10');
  62  |   await page.fill('input[name="lines[1][unit_price]"]', '50');
  63  | 
  64  |   await page.click('button[type="submit"]');
  65  |   await page.waitForURL('/admin/purchase-orders/*');
  66  | 
  67  |   const lineRows = await page.locator('table tbody tr').count();
  68  |   expect(lineRows).toBe(2);
  69  | });
  70  | 
  71  | test('PO-B12: Skip flags visible on form', async ({ page }) => {
  72  |   await login(page, PROCUREMENT);
  73  |   await page.goto('/admin/purchase-orders/create');
  74  | 
  75  |   await expect(page.locator('input[type="checkbox"][name="skip_tech"]')).toBeVisible();
  76  |   await expect(page.locator('input[type="checkbox"][name="skip_qa"]')).toBeVisible();
  77  | });
  78  | 
  79  | test('PO-B14: qty > 10000 shows validation error', async ({ page }) => {
  80  |   await login(page, PROCUREMENT);
  81  |   await page.goto('/admin/purchase-orders/create');
  82  | 
  83  |   await page.selectOption('select[name="supplier_id"]', { index: 1 });
  84  |   await page.fill('input[name="lines[0][qty_ordered]"]', '99999');
  85  |   await page.fill('input[name="lines[0][unit_price]"]', '100');
  86  |   await page.click('button[type="submit"]');
  87  | 
  88  |   await expect(page.locator('text=/max.*10000/')).toBeVisible();
  89  | });
  90  | 
  91  | test('PO-B15: price < 0.01 shows validation error', async ({ page }) => {
  92  |   await login(page, PROCUREMENT);
  93  |   await page.goto('/admin/purchase-orders/create');
  94  | 
  95  |   await page.selectOption('select[name="supplier_id"]', { index: 1 });
  96  |   await page.fill('input[name="lines[0][qty_ordered]"]', '5');
  97  |   await page.fill('input[name="lines[0][unit_price]"]', '0');
  98  |   await page.click('button[type="submit"]');
  99  | 
  100 |   await expect(page.locator('text=/validation|error/')).toBeVisible();
  101 | });
  102 | 
  103 | // Confirm PO
  104 | test('PO-B20: Confirm button visible on draft PO', async ({ page }) => {
  105 |   await login(page, PROCUREMENT);
  106 |   await page.goto('/admin/purchase-orders');
  107 | 
  108 |   // Find draft PO and click it
  109 |   const draftRow = page.locator('text=Draft').first();
  110 |   await draftRow.click();
  111 | 
  112 |   await expect(page.locator('button:has-text("Confirm")')).toBeVisible();
  113 | });
  114 | 
  115 | test('PO-B21: Confirm moves to open status', async ({ page }) => {
  116 |   await login(page, MANAGER);
  117 |   await page.goto('/admin/purchase-orders');
  118 | 
  119 |   const draftRow = page.locator('text=Draft').first();
  120 |   await draftRow.click();
```