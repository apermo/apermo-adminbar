import { test, expect } from '@playwright/test';
test('plugin settings page renders', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=apermo_adminbar');
  await expect(page.locator('h1')).toContainText(/AdminBar/i);
});
