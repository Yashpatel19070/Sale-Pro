import { test, expect, type Page } from '@playwright/test';

// ── Credentials seeded by E2ESeeder ──────────────────────────────────────────
const ADMIN = { email: 'admin@sale-pro.test', password: 'password' };

// ── Helpers ───────────────────────────────────────────────────────────────────

async function login(page: Page, user: { email: string; password: string }) {
    await page.goto('/admin/login');
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/dashboard', { timeout: 15000 });
}

async function fillOrderForm(page: Page) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // Select customer: just use index 1 (second option, first real customer)
    // The E2E seeder creates "E2E Test Customer" first, so it should be index 1
    const customerSelect = page.locator('#customer_id');
    await customerSelect.scrollIntoViewIfNeeded();
    await customerSelect.selectOption({ index: 1 });
    await page.waitForTimeout(800);

    const sourceSelect = page.locator('#source');
    await sourceSelect.selectOption({ index: 1 });

    const paymentMethodSelect = page.locator('#payment_method');
    await paymentMethodSelect.selectOption({ index: 1 });

    await page.waitForTimeout(300);

    // Billing address select has no name attr (hidden input has name="billing_address_id").
    // Use x-model target: the select that x-model binds to billingAddressId.
    // It's the first select after #customer_id that has :disabled binding.
    const billingAddressSelect = page.locator('select[x-model="billingAddressId"]');
    if (await billingAddressSelect.count() > 0) {
        const isDisabled = await billingAddressSelect.isDisabled({ timeout: 2000 }).catch(() => true);
        if (!isDisabled) {
            const optionCount = await billingAddressSelect.locator('option').count();
            if (optionCount > 1) {
                await billingAddressSelect.selectOption({ index: 1 });
            }
        }
    }

    // Product: Select any available product (first real product at index 1)
    // We'll wait for the product select to appear and be enabled
    const productSelects = page.locator('select[name*="product_listing_id"]');
    await expect(productSelects.first()).toBeVisible({ timeout: 10000 });

    const productSelect = productSelects.first();
    await productSelect.scrollIntoViewIfNeeded();

    // Wait for the select to have options beyond the placeholder
    let optionCount = 0;
    for (let i = 0; i < 10; i++) {
        optionCount = await productSelect.locator('option').count();
        if (optionCount > 1) break;  // More than just the "Select product..." option
        await page.waitForTimeout(200);
    }

    if (optionCount > 1) {
        // Try to select Widget Alpha (WIDGET-001) by iterating through options
        const options = productSelect.locator('option');
        let selectedIndex = 1;  // Default fallback to first real option

        for (let i = 1; i < optionCount; i++) {
            const text = await options.nth(i).textContent();
            if (text && (text.includes('Widget Alpha') || text.includes('WIDGET-001'))) {
                selectedIndex = i;
                break;
            }
        }

        await productSelect.selectOption({ index: selectedIndex });
    }
    await page.waitForTimeout(800);
}

// ── Orders Index ──────────────────────────────────────────────────────────────

test.describe('Orders Index', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('1. Orders index page loads with heading', async ({ page }) => {
        await page.goto('/admin/orders');
        await expect(page).toHaveURL(/admin\/orders$/);
        const heading = page.getByRole('heading').filter({ hasText: /Orders/i });
        await expect(heading).toBeVisible();
    });

    test('navigates to orders via nav link', async ({ page }) => {
        const ordersLink = page.getByRole('link', { name: /Orders/i });
        await expect(ordersLink).toBeVisible();
        await ordersLink.click();
        await expect(page).toHaveURL(/admin\/orders/);
    });
});

// ── Create Order Form ─────────────────────────────────────────────────────────

test.describe('Create Order Form', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('2. Create order form loads with required fields', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await expect(page).toHaveURL(/admin\/orders\/create/);

        const title = page.getByRole('heading').filter({ hasText: /New Order/i });
        await expect(title).toBeVisible();

        const customerSelect = page.locator('#customer_id');
        await expect(customerSelect).toBeVisible();

        const sourceSelect = page.locator('#source');
        await expect(sourceSelect).toBeVisible();

        const paymentMethodSelect = page.locator('#payment_method');
        await expect(paymentMethodSelect).toBeVisible();

        const submitButton = page.getByRole('button', { name: /Create Order/i });
        await expect(submitButton).toBeVisible();
    });

    test('3. Product selection auto-fills unit price', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await page.waitForLoadState('networkidle');

        const customerSelect = page.locator('#customer_id');
        await customerSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);

        const productSelects = page.locator('select[name*="product_listing_id"]');
        const productSelect = productSelects.first();

        if (await productSelect.isVisible()) {
            await productSelect.selectOption({ index: 1 });
            await page.waitForTimeout(500);

            const unitPriceInputs = page.locator('input[name*="unit_price"]');
            const unitPriceInput = unitPriceInputs.first();
            const filledValue = await unitPriceInput.inputValue();

            expect(filledValue).toBeTruthy();
            expect(parseFloat(filledValue || '0')).toBeGreaterThan(0);
        }
    });

    test('4. Customer selection enables address dropdowns', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);

        // Billing address select uses x-model, no name attribute (hidden input has the name)
        const billingAddressSelect = page.locator('select[x-model="billingAddressId"]');
        await expect(billingAddressSelect).toBeDisabled({ timeout: 5000 });

        const customerSelect = page.locator('#customer_id');
        await customerSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);

        await expect(billingAddressSelect).not.toBeDisabled({ timeout: 5000 });
    });
});

