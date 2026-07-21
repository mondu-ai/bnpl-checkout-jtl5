import { test, expect } from '@playwright/test';

const BASE_URL = 'https://jtl5-ivan-local.casa-kuhl.de';

test.describe('PT-3766: /mondu-api route does not crash', () => {
  test('GET /mondu-api returns 200, not 500', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/mondu-api`);
    expect(response.status()).not.toBe(500);
    expect(response.status()).toBe(200);
  });

  test('POST /mondu-api without params returns valid response', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/mondu-api`, {
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).not.toBe(500);
  });

  test('Homepage still loads correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/`);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url()).toContain(BASE_URL.replace('https://', ''));

    const body = await page.textContent('body');
    expect(body).toBeTruthy();
    expect(body!.length).toBeGreaterThan(100);
  });

  test('Mondu CSS is loaded on storefront pages', async ({ page }) => {
    await page.goto(`${BASE_URL}/`);
    await page.waitForLoadState('domcontentloaded');

    const monduCss = page.locator('link[href*="MonduPayment"][href*="style.css"], link[href*="mondu"][href*="style.css"]');
    const count = await monduCss.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('Webhook endpoint accepts POST', async ({ request }) => {
    const body = JSON.stringify({
      topic: 'order/confirmed',
      order_uuid: 'e2e-test-uuid',
      external_reference_id: 'E2E-JTL5-001',
    });

    const response = await request.post(`${BASE_URL}/mondu-api?return=webhook`, {
      data: body,
      headers: { 'Content-Type': 'application/json' },
    });

    // Should not crash with 500 — any other status is acceptable
    expect(response.status()).not.toBe(500);
  });
});
