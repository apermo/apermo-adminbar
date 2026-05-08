import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
export default async (config: FullConfig) => {
  const { baseURL } = config.projects[0].use;
  mkdirSync('e2e/.auth', { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
  await ctx.storageState({ path: 'e2e/.auth/admin.json' });
  await browser.close();
};
