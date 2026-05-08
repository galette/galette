/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Accessibility Tests - WCAG 2.1 A/AA + RGAA compliance using axe-core
 */

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import { MemberListPage } from '../pages/MemberListPage';

let animationTimeout = 500;

test.describe('Accessibility', () => {

  // Public Pages
  base('A11y - Login page', async ({ page }) => {
    await page.goto('/login');
    await page.locator('form.ui.form').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  base('A11y - 404 page', async ({ page }) => {
    await page.goto('/invalid-url');
    await page.locator('h1').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  base('A11y - compatibility checks page', async ({ page }) => {
    await page.goto('/compat_test.php');
    await page.locator('h1').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  base('A11y - installer page', async ({ page }) => {
    await page.goto('/installer.php');
    await page.locator('h1').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Dashboard
  test('A11y - Dashboard page', async ({ loggedInPage: page }) => {
    await page.goto('/dashboard');
    await page.locator('#main-activities').waitFor({ state: 'visible' });

    // Dismiss warning toast to avoid flakiness from animation-phase contrast readings
    const warningToast = page.locator('.ui.toast.warning');
    if (await warningToast.isVisible()) {
      await page.locator('.ui.toast.warning i[role="button"]').click();
      await warningToast.waitFor({ state: 'hidden' });
    }

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Members
  test('A11y - Members list page', async ({ loggedInPage: page }) => {
    const listPage = new MemberListPage(page);
    await listPage.goto();
    await listPage.memberTable.waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Member details page', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    const searchInput = page.locator('#filter_str').first();
    await searchInput.fill('luke');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForTimeout(animationTimeout);

    const memberLink = page.locator('a:has-text("Skywalker")').first();
    await memberLink.click();
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Member add form', async ({ loggedInPage: page }) => {
    await page.goto('/member/add');
    await page.locator('#nom_adh').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Member advanced search', async ({ loggedInPage: page }) => {
    await page.goto('/advanced-search');
    await page.locator('#filter_str').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Members search form', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    const searchInput = page.locator('#filter_str');
    await searchInput.waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').include('form, [role="search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Contributions
  test('A11y - Contributions list page', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Contribution details page', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const firstContribution = page.locator('a[href*="/contribution"]').first();
    if (await firstContribution.isVisible()) {
      await firstContribution.click();
      await page.locator('h1, h2').waitFor({ state: 'visible' });

      const results = await axeBuilder(page).analyze();
      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    }
  });

  test('A11y - Contribution add form', async ({ loggedInPage: page }) => {
    // Navigate via member's contribution page to pre-select member
    const listPage = new MemberListPage(page);
    await listPage.goto();
    await listPage.filterByName('SKYWALKER Luke');
    await listPage.getMemberRowByName('SKYWALKER Luke')
      .locator('a:has(i.receipt.green.icon)')
      .click();
    await page.locator('form a[href*="/contribution/fee/add"]').click();
    await page.locator('#montant_cotis').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Scheduled payments
  test('A11y - Scheduled payments list page', async ({ loggedInPage: page }) => {
    await page.goto('/scheduled-payments');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Transactions
  test('A11y - Transaction list page', async ({ loggedInPage: page }) => {
    await page.goto('/transactions');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Transaction details page', async ({ loggedInPage: page }) => {
    await page.goto('/transactions');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const firstTransaction = page.locator('a[href*="/transaction"]').first();
    if (await firstTransaction.isVisible()) {
      await firstTransaction.click();
      await page.locator('h1, h2').waitFor({ state: 'visible' });

      const results = await axeBuilder(page).analyze();
      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    }
  });

  test('A11y - Transaction add form', async ({ loggedInPage: page }) => {
    await page.goto('/transaction/add');
    await page.locator('#trans_amount').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Reminders', async ({ loggedInPage: page }) => {
    await page.goto('/reminders');
    await page.locator('#send_reminders').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Groups
  test('A11y - Groups list page', async ({ loggedInPage: page }) => {
    await page.goto('/groups');
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Group details page', async ({ loggedInPage: page }) => {
    await page.goto('/groups');
    const groupRows = page.locator('table.listing tbody tr');
    const count = await groupRows.count();

    if (count > 0) {
      // Navigate to first group's edit page
      await groupRows.first().locator('a[href*="/group/edit/"]').click();
      await page.locator('h1, h2').waitFor({ state: 'visible' });
      await page.waitForTimeout(animationTimeout);

      const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    }
  });

  // Documents
  test('A11y - Documents list page', async ({ loggedInPage: page }) => {
    await page.goto('/documents');
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Documents add page', async ({ loggedInPage: page }) => {
    await page.goto('/document/add');
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page)
      .exclude('*[autocomplete="fomantic-search"]')
      .exclude('.note-editable') // Summernote related issue
      .exclude('.note-resizebar') //Summernote issue
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Charts
  test('A11y - Charts', async ({ loggedInPage: page }) => {
    await page.goto('/charts');
    await page.locator('h1').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // History
  test('A11y - History', async ({ loggedInPage: page }) => {
    await page.goto('/history');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Configuration
  test('A11y - Preferences page', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');
    await page.locator('form.ui.form').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Plugins page', async ({ loggedInPage: page }) => {
    await page.goto('/plugins');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Core list configuration page', async ({ loggedInPage: page }) => {
    await page.goto('/lists/adherents/configure');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).exclude('*[autocomplete="fomantic-search"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Core fields configuration page', async ({ loggedInPage: page }) => {
    await page.goto('/fields/core/configure');
    await page.locator('#sortable_categories').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Dynamic fields configuration page', async ({ loggedInPage: page }) => {
    await page.goto('/fields/dynamic/configure');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Dynamic fields add', async ({ loggedInPage: page }) => {
    await page.goto('/fields/dynamic/configure');
    await page.locator('table.listing').waitFor({ state: 'visible' });

    await page.getByRole('link', { name: 'Add' }).click();
    await page.getByRole('textbox', { name: 'Field name' }).waitFor({ state: 'visible' });
    await page.waitForTimeout(animationTimeout);

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Contribution types page', async ({ loggedInPage: page }) => {
    await page.goto('/contributions-types');
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page)
      .exclude('*[autocomplete="fomantic-search"]')
      .exclude('.note-editable') // Summernote related issue
      .exclude('.note-resizebar') //Summernote issue
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Payment types page', async ({ loggedInPage: page }) => {
    await page.goto('/payment-types');
    await page.locator('h1, h2').waitFor({ state: 'visible' });

    const results = await axeBuilder(page)
      .exclude('*[autocomplete="fomantic-search"]')
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Titles page', async ({ loggedInPage: page }) => {
    await page.goto('/titles');
    await page.waitForSelector('form, table', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Status page', async ({ loggedInPage: page }) => {
    await page.goto('/status');
    await page.waitForSelector('form, table', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Dynamic translations page', async ({ loggedInPage: page }) => {
    await page.goto('/dynamic-translations?text_orig=President');
    await page.waitForSelector('form, table', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Import/Export
  test('A11y - Export page', async ({ loggedInPage: page }) => {
    await page.goto('/export');
    await page.locator('form').waitFor({ state: 'visible' });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Import page', async ({ loggedInPage: page }) => {
    await page.goto('/import');
    await page.waitForSelector('form, h1', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Import model configuration page', async ({ loggedInPage: page }) => {
    await page.goto('/import/model');
    await page.waitForSelector('a[href*="/import/model/get"]', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Import model change page', async ({ loggedInPage: page }) => {
    await page.goto('/import/model?tab=change');
    await page.waitForSelector('#store-model', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Public Pages - by default, limited to up-to-date members.
  test('A11y - Public members list', async ({ loggedInPage: page }) => {
    await page.goto('/public/members/list');
    await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Public members gallery', async ({ loggedInPage: page }) => {
    await page.goto('/public/members/gallery');
    await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Public staff list', async ({ loggedInPage: page }) => {
    await page.goto('/public/staff/list');
    await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Public staff gallery', async ({ loggedInPage: page }) => {
    await page.goto('/public/staff/gallery');
    await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Public documents list', async ({ loggedInPage: page }) => {
    await page.goto('/public/documents');
    await page.waitForSelector('.ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Mailings
  // FIXME: requires mail method to be setup
  /*test('A11y - Mailings form', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('h1, h2, form', { timeout: 10000 });

      const results = await axeBuilder(page).analyze();
      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    }
  });*/

  // Search
  test('A11y - Saved searches list', async ({ loggedInPage: page }) => {
    await page.goto('/saved-searches');
    await page.waitForSelector('h1, h2, table, .ui.message', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Navigation
  test('A11y - Main navigation', async ({ loggedInPage: page }) => {
    await page.goto('/dashboard');
    const nav = page.locator('#sidemenu nav, [role="navigation"]');
    await nav.first().waitFor({ state: 'visible' });

    const results = await axeBuilder(page).include('nav, [role="navigation"]').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('A11y - Keyboard navigation', async ({ loggedInPage: page }) => {
    await page.goto('/dashboard');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');

    const focused = await page.evaluate(() => document.activeElement?.tagName);
    expect(['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA']).toContain(focused);
  });

  // Color Contrast
  test('A11y - Color contrast on members page', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    const results = await axeBuilder(page).include('body').analyze();
    const contrastViolations = results.violations.filter(v => v.id.includes('contrast'));

    expect(contrastViolations, formatViolations(contrastViolations)).toEqual([]);
  });

  // Form Labels
  test('A11y - Search form labels', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    const inputs = page.locator('input[type="text"], input[type="search"], input[type="email"]');
    const inputCount = await inputs.count();

    for (let i = 0; i < Math.min(inputCount, 3); i++) {
      const input = inputs.nth(i);
      const ariaLabel = await input.getAttribute('aria-label');
      const placeholder = await input.getAttribute('placeholder');
      const id = await input.getAttribute('id');
      const hasLabel = ariaLabel || placeholder || id;
      expect(hasLabel).toBeTruthy();
    }
  });
});
