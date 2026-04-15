import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',

    // Tests share a real MySQL DB — run sequentially to avoid state conflicts
    fullyParallel: false,
    workers: 1,

    retries: 0,
    reporter: 'list',

    use: {
        baseURL: 'http://localhost:8000',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'retain-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    // Auto-start dev server if not already running
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: true,
        timeout: 30_000,
    },

    outputDir: 'test-results',
});
