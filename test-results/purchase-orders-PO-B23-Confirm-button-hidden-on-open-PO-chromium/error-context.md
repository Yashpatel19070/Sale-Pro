# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: purchase-orders.spec.ts >> PO-B23: Confirm button hidden on open PO
- Location: tests/Browser/purchase-orders.spec.ts:138:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.click: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('text=Open').first()
    - locator resolved to <option value="open">Open</option>
  - attempting click action
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
    - waiting 20ms
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
      - waiting 100ms
    58 × waiting for element to be visible, enabled and stable
       - element is not visible
     - retrying click action
       - waiting 500ms

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
            - button "Inventory" [ref=e15] [cursor=pointer]:
              - text: Inventory
              - img [ref=e16]
            - button "Procurement" [ref=e19] [cursor=pointer]:
              - text: Procurement
              - img [ref=e20]
            - button "Admin" [ref=e23] [cursor=pointer]:
              - text: Admin
              - img [ref=e24]
        - button "Sales Manager" [ref=e29] [cursor=pointer]:
          - generic [ref=e30]: Sales Manager
          - img [ref=e32]
    - banner [ref=e34]:
      - heading "Purchase Orders" [level=2] [ref=e37]
    - main [ref=e38]:
      - generic [ref=e40]:
        - generic [ref=e41]:
          - textbox "PO number or supplier…" [ref=e42]
          - combobox [ref=e43]:
            - option "All statuses" [selected]
            - option "Draft"
            - option "Open"
            - option "Partial"
            - option "Closed"
            - option "Cancelled"
          - combobox [ref=e44]:
            - option "All types" [selected]
            - option "Purchase"
            - option "Return"
          - button "Filter" [ref=e45] [cursor=pointer]
          - link "Clear" [ref=e46] [cursor=pointer]:
            - /url: http://localhost:8000/admin/purchase-orders
        - table [ref=e48]:
          - rowgroup [ref=e49]:
            - row "PO Number Type Supplier Status Lines Created By Created" [ref=e50]:
              - columnheader "PO Number" [ref=e51]
              - columnheader "Type" [ref=e52]
              - columnheader "Supplier" [ref=e53]
              - columnheader "Status" [ref=e54]
              - columnheader "Lines" [ref=e55]
              - columnheader "Created By" [ref=e56]
              - columnheader "Created" [ref=e57]
          - rowgroup [ref=e58]:
            - row "No purchase orders found." [ref=e59]:
              - cell "No purchase orders found." [ref=e60]
  - generic [ref=e63]:
    - generic [ref=e65]:
      - generic [ref=e66] [cursor=pointer]:
        - generic: Request
      - generic [ref=e67] [cursor=pointer]:
        - generic: Timeline
      - generic [ref=e68] [cursor=pointer]:
        - generic: Views
        - generic [ref=e69]: "26"
      - generic [ref=e70] [cursor=pointer]:
        - generic: Queries
        - generic [ref=e71]: "11"
      - generic [ref=e72] [cursor=pointer]:
        - generic: Models
        - generic [ref=e73]: "44"
      - generic [ref=e74] [cursor=pointer]:
        - generic: Gate
        - generic [ref=e75]: "23"
      - generic [ref=e76] [cursor=pointer]:
        - generic: Cache
        - generic [ref=e77]: "4"
    - generic [ref=e78]:
      - generic [ref=e86] [cursor=pointer]: GET /admin/purchase-orders
      - generic [ref=e87] [cursor=pointer]:
        - generic: 25.11ms
      - generic [ref=e89] [cursor=pointer]:
        - generic: 3MB
      - generic [ref=e91] [cursor=pointer]:
        - generic: 13.x