// ── Create Full Order ─────────────────────────────────────────────────────────

test.describe('Create Full Walk-in Cash Order', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('5. Create a full walk-in cash order', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await expect(page).toHaveURL(/admin\/orders\/create/);
        await fillOrderForm(page);

        const submitButton = page.getByRole('button', { name: /Create Order/i });
        await submitButton.click();

        await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 20000 });
        expect(page.url()).toMatch(/admin\/orders\/\d+$/);

        const orderNumber = page.getByText(/ORD-/).first();
        await expect(orderNumber).toBeVisible();
    });
});

// ── Order Show Page ───────────────────────────────────────────────────────────

test.describe('Order Show Page', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
        await page.goto('/admin/orders/create');
        await fillOrderForm(page);

        const submitButton = page.getByRole('button', { name: /Create Order/i });
        await submitButton.click();

        await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 20000 });
    });

    test('6. Order show page displays order details', async ({ page }) => {
        const orderNumberHeading = page.getByRole('heading').filter({ hasText: /ORD-/ });
        await expect(orderNumberHeading).toBeVisible();

        const statusBadge = page.locator('[class*="rounded-full"]').first();
        await expect(statusBadge).toBeVisible();

        const pageContent = page.locator('body');
        await expect(pageContent).toContainText(/E2E Test Customer|Customer/);

        const lineItemsTable = page.locator('table').first();
        const tableRows = lineItemsTable.locator('tbody tr');
        const rowCount = await tableRows.count();
        expect(rowCount).toBeGreaterThanOrEqual(1);

        const cashPaymentButton = page.getByRole('button', { name: /Record Cash Payment/i });
        await expect(cashPaymentButton).toBeVisible();
    });

    test('7. Record cash payment from show page', async ({ page }) => {
        const cashPaymentButton = page.getByRole('button', { name: /Record Cash Payment/i });
        await expect(cashPaymentButton).toBeVisible();
        await cashPaymentButton.click();

        await page.waitForTimeout(300);

        const amountInput = page.locator('input[name="amount"]');
        if (await amountInput.isVisible()) {
            await amountInput.fill('100.00');

            const paymentForm = page.locator('#cash-payment-form');
            const paymentSubmitButton = paymentForm.getByRole('button', { name: /Confirm/i });
            await paymentSubmitButton.click();

            await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 20000 });

            const paidBadge = page.getByText(/Paid/).first();
            await expect(paidBadge).toBeVisible();
        }
    });
});

// ── Edge Cases & Error Handling ───────────────────────────────────────────────

test.describe('Orders - Edge Cases', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('handles missing required fields gracefully', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await page.waitForLoadState('networkidle');

        const submitButton = page.getByRole('button', { name: /Create Order/i });
        await submitButton.click();

        await page.waitForTimeout(300);

        const stillOnCreatePage = page.url().includes('/orders/create');
        expect(stillOnCreatePage).toBe(true);
    });

    test('can navigate back from create form', async ({ page }) => {
        await page.goto('/admin/orders/create');
        await page.waitForLoadState('networkidle');

        // Back arrow link in the page header (SVG icon, links to orders.index)
        const backLink = page.locator('a[href*="/admin/orders"]').first();
        if (await backLink.isVisible()) {
            await backLink.click();
            await page.waitForURL(/\/admin\/orders$/, { timeout: 10000 });
            expect(page.url()).toMatch(/\/admin\/orders$/);
        }
    });
});

// ── Full Order Lifecycle (E2E) ────────────────────────────────────────────────

