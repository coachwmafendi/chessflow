import { defineConfig } from '@playwright/test';

const PORT = 8123;

export default defineConfig({
    testDir: 'tests/e2e',
    // Fresh test accounts (student + teacher) for the feature specs.
    globalSetup: './tests/e2e/global-setup.ts',
    timeout: 30_000,
    fullyParallel: true,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        // System Chrome: no browser download needed locally.
        channel: process.env.PW_CHANNEL ?? 'chrome',
    },
    webServer: {
        // Assets come from public/build (run `npm run build` first; remove public/hot if the dev server is off).
        command: `php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}/dev/playground`,
        reuseExistingServer: !process.env.CI,
        env: { APP_ENV: 'local' },
    },
});
