import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  use: {
    baseURL: 'https://jtl5-ivan-local.casa-kuhl.de',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    locale: 'de-DE',
  },
});
