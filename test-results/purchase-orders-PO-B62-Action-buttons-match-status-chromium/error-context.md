# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: purchase-orders.spec.ts >> PO-B62: Action buttons match status
- Location: tests/Browser/purchase-orders.spec.ts:239:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.click: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('text=Draft').first()
    - locator resolved to <option value="draft">Draft</option>
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
      - generic [ref=e40]:
        - heading "Purchase Orders" [level=2] [ref=e41]
        - link "New Purchase Order" [ref=e42] [cursor=pointer]:
          - /url: http://localhost:8000/admin/purchase-orders/create
    - main [ref=e43]:
      - generic [ref=e45]:
        - generic [ref=e46]:
          - textbox "PO number or supplier…" [ref=e47]
          - combobox [ref=e48]:
            - option "All statuses" [selected]
            - option "Draft"
            - option "Open"
            - option "Partial"
            - option "Closed"
            - option "Cancelled"
          - combobox [ref=e49]:
            - option "All types" [selected]
            - option "Purchase"
            - option "Return"
          - button "Filter" [ref=e50] [cursor=pointer]
          - link "Clear" [ref=e51] [cursor=pointer]:
            - /url: http://localhost:8000/admin/purchase-orders
        - table [ref=e53]:
          - rowgroup [ref=e54]:
            - row "PO Number Type Supplier Status Lines Created By Created" [ref=e55]:
              - columnheader "PO Number" [ref=e56]
              - columnheader "Type" [ref=e57]
              - columnheader "Supplier" [ref=e58]
              - columnheader "Status" [ref=e59]
              - columnheader "Lines" [ref=e60]
              - columnheader "Created By" [ref=e61]
              - columnheader "Created" [ref=e62]
          - rowgroup [ref=e63]:
            - row "No purchase orders found." [ref=e64]:
              - cell "No purchase orders found." [ref=e65]
  - generic [ref=e68]:
    - generic [ref=e70]:
      - generic [ref=e71] [cursor=pointer]:
        - generic: Request
      - generic [ref=e72] [cursor=pointer]:
        - generic: Timeline
      - generic [ref=e73] [cursor=pointer]:
        - generic: Views
        - generic [ref=e74]: "32"
      - generic [ref=e75] [cursor=pointer]:
        - generic: Queries
        - generic [ref=e76]: "11"
      - generic [ref=e77] [cursor=pointer]:
        - generic: Models
        - generic [ref=e78]: "81"
      - generic [ref=e79] [cursor=pointer]:
        - generic: Gate
        - generic [ref=e80]: "25"
      - generic [ref=e81] [cursor=pointer]:
        - generic: Cache
        - generic [ref=e82]: "4"
    - generic [ref=e83]:
      - generic [ref=e91] [cursor=pointer]: GET /admin/purchase-orders
      - generic [ref=e92] [cursor=pointer]:
        - generic: 25.5ms
      - generic [ref=e94] [cursor=pointer]:
        - generic: 3MB
      - generic [ref=e96] [cursor=pointer]:
        - generic: 13.x
```

# Test source

```ts
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
> 244 |   await draftRow.click();
      |                  ^ Error: locator.click: Test timeout of 30000ms exceeded.
  245 | 
  246 |   await expect(page.locator('button:has-text("Confirm")')).toBeVisible();
  247 |   await expect(page.locator('button:has-text("Edit")')).toBeVisible();
  248 |   await expect(page.locator('button:has-text("Cancel")')).toBeVisible();
  249 | });
  250 | 
```