import { expect, test } from '@playwright/test';

test('the admin app renders on a WordPress boot', async ({ page }) => {
  const pageErrors: string[] = [];
  const consoleErrors: string[] = [];
  page.on('pageerror', (error) => {
    pageErrors.push(error.message);
  });
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL(/\/wp-admin\//);
  await page.goto('/wp-admin/admin.php?page=querynova');
  await expect(page.locator('#querynova-admin')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'What Matters Now' })).toBeVisible();
  await expect(page.getByText('QueryNova admin assets are missing')).toHaveCount(0);
  expect(pageErrors, pageErrors.join('\n')).toEqual([]);
  expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
});
