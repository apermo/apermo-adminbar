import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: 'e2e',
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: 'https://apermo-adminbar.ddev.site',
    storageState: 'e2e/.auth/admin.json',
    ignoreHTTPSErrors: true
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }]
});
