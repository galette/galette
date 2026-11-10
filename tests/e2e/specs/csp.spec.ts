/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Content Security Policy tests
 *
 * The complete policy is sent report-only: a page breaking it still works,
 * nothing but the console tells. Browsers fire a securitypolicyviolation
 * event for each violation, enforced or not; pages are browsed while
 * listening to it, any violation fails the test.
 */

import { test as base, expect, Page } from '@playwright/test';
import { logIn, test } from '../fixtures/auth.fixture';
import { enableDarkMode } from '../fixtures/a11y.fixture';

type Violation = {
  directive: string;
  blocked: string;
  source: string;
  line: number;
  sample: string;
};

declare global {
  interface Window {
    __cspViolations: Violation[];
  }
}

/**
 * Record violations of every document the page loads
 */
async function listenViolations(page: Page): Promise<void> {
  await page.addInitScript(() => {
    window.__cspViolations = [];
    document.addEventListener('securitypolicyviolation', (event) => {
      window.__cspViolations.push({
        directive: event.effectiveDirective,
        blocked: event.blockedURI,
        source: event.sourceFile,
        line: event.lineNumber,
        sample: event.sample,
      });
    });
  });
}

/**
 * Get violations of the current document
 *
 * Waits for the network to settle first: scripts loaded late, fetch calls and
 * images are checked when they are requested.
 */
async function violations(page: Page): Promise<Violation[]> {
  await page.waitForLoadState('networkidle');
  return page.evaluate(() => window.__cspViolations ?? []);
}

/**
 * Format violations for an assertion message
 */
function format(found: Violation[]): string {
  return found
    .map((v) => `${v.directive} blocked ${v.blocked || '(inline)'} (from ${v.source}:${v.line}) ${v.sample}`)
    .join('\n');
}

/**
 * Browse pages, and check none breaks the policy
 */
async function expectNoViolation(page: Page, urls: string[], status = 200): Promise<void> {
  for (const url of urls) {
    const response = await page.goto(url);
    expect(response?.status(), url).toBe(status);
    const found = await violations(page);
    expect(found, `${url}\n${format(found)}`).toEqual([]);
  }
}

test.describe('Content Security Policy', () => {

  base('Policy headers carry the nonce of page scripts', async ({ page }) => {
    const response = await page.goto('/login');
    const headers = response!.headers();

    expect(headers['content-security-policy']).toContain("frame-ancestors 'self'");
    expect(headers['content-security-policy']).toContain("object-src 'none'");
    expect(headers['x-content-type-options']).toBe('nosniff');

    const policy = headers['content-security-policy-report-only'];
    const nonce = policy.match(/'nonce-([^']+)'/)?.[1];
    expect(nonce).toBeTruthy();

    // browsers hide the nonce attribute value from the DOM, but not the property
    const nonces = await page.locator('script').evaluateAll(
      (scripts) => scripts.map((script) => (script as HTMLScriptElement).nonce)
    );
    expect(nonces.length).toBeGreaterThan(0);
    expect(nonces.every((value) => value === nonce)).toBe(true);
  });

  base('Violations are detected', async ({ page }) => {
    // check the listener catches what it is meant to, or every other test would pass for nothing
    await listenViolations(page);
    await page.goto('/login');
    await page.evaluate(() => {
      const frame = document.createElement('iframe');
      frame.src = 'https://galette.invalid/';
      document.body.appendChild(frame);
      fetch('https://galette.invalid/exfiltrate').catch(() => null);
    });

    // report-only: the requests are still sent, do not wait for them to settle
    await expect.poll(async () => {
      const found = await page.evaluate(() => window.__cspViolations);
      return [...new Set(found.map((v) => v.directive))].sort();
    })
      .toEqual(['connect-src', 'frame-src']);
  });

  base('Public pages', async ({ page }) => {
    await listenViolations(page);
    await expectNoViolation(page, [
      '/login',
      '/public/members/list',
      '/public/members/gallery',
      '/public/staff/list',
      '/public/documents',
    ]);
    await expectNoViolation(page, ['/invalid-url-for-404'], 404);
  });

  test('Administration pages', async ({ loggedInPage: page }) => {
    // a page load each, waiting for the network to settle
    test.setTimeout(90000);
    await listenViolations(page);
    await expectNoViolation(page, [
      '/dashboard',
      '/members',
      '/member/me',
      '/member/add',
      '/advanced-search',
      '/saved-searches',
      '/groups',
      '/contributions',
      '/contribution/fee/add',
      '/transactions',
      '/transaction/add',
      '/scheduled-payments',
      '/contributions-types',
      '/payment-types',
      '/reminders',
      '/mailings',
      '/history',
      '/charts',
      '/documents',
      '/document/add',
      '/export',
      '/import',
      '/import/model',
      '/preferences',
      '/preferences?tab=rights',
      '/fields/core/configure',
      '/fields/dynamic/configure',
      '/lists/adherents/configure',
      '/titles',
      '/status',
      '/roles',
      '/plugins',
      '/two-factor/manage',
    ]);
  });

  test('Interactive widgets', async ({ loggedInPage: page }) => {
    await listenViolations(page);

    // dropdowns, search and modals of the members list
    await page.goto('/members');
    await page.locator('#filter_str').first().fill('sky');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('load');
    expect(await violations(page), format(await violations(page))).toEqual([]);

    // HTML editor, as on PDF models; mailings need emailing enabled, fixtures do not
    await page.goto('/models/pdf');
    await expect(page.locator('.note-editor').first()).toBeVisible();
    await page.locator('.note-editor .btn-codeview').first().click();
    await expect(page.locator('.note-editor.codeview').first()).toBeVisible();
    const found = await violations(page);
    expect(found, format(found)).toEqual([]);
  });

  base('Dark mode', async ({ browser }) => {
    // own context: dark mode is a cookie, other tests must not get it
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      await listenViolations(page);
      await logIn(page, 'superadmin');
      // reloaded: the weak password warning shown on login covers the user menu
      await page.goto('/dashboard');

      await enableDarkMode(page);
      await expectNoViolation(page, ['/dashboard', '/members', '/charts']);
    } finally {
      await context.close();
    }
  });
});
