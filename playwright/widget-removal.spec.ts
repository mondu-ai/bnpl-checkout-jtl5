import { test, expect, Page } from '@playwright/test'

/**
 * PT-4650 regression: the storefront checkout must carry no widget bootstrap,
 * while Mondu payment methods and the hosted-checkout handoff keep working.
 *
 * Drives the real JTL storefront rather than the API-seeding harness used by
 * mondu-credit-note.spec.ts, because everything PT-4650 removed lives in the
 * frontend hook, plugin.js and the checkout stylesheet.
 *
 * Requires SHOP_URL in .env (the storefront base URL). Buyer address fields
 * fall back to sensible defaults; override with the BUYER_* variables.
 *
 * By default this stops on the confirmation step and places no order, so it
 * creates nothing in Mondu. Set E2E_PLACE_ORDER=1 to also assert the hosted
 * redirect; that DOES create a sandbox order (state `created`, unauthorized).
 */

const PRODUCT_PATH = process.env.E2E_PRODUCT_PATH || '/Gutes-Leder-Kissen'
const PLACE_ORDER = process.env.E2E_PLACE_ORDER === '1'

const ADDRESS = {
  vorname: process.env.BUYER_FIRST_NAME || 'Jane',
  nachname: process.env.BUYER_LAST_NAME || 'Doe',
  strasse: process.env.BUYER_STREET || 'Strassmannstr.',
  hausnummer: process.env.BUYER_HOUSE_NO || '45',
  plz: process.env.BUYER_ZIP || '10122',
  ort: process.env.BUYER_CITY || 'Berlin',
}
const COUNTRY = process.env.BUYER_COUNTRY || 'DE'

async function dismissConsentBanner(page: Page): Promise<void> {
  const accept = page.locator('#consent-banner-btn-all')
  if (await accept.isVisible().catch(() => false)) {
    await accept.click()
    await page.waitForTimeout(500)
  }
}

async function addProductToCart(page: Page): Promise<void> {
  await page.goto(PRODUCT_PATH)
  await dismissConsentBanner(page)
  await page.locator('button[name="inWarenkorb"]').first().click()
  await page.waitForTimeout(1500)
}

/**
 * Guest checkout, step 1. The delivery-address block is shown and required on
 * this shop, so it has to be filled too; the account-password fields are
 * disabled and therefore exempt from constraint validation.
 */
async function submitGuestAddress(page: Page): Promise<void> {
  await page.goto('/Bestellvorgang')
  await page.waitForTimeout(1000)

  for (const [name, value] of Object.entries(ADDRESS)) {
    await page.fill(`input[name="${name}"]`, value)
  }
  await page.fill('input[name="email"]:not(#login_email)', `ac.good.${Date.now()}@example.com`)
  await page.selectOption('select[name="land"]', COUNTRY).catch(() => {})

  for (const [name, value] of Object.entries(ADDRESS)) {
    await page.fill(`input[name="register[shipping_address][${name}]"]`, value).catch(() => {})
  }
  await page.selectOption('select[name="register[shipping_address][land]"]', COUNTRY).catch(() => {})

  await page.locator('form button:has-text("Kundendaten abschicken")').first().click()
  await page.waitForLoadState('domcontentloaded')

  await expect(
    page.locator('input[name="Zahlungsart"]').first(),
    'reached the shipping/payment step'
  ).toBeAttached({ timeout: 20_000 })
}

/** Returns the JTL payment-method id of the first Mondu invoice method on offer. */
async function monduInvoiceMethodId(page: Page): Promise<string> {
  const config = await page.evaluate(() => (window as any).MONDU_CONFIG ?? null)
  expect(config?.payment_methods, 'MONDU_CONFIG.payment_methods is populated').toBeTruthy()

  const entry = Object.entries(config.payment_methods as Record<string, string>).find(([, name]) =>
    /rechnungskauf/i.test(name)
  )
  expect(entry, 'a Mondu invoice method is offered').toBeTruthy()

  return entry![0]
}

async function selectShippingAndMondu(page: Page, methodId: string): Promise<void> {
  await page.locator('input[name="Versandart"]').first().check({ force: true }).catch(() => {})

  // The radio sits under the plugin's clickable .mondu-card overlay, so click
  // the card the way a shopper does and let plugin.js tick the radio.
  await page.locator(`label[for="payment${methodId}"], .mondu-card:has(#payment${methodId})`).first().click()
  await expect(page.locator(`#payment${methodId}`), 'Mondu method selected').toBeChecked()

  await page.locator('form button:has-text("Weiter")').first().click()
  await page.waitForLoadState('domcontentloaded')
}

async function expectNoWidgetBootstrap(page: Page, where: string): Promise<void> {
  expect(await page.locator('script[src*="widget.js"]').count(), `${where}: no widget.js script tag`).toBe(0)
  expect(await page.locator('#mondu-checkout-widget').count(), `${where}: no widget container`).toBe(0)

  const cssMentionsWidget = await page.evaluate(() =>
    Array.from(document.styleSheets).some((sheet) => {
      try {
        return Array.from(sheet.cssRules || []).some((r) => r.cssText.includes('mondu-checkout-widget'))
      } catch {
        return false
      }
    })
  )
  expect(cssMentionsWidget, `${where}: no #mondu-checkout-widget CSS rule is served`).toBe(false)
}

test.describe('PT-4650 — widget bootstrap removed, Mondu checkout intact', () => {
  test('payment step offers Mondu with no widget bootstrap on the page', async ({ page }) => {
    await addProductToCart(page)
    await submitGuestAddress(page)

    await expectNoWidgetBootstrap(page, 'payment step')

    // The Checkout hook must survive: plugin.js reads MONDU_CONFIG.payment_methods
    // to decide whether the selected method is a Mondu one.
    const config = await page.evaluate(() => (window as any).MONDU_CONFIG ?? null)
    expect(config, 'MONDU_CONFIG is still emitted').not.toBeNull()
    expect(Object.keys(config.payment_methods).length, 'Mondu methods are listed').toBeGreaterThan(0)
    expect(config.state_flow, 'widget-only state_flow key is gone').toBeUndefined()
    expect(config.token_url, 'widget-only token_url key is gone').toBeUndefined()

    // plugin.js still drives the payment-method cards after the inert
    // _isMonduPaymentSelected() guard was dropped from init().
    const methodId = await monduInvoiceMethodId(page)
    await page.locator(`label[for="payment${methodId}"], .mondu-card:has(#payment${methodId})`).first().click()
    await expect(page.locator(`#payment${methodId}`)).toBeChecked()
    await expect(page.locator(`.mondu-card:has(#payment${methodId})`)).toHaveClass(/mondu-card-active/)
  })

  test('confirmation step hands off to hosted checkout', async ({ page }) => {
    test.skip(
      !PLACE_ORDER,
      'creates a sandbox Mondu order; run with E2E_PLACE_ORDER=1 when that is acceptable'
    )

    await addProductToCart(page)
    await submitGuestAddress(page)

    const methodId = await monduInvoiceMethodId(page)
    await selectShippingAndMondu(page, methodId)

    await page.waitForURL((u) => /mondu\.ai/.test(u.toString()), { timeout: 60_000 })
    expect(page.url(), 'redirected to Mondu hosted checkout').toMatch(/pay\.[a-z]+\.mondu\.ai|pay\.mondu\.ai/)
  })
})
