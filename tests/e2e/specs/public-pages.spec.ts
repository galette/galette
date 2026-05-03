/**
 * Copyright © 2003-2026 The Galette Team
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
 */

/**
 * Public Pages Tests - Tests for publicly accessible pages (no authentication required)
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';


test.describe('Public Pages', () => {

  // Public Members List
  test.describe('Public Members List', () => {
    test('Public - Members list accessible limited to up-to-date members', async ({ loggedInPage: page }) => {
      await page.goto('/public/members/list');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      // Should display members list or appropriate message
      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });

      const pageContent = await page.textContent('body');
      expect(pageContent).toBeTruthy();
    });

    test('Public - Members list has content structure', async ({ loggedInPage: page }) => {
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
  });

  // Public Members Gallery (Trombinoscope)
  test.describe('Public Members Gallery', () => {

    test('Public - Members gallery accessible limited to up-to-date members', async ({ loggedInPage: page }) => {
      await page.goto('/public/members/gallery');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });
    });

    test('Public - Members gallery has content', async ({ loggedInPage: page }) => {
      await page.goto('/public/members/gallery');

      await page.waitForSelector('body', { timeout: 10000 });

      // Gallery should have cards, grid, or message
      const hasCards = await page.locator('.ui.card, .ui.cards').count() > 0;
      const hasGrid = await page.locator('.ui.grid').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasCards || hasGrid || hasMessage).toBeTruthy();
    });

    test('Public - Gallery title visible', async ({ loggedInPage: page }) => {
      await page.goto('/public/members/gallery');

      const title = page.locator('h1, h2').first();
      await expect(title).toBeVisible({ timeout: 10000 });
    });

  });

  // Public Staff List
  test.describe('Public Staff List', () => {

    test('Public - Staff list accessible limited to up-to-date members', async ({ loggedInPage: page }) => {
      await page.goto('/public/staff/list');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message, table', { timeout: 10000 });
    });

    test('Public - Staff list has structure', async ({ loggedInPage: page }) => {
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

    test('Public - Staff gallery accessible limited to up-to-date members', async ({ loggedInPage: page }) => {
      await page.goto('/public/staff/gallery');

      // Should not redirect to login
      await expect(page).not.toHaveURL(/\/login/);

      await page.waitForSelector('h1, h2, .ui.message', { timeout: 10000 });
    });

    test('Public - Staff gallery has content', async ({ loggedInPage: page }) => {
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

    test('Public - Documents list accessible limited to up-to-date members', async ({ loggedInPage: page }) => {
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

  // General Public Pages Tests
  test.describe('Public Pages General', () => {

    test('Public - Pages load limited to up-to-date members', async ({ loggedInPage: page }) => {
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

    test('Public - Pages have no login requirement for up-to-date member', async ({ loggedInPage: page }) => {
      // Navigate to public page
      await page.goto('/public/members/list');

      // Should not show login form
      // If the page IS the login page, that means we got redirected (bad)
      const isLoginPage = await page.locator('input[name="login"]').count() > 0;

      expect(isLoginPage).toBe(false);
    });

    test('Public - Pages have login requirement when not connected', async ({ page }) => {
      // Navigate to public page
      await page.goto('/public/members/list');

      // Should show login form
      const isLoginPage = await page.locator('input[name="login"]').count() > 0;
      expect(isLoginPage).toBe(true);
    });
  });
});