```

# Test source

```ts
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
  121 | 
  122 |   await page.click('button:has-text("Confirm")');
  123 |   await page.click('button:has-text("Confirm")'); // Modal confirm
  124 | 
  125 |   await expect(page.locator('text=Open')).toBeVisible();
  126 | });
  127 | 
  128 | test('PO-B22: Edit hidden after confirm', async ({ page }) => {
  129 |   await login(page, PROCUREMENT);
  130 |   await page.goto('/admin/purchase-orders');
  131 | 
  132 |   const openRow = page.locator('text=Open').first();
  133 |   await openRow.click();
  134 | 
  135 |   await expect(page.locator('button:has-text("Edit")')).not.toBeVisible();
  136 | });
  137 | 
  138 | test('PO-B23: Confirm button hidden on open PO', async ({ page }) => {
  139 |   await login(page, MANAGER);
  140 |   await page.goto('/admin/purchase-orders');
  141 | 
  142 |   const openRow = page.locator('text=Open').first();
> 143 |   await openRow.click();
      |                 ^ Error: locator.click: Test timeout of 30000ms exceeded.
  144 | 
  145 |   await expect(page.locator('button:has-text("Confirm")')).not.toBeVisible();
  146 | });
  147 | 
  148 | // Cancel PO
  149 | test('PO-B30: Cancel shows notes textarea', async ({ page }) => {
  150 |   await login(page, MANAGER);
  151 |   await page.goto('/admin/purchase-orders');
  152 | 
  153 |   const draftRow = page.locator('text=Draft').first();
  154 |   await draftRow.click();
  155 |   await page.click('button:has-text("Cancel")');
  156 | 
  157 |   await expect(page.locator('textarea[name="cancel_notes"]')).toBeVisible();
  158 | });
  159 | 
  160 | test('PO-B31: Cancel with valid reason succeeds', async ({ page }) => {
  161 |   await login(page, MANAGER);
  162 |   await page.goto('/admin/purchase-orders');
  163 | 
  164 |   const draftRow = page.locator('text=Draft').first();
  165 |   await draftRow.click();
  166 |   await page.click('button:has-text("Cancel")');
  167 | 
  168 |   await page.fill('textarea[name="cancel_notes"]', 'Supplier unable to deliver');
  169 |   await page.click('button[type="submit"]');
  170 | 
  171 |   await expect(page.locator('text=Cancelled')).toBeVisible();
  172 |   await expect(page.locator('text=Supplier unable to deliver')).toBeVisible();
  173 | });
  174 | 
  175 | test('PO-B32: Cancel with short reason shows error', async ({ page }) => {
  176 |   await login(page, MANAGER);
  177 |   await page.goto('/admin/purchase-orders');
  178 | 
  179 |   const draftRow = page.locator('text=Draft').first();
  180 |   await draftRow.click();
  181 |   await page.click('button:has-text("Cancel")');
  182 | 
  183 |   await page.fill('textarea[name="cancel_notes"]', 'ok');
  184 |   await page.click('button[type="submit"]');
  185 | 
  186 |   await expect(page.locator('text=/validation|error/')).toBeVisible();
  187 | });
  188 | 
  189 | // Reopen PO
  190 | test('PO-B40: Reopen button visible on closed PO', async ({ page }) => {
  191 |   await login(page, MANAGER);
  192 |   await page.goto('/admin/purchase-orders');
  193 | 
  194 |   const closedRow = page.locator('text=Closed').first();
  195 |   if (closedRow) {
  196 |     await closedRow.click();
  197 |     await expect(page.locator('button:has-text("Reopen")')).toBeVisible();
  198 |   }
  199 | });
  200 | 
  201 | // Index - Search & Filter
  202 | test('PO-B50: Search by PO number', async ({ page }) => {
  203 |   await login(page, ADMIN);
  204 |   await page.goto('/admin/purchase-orders');
  205 | 
  206 |   const firstPoNumber = await page.locator('table tbody tr:first-child td:first-child').textContent();
  207 |   if (firstPoNumber) {
  208 |     await page.fill('input[type="search"]', firstPoNumber.slice(0, 5));
  209 |     await page.press('input[type="search"]', 'Enter');
  210 | 
  211 |     await expect(page.locator('table')).toBeVisible();
  212 |   }
  213 | });
  214 | 
  215 | test('PO-B51: Filter by status=draft', async ({ page }) => {
  216 |   await login(page, ADMIN);
  217 |   await page.goto('/admin/purchase-orders');
  218 | 
  219 |   await page.selectOption('select[name="status"]', { label: 'Draft' });
  220 |   await page.click('button:has-text("Filter")');
  221 | 
  222 |   const rows = await page.locator('table tbody tr').count();
  223 |   if (rows > 0) {
  224 |     await expect(page.locator('text=Draft')).toBeVisible();
  225 |   }
  226 | });
  227 | 
  228 | test('PO-B60: Lines table visible on show page', async ({ page }) => {
  229 |   await login(page, ADMIN);
  230 |   await page.goto('/admin/purchase-orders');
  231 | 
  232 |   const firstRow = page.locator('table tbody tr:first-child');
  233 |   await firstRow.click();
  234 | 
  235 |   await expect(page.locator('table')).toBeVisible();
  236 |   await expect(page.locator('text=/Product|Qty|Price/')).toBeVisible();
  237 | });
  238 | 
  239 | test('PO-B62: Action buttons match status', async ({ page }) => {
  240 |   await login(page, ADMIN);
  241 |   await page.goto('/admin/purchase-orders');
  242 | 
  243 |   const draftRow = page.locator('text=Draft').first();
```