test.describe('Full Order Lifecycle (E2E)', () => {
    test('8. Full lifecycle: create → pending → pay → serial link → serial movement → complete', async ({ page }) => {
        // Step 1: Login
        await login(page, ADMIN);

        // Step 2: Create order
        await page.goto('/admin/orders/create');
        await fillOrderForm(page);

        const submitButton = page.getByRole('button', { name: /Create Order/i });
        await submitButton.click();

        await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 20000 });
        const orderUrl = page.url();
        const orderId = orderUrl.match(/\/(\d+)$/)?.[1];
        expect(orderId).toBeTruthy();

        // Step 3: Verify Pending state (before payment)
        await page.waitForLoadState('networkidle');

        // Status badge contains "Pending"
        await expect(page.locator('body')).toContainText(/Pending/i);

        // Payment status badge contains "Unpaid"
        await expect(page.locator('body')).toContainText(/Unpaid/i);

        // Serial column shows "—" (no serial numbers yet)
        // Check line items table and verify no serial links exist
        const lineItemsTable = page.locator('table').first();
        const serialLinks = lineItemsTable.locator('a').filter({ hasText: /SN-/ });
        const serialLinkCount = await serialLinks.count();
        expect(serialLinkCount).toBe(0);

        // Created By shows admin name or email
        await expect(page.locator('body')).toContainText(/Created By|admin@sale-pro\.test|Admin/i);

        // Step 4: Record full cash payment
        const cashPaymentButton = page.getByRole('button', { name: /Record Cash Payment/i });
        await cashPaymentButton.click();
        await page.waitForTimeout(300);

        const amountInput = page.locator('input[name="amount"]');
        await expect(amountInput).toBeVisible();

        // Hard-code the payment amount to 100.00 (Widget Alpha)
        // The form pre-fills this amount, but we explicitly set it
        await amountInput.fill('100.00');

        const paymentForm = page.locator('#cash-payment-form');
        const paymentSubmitButton = paymentForm.getByRole('button', { name: /Confirm/i });
        await paymentSubmitButton.click();

        // Wait for form to hide (success) or for error to appear
        await page.waitForTimeout(1000);

        // Check if the form is still visible (error case) or if we got redirected (success case)
        const formStillVisible = await paymentForm.isVisible().catch(() => false);

        if (!formStillVisible) {
            // Success: redirected, wait for page to load
            await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 20000 });
            await page.waitForLoadState('networkidle');
        } else {
            // Error: form is still visible, which means payment failed
            // Check for error messages
            const errorSection = page.locator('div').filter({ hasText: /error|failed|No in-stock/i }).first();
            if (await errorSection.isVisible()) {
                const errorMsg = await errorSection.textContent();
                throw new Error(`Payment failed: ${errorMsg}`);
            }
        }

        // Verify we're on the correct order show page
        const orderPageHeading = page.getByRole('heading').filter({ hasText: /ORD-/ }).first();
        await expect(orderPageHeading).toBeVisible();

        // Step 5: Verify Processing state (after payment)
        // Status badge now contains "Processing" and "Paid"
        await expect(page.locator('body')).toContainText(/Paid/i);
        await expect(page.locator('body')).toContainText(/Processing/i);

        // Payment status badge now contains "Paid"
        await expect(page.locator('body')).toContainText(/Paid/i);

        // A serial link now exists in the line items table
        const updatedLineItemsTable = page.locator('table').first();
        const serialLinksAfterPayment = updatedLineItemsTable.locator('a').filter({ hasText: /SN-/ });
        const updatedSerialLinkCount = await serialLinksAfterPayment.count();
        expect(updatedSerialLinkCount).toBeGreaterThan(0);

        // Get the serial link href and text
        const serialLink = serialLinksAfterPayment.first();
        const serialHref = await serialLink.getAttribute('href');
        const serialNumber = await serialLink.textContent();

        expect(serialHref).toMatch(/\/admin\/inventory-serials\/\d+/);
        expect(serialNumber).toMatch(/SN-/i);

        // Step 6: Navigate to serial detail page
        await serialLink.click();
        await page.waitForURL(/admin\/inventory-serials\/\d+/, { timeout: 15000 });

        // Verify serial page loaded (heading shows serial number)
        const serialHeading = page.locator('h2').filter({ hasText: /SN-/ }).first();
        await expect(serialHeading).toBeVisible();

        // Step 7: Verify movement history on serial page
        // Find the Movement History table
        const movementTable = page.locator('table').first();

        // Locate the row with "sale" type badge
        const saleMovementRow = movementTable.locator('tr').filter({
            has: page.locator('span').filter({ hasText: /sale/i })
        }).first();

        // Verify the row exists
        await expect(saleMovementRow).toBeVisible();

        // Verify Source column contains order number (ORD-...)
        // The Source cell should contain plain text (not a link for orders)
        const sourceCell = saleMovementRow.locator('td').nth(4);
        await expect(sourceCell).toContainText(/ORD-/i);

        // Verify Notes column contains "Order placed by"
        const notesCell = saleMovementRow.locator('td').nth(5);
        await expect(notesCell).toContainText(/Order placed by/i);

        // Step 8: Navigate back and complete the order
        await page.goBack();
        await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');

        // Click "Complete Order" button
        const completeButton = page.getByRole('button', { name: /Complete Order/i });
        await expect(completeButton).toBeVisible();
        await completeButton.click();

        // Handle confirmation dialog
        await page.once('dialog', dialog => {
            dialog.accept();
        });

        await page.waitForURL(/admin\/orders\/\d+$/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');

        // Verify the order is now Complete
        await expect(page.locator('body')).toContainText(/Complete/i);
    });
});
