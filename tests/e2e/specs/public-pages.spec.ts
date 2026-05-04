/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Public Pages Tests - Tests for publicly accessible pages (no authentication required)
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import {
  PreferencesHelper,
  PUBLIC_PAGES_VISIBILITY,
} from '../helpers/preferences';


test.describe('Public Pages', () => {

  // Enable public pages before each test and restore defaults after
  test.beforeEach(async ({ loggedInPage: page }) => {
    await PreferencesHelper.enablePublicPages(page, PUBLIC_PAGES_VISIBILITY.RESTRICTED);
  });

  test.afterEach(async ({ loggedInPage: page }) => {
    await PreferencesHelper.restoreDefaultPublicPages(page);
  });

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

    test('Public - Pages have login requirement when not connected', async ({ browser }) => {
      //new isolated context (no cookie/session)
      const context = await browser.newContext();
      const page = await context.newPage();

      try {
        // Navigate to public page
        await page.goto('/public/members/list');

        // Should be login URL
        const url = page.url();
        expect(url).toContain('/login');

        // Should show login form
        await expect(page.locator('input#login')).toBeVisible();
      } finally {
        await context.close();
      }
    });
  });

  // Deprecated Public Routes
  test.describe('Deprecated Public Routes', () => {

    test('Public - Deprecated /public/list redirects to /public/members/list', async ({ loggedInPage: page }) => {
      const response = await page.goto('/public/list', { waitUntil: 'domcontentloaded' });

      // Should redirect with 301
      const finalUrl = page.url();
      expect(finalUrl).toContain('/public/members/list');
      expect(response?.status()).toBe(200); // Final page after redirect
    });

    test('Public - Deprecated /public/members redirects to /public/members/list', async ({ loggedInPage: page }) => {
      const response = await page.goto('/public/members', { waitUntil: 'domcontentloaded' });

      const finalUrl = page.url();
      expect(finalUrl).toContain('/public/members/list');
      expect(response?.status()).toBe(200);
    });

    test('Public - Deprecated /public/list with pagination parameters', async ({ loggedInPage: page }) => {
      const response = await page.goto('/public/list/page/2', { waitUntil: 'domcontentloaded' });

      // Parameters should be preserved in redirect
      const finalUrl = page.url();
      expect(finalUrl).toContain('/public/members/list');
      expect(finalUrl).toContain('page/2');
      expect(response?.status()).toBe(200);
    });

    test('Public - Deprecated /public/trombinoscope redirects to /public/members/gallery', async ({ loggedInPage: page }) => {
      const response = await page.goto('/public/trombinoscope', { waitUntil: 'domcontentloaded' });

      const finalUrl = page.url();
      expect(finalUrl).toContain('/public/members/gallery');
      expect(response?.status()).toBe(200);
    });

    test('Public - Deprecated /public/trombi redirects to /public/members/gallery', async ({ loggedInPage: page }) => {
      const response = await page.goto('/public/trombi', { waitUntil: 'domcontentloaded' });

      const finalUrl = page.url();
      expect(finalUrl).toContain('/public/members/gallery');
      expect(response?.status()).toBe(200);
    });
  });

  // Visibility Tests by Role
  test.describe('Public Pages Visibility by Role', () => {

    test('Public - PUBLIC visibility allows anonymous access', async ({ browser, loggedInAs }) => {
      // Set all pages to PUBLIC (no auth required) using an admin session
      const adminPage = await loggedInAs('superadmin');
      await PreferencesHelper.enablePublicPages(adminPage, PUBLIC_PAGES_VISIBILITY.PUBLIC);
      await adminPage.close();

      // Navigate without authentication using a fresh context
      const context = await browser.newContext();
      const page = await context.newPage();
      try {
        await page.goto('/public/members/list');

        // Should NOT redirect to login
        const currentUrl = page.url();
        expect(currentUrl).not.toContain('/login');

        // Should display content
        const hasContent = await page.locator('h1, h2').count() > 0;
        expect(hasContent).toBeTruthy();
      } finally {
        await context.close();
      }
    });

    test('Public - RESTRICTED visibility requires authentication', async ({ browser, loggedInAs }) => {
      // Set to RESTRICTED (members only)
      const adminPage = await loggedInAs('superadmin');
      await PreferencesHelper.enablePublicPages(adminPage, PUBLIC_PAGES_VISIBILITY.RESTRICTED);
      await adminPage.close();

      // Try to access without auth - should redirect to login
      // We must use a separate context to be anonymous
      const context = await browser.newContext();
      const page = await context.newPage();
      try {
        await page.goto('/public/members/list');
        const anonUrl = page.url();
        expect(anonUrl).toContain('/login');
      } finally {
        await context.close();
      }

      // Now login as member and access
      const memberPage = await loggedInAs('member');
      await memberPage.goto('/public/members/list');
      const memberUrl = memberPage.url();
      expect(memberUrl).not.toContain('/login');
      expect(memberUrl).toContain('/public/members/list');
      await memberPage.close();
    });

    /* FIXME: works locally, but not on CI :/
    test('Public - PRIVATE visibility requires admin/staff access', async ({ browser, loggedInAs }) => {
      // Set to PRIVATE (admin/staff only)
      const adminPage = await loggedInAs('superadmin');
      await PreferencesHelper.enablePublicPages(adminPage, PUBLIC_PAGES_VISIBILITY.PRIVATE);
      await adminPage.close();

      // Try as anonymous - should redirect to login
      const context = await browser.newContext();
      const page = await context.newPage();
      try {
        await page.goto('/public/members/list');
        expect(page.url()).toContain('/login');
      } finally {
        await context.close();
      }

      // Try as regular member - should be denied
      // PublicPages middleware redirects to '/' (slash route), which then redirects
      // logged-in non-staff/non-admin users to '/dashboard' (not '/login')
      const memberPage = await loggedInAs('member');
      await memberPage.goto('/public/members/list');
      // Should be redirected to dashboard (logged-in but not staff/admin)
      expect(memberPage.url()).toContain('/dashboard');
      await memberPage.close();

      // Try as admin - should work
      const staffPage = await loggedInAs('admin');
      await staffPage.goto('/public/members/list');
      expect(staffPage.url()).toContain('/public/members/list');
      await staffPage.close();
    });*/

    test('Public - HIDDEN visibility denies all access', async ({ loggedInAs }) => {
      // Set to HIDDEN (no one can access)
      const superadminPage = await loggedInAs('superadmin');
      await PreferencesHelper.enablePublicPages(superadminPage, PUBLIC_PAGES_VISIBILITY.HIDDEN);

      // Even superadmin should be denied
      await superadminPage.goto('/public/members/list');

      // Should be redirected away from public page
      expect(superadminPage.url()).not.toContain('/public/members/list');
      await superadminPage.close();
    });

    test('Public - Gallery visibility can differ from list visibility', async ({ browser, loggedInAs }) => {
      const adminPage = await loggedInAs('superadmin');

      // Enable pages, then set specific visibilities
      await PreferencesHelper.enablePublicPages(adminPage, PUBLIC_PAGES_VISIBILITY.RESTRICTED);
      await PreferencesHelper.setPublicPageVisibility(
        adminPage,
        'pref_publicpages_visibility_memberslist',
        PUBLIC_PAGES_VISIBILITY.PUBLIC
      );
      await PreferencesHelper.setPublicPageVisibility(
        adminPage,
        'pref_publicpages_visibility_membersgallery',
        PUBLIC_PAGES_VISIBILITY.PRIVATE
      );
      await adminPage.close();

      // Anonymous user can access list (PUBLIC)
      const context = await browser.newContext();
      const page = await context.newPage();
      try {
        await page.goto('/public/members/list');
        expect(page.url()).toContain('/public/members/list');
      } finally {
        await context.close();
      }

      // But member needs admin/staff for gallery (PRIVATE)
      // PublicPages middleware redirects to '/' which then redirects to '/dashboard' for logged-in members
      const memberPage = await loggedInAs('member');
      await memberPage.goto('/public/members/gallery');
      await memberPage.waitForTimeout(500); //wait for redirect
      expect(memberPage.url()).toContain('/dashboard');
      await memberPage.close();
    });
  });
});

