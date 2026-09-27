import { defineConfig } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL ?? 'http://127.0.0.1:8080';
const core = process.env.WP_CORE_DIR ?? '/tmp/wordpress';

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 60_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  retries: 0,
  reporter: 'line',
  use: {
    baseURL,
    headless: true,
  },
  webServer: {
    command: `php -S 127.0.0.1:8080 -t "${core}" bin/wordpress-dev-router.php`,
    url: `${baseURL}/wp-login.php`,
    timeout: 60_000,
    reuseExistingServer: false,
  },
});
