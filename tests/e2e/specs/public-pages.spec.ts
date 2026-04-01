/*!
 * Copyright © 2007-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Javascript
 * @package   Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 */

/**
 * Public Pages Tests - Tests for publicly accessible pages (no authentication required)
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';


test.describe('Public Pages', () => {

  // Public Members List
  test.describe('Public Members List', () => {

    /* TODO: Public pages must be enabled from preferences; but I had issues trying to do that.
    test('Enable public pages', async ({ loggedInPage: page }) => {
      await page.goto('/preferences');

      await page.getByRole('link', { name: 'Parameters', exact: true }).click();

      await page.getByRole('textbox', { name: 'Default', exact: true }).click();
      await page.locator('div').filter({ hasText: /^Everyone$/ }).nth(3).click();

      await page.getByRole('textbox', { name: 'Members list', exact: true }).click();
      await page.locator('div').filter({ hasText: /^Everyone$/ }).nth(3).click();

      await page.getByRole('textbox', { name: 'Staff list' }).click();
      await page.locator('.menu.transition.visible > div:nth-child(3)').click();
      await page.locator('div').filter({ hasText: 'Members list Inherit Hidden' }).nth(4).click();
      await page.getByRole('textbox', { name: 'Members gallery' }).click();
      await page.locator('div').filter({ hasText: /^Everyone$/ }).nth(5).click();
      await page.getByRole('textbox', { name: 'Staff gallery' }).click();
      await page.locator('.menu.transition.visible > div:nth-child(3)').click();

      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.ui.toast.success')).toBeVisible({ timeout: 10000 });
      await page.getByRole('link', { name: 'Log off' }).click();
    });

    test('Public - Members list accessible without auth', async ({ page }) => {
      await page.goto('/public/members/list');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      // Should display members list or appropriate message
      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

      const pageContent = await page.textContent('body');
      expect(pageContent).toBeTruthy();
    });

    test('Public - Members list has content structure', async ({ page }) => {
      await page.goto('/public/members/list');

      await page.waitForSelector('body', { timeout: 10000 });

      // Check if there's either a table, cards, or a message
      const hasTable = await page.locator('table').count() > 0;
      const hasCards = await page.locator('.ui.card, .ui.cards').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      // Should have at least one of these elements
      expect(hasTable || hasCards || hasMessage).toBeTruthy();
    });

    test('Public - Members list has title', async ({ page }) => {
      await page.goto('/public/members/list');

      const title = page.locator('h1, h2').first();
      await expect(title).toBeVisible({ timeout: 10000 });
    });
*/
  });
/*
  // Public Members Gallery (Trombinoscope)
  test.describe('Public Members Gallery', () => {

    test('Public - Members gallery accessible without auth', async ({ page }) => {
      await page.goto('/public/members/gallery');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });
    });

    test('Public - Members gallery has content', async ({ page }) => {
      await page.goto('/public/members/gallery');

      await page.waitForSelector('body', { timeout: 10000 });

      // Gallery should have cards, grid, or message
      const hasCards = await page.locator('.ui.card, .ui.cards').count() > 0;
      const hasGrid = await page.locator('.ui.grid').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasCards || hasGrid || hasMessage).toBeTruthy();
    });

    test('Public - Gallery title visible', async ({ page }) => {
      await page.goto('/public/members/gallery');

      const title = page.locator('h1, h2').first();
      await expect(title).toBeVisible({ timeout: 10000 });
    });

  });

  // Public Staff List
  test.describe('Public Staff List', () => {

    test('Public - Staff list accessible without auth', async ({ page }) => {
      await page.goto('/public/staff/list');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message, table', { timeout: 10000 });
    });

    test('Public - Staff list has structure', async ({ page }) => {
      await page.goto('/public/staff/list');

      await page.waitForSelector('body', { timeout: 10000 });

      const hasTable = await page.locator('table').count() > 0;
      const hasCards = await page.locator('.ui.card, .ui.cards').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasTable || hasCards || hasMessage).toBeTruthy();
    });

  });

  // Public Staff Gallery
  test.describe('Public Staff Gallery', () => {

    test('Public - Staff gallery accessible without auth', async ({ page }) => {
      await page.goto('/public/staff/gallery');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });
    });

    test('Public - Staff gallery has content', async ({ page }) => {
      await page.goto('/public/staff/gallery');

      await page.waitForSelector('body', { timeout: 10000 });

      const hasCards = await page.locator('.ui.card, .ui.cards').count() > 0;
      const hasGrid = await page.locator('.ui.grid').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasCards || hasGrid || hasMessage).toBeTruthy();
    });

  });

  // Public Documents
  test.describe('Public Documents', () => {

    test('Public - Documents list accessible without auth', async ({ page }) => {
      await page.goto('/public/documents');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message, table', { timeout: 10000 });
    });

    test('Public - Documents page has structure', async ({ page }) => {
      await page.goto('/public/documents');

      await page.waitForSelector('body', { timeout: 10000 });

      const hasTable = await page.locator('table').count() > 0;
      const hasList = await page.locator('.ui.list').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasTable || hasList || hasMessage).toBeTruthy();
    });

  });

  // Deprecated Routes (should redirect)
  test.describe('Deprecated Routes', () => {

    test('Public - /public/list redirects to /public/members/list', async ({ page }) => {
      await page.goto('/public/list');

      // Should redirect to the new route
      await page.waitForURL(/\/public\/members\/list/, { timeout: 10000 });
      await expect(page).toHaveURL(/\/public\/members\/list/);
    });

    test('Public - /public/trombinoscope redirects to gallery', async ({ page }) => {
      await page.goto('/public/trombinoscope');

      // Should redirect to the new gallery route
      await page.waitForURL(/\/public\/members\/gallery/, { timeout: 10000 });
      await expect(page).toHaveURL(/\/public\/members\/gallery/);
    });

  });

  // General Public Pages Tests
  test.describe('Public Pages General', () => {

    test('Public - Pages load without authentication', async ({ page }) => {
      const publicPages = [
        '/public/members/list',
        '/public/members/gallery',
        '/public/staff/list',
        '/public/staff/gallery',
        '/public/documents'
      ];

      for (const publicPage of publicPages) {
        await page.goto(publicPage);

        // Should not redirect to login
        const currentUrl = page.url();
        expect(currentUrl).not.toContain('/login');

        // Page should have content
        const bodyContent = await page.textContent('body');
        expect(bodyContent).toBeTruthy();
        expect(bodyContent!.length).toBeGreaterThan(100);
      }
    });

    test('Public - Pages have no login requirement', async ({ page }) => {
      // Navigate to public page
      await page.goto('/public/members/list');

      // Should not show login form
      // If the page IS the login page, that means we got redirected (bad)
      const isLoginPage = await page.locator('input[name="login"]').count() > 0;

      expect(isLoginPage).toBe(false);
    });

  });*/

  /* see Enable public pages
  test('Disable public pages', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');

    await page.getByRole('link', { name: 'Parameters', exact: true }).click();
    await page.getByRole('textbox', { name: 'Default', exact: true }).click();
    await page.getByText('Up to date members').nth(1).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('.ui.toast.success')).toBeVisible({ timeout: 10000 });
    await page.getByRole('link', { name: 'Log off' }).click();
  });*/
});


