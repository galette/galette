/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Accessibility Tests for Public Pages (Anonymous Mode)
 *
 * Tests WCAG 2.1 A/AA compliance on public pages without authentication.
 * Pages are configured with PUBLIC visibility to allow anonymous access.
 */

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import {
  PreferencesHelper,
  PUBLIC_PAGES_VISIBILITY,
} from '../helpers/preferences';

test.describe('Accessibility - Public Pages (Anonymous)', () => {

  // Configure public pages with PUBLIC visibility before tests
  test.beforeAll(async ({ browser }) => {
    // Use a temporary context to configure preferences
    const context = await browser.newContext();
    const page = await context.newPage();

    // Login as admin to configure
    await page.goto('/login');
    await page.locator('input#login').fill(process.env.E2E_ADMIN_USER ?? 'admin');
    await page.locator('input#password').fill(process.env.E2E_ADMIN_PASS ?? 'admin');
    await page.locator('input[type="submit"]').click();
    await page.waitForURL('/dashboard');

    // Enable public pages with PUBLIC visibility (no auth required)
    await PreferencesHelper.enablePublicPages(page, PUBLIC_PAGES_VISIBILITY.PUBLIC);

    await context.close();
  });

  // Restore default configuration after all tests
  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto('/login');
    await page.locator('input#login').fill(process.env.E2E_ADMIN_USER ?? 'admin');
    await page.locator('input#password').fill(process.env.E2E_ADMIN_PASS ?? 'admin');
    await page.locator('input[type="submit"]').click();
    await page.waitForURL('/dashboard');

    await PreferencesHelper.restoreDefaultPublicPages(page);

    await context.close();
  });

  // Public Members List - Anonymous
  base('A11y - Public members list (anonymous)', async ({ page }) => {
    await page.goto('/public/members/list');
    await page.waitForSelector('h1, h2, table, .ui.message', { timeout: 10000 });

    // Verify we're not on login page
    const url = page.url();
    expect(url).not.toContain('/login');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Public Members Gallery - Anonymous
  base('A11y - Public members gallery (anonymous)', async ({ page }) => {
    await page.goto('/public/members/gallery');
    await page.waitForSelector('h1, h2, .ui.card, .ui.cards, .ui.message', { timeout: 10000 });

    const url = page.url();
    expect(url).not.toContain('/login');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Public Staff List - Anonymous
  base('A11y - Public staff list (anonymous)', async ({ page }) => {
    await page.goto('/public/staff/list');
    await page.waitForSelector('h1, h2, table, .ui.message', { timeout: 10000 });

    const url = page.url();
    expect(url).not.toContain('/login');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Public Staff Gallery - Anonymous
  base('A11y - Public staff gallery (anonymous)', async ({ page }) => {
    await page.goto('/public/staff/gallery');
    await page.waitForSelector('h1, h2, .ui.card, .ui.cards, .ui.message', { timeout: 10000 });

    const url = page.url();
    expect(url).not.toContain('/login');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Public Documents - Anonymous
  base('A11y - Public documents list (anonymous)', async ({ page }) => {
    await page.goto('/public/documents');
    await page.waitForSelector('h1, h2, table, .ui.list, .ui.message', { timeout: 10000 });

    const url = page.url();
    expect(url).not.toContain('/login');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Test deprecated routes accessibility (they redirect)
  base('A11y - Deprecated route /public/list (anonymous)', async ({ page }) => {
    await page.goto('/public/list');
    await page.waitForSelector('h1, h2, table, .ui.message', { timeout: 10000 });

    // After redirect, should be on members list
    const url = page.url();
    expect(url).toContain('/public/members/list');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  base('A11y - Deprecated route /public/trombinoscope (anonymous)', async ({ page }) => {
    await page.goto('/public/trombinoscope');
    await page.waitForSelector('h1, h2, .ui.card, .ui.cards, .ui.message', { timeout: 10000 });

    // After redirect, should be on members gallery
    const url = page.url();
    expect(url).toContain('/public/members/gallery');

    const results = await axeBuilder(page).analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Navigation accessibility on public pages
  base('A11y - Public page navigation (anonymous)', async ({ page }) => {
    await page.goto('/public/members/list');
    await page.waitForSelector('body', { timeout: 10000 });

    // Test that navigation elements are accessible
    const results = await axeBuilder(page)
      .include('nav, [role="navigation"], header')
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  // Keyboard navigation on public pages
  base('A11y - Public page keyboard navigation (anonymous)', async ({ page }) => {
    await page.goto('/public/members/list#main-content');
    await page.waitForSelector('body', { timeout: 10000 });

    // Test keyboard navigation
    //await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');

    const focused = await page.evaluate(() => document.activeElement?.tagName);
    // Should be able to focus on interactive elements
    expect(['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'DIV']).toContain(focused);
  });

  // Color contrast on public pages
  base('A11y - Public page color contrast (anonymous)', async ({ page }) => {
    await page.goto('/public/members/list');
    await page.waitForSelector('body', { timeout: 10000 });

    const results = await axeBuilder(page).include('body').analyze();
    const contrastViolations = results.violations.filter(v => v.id.includes('contrast'));

    expect(contrastViolations, formatViolations(contrastViolations)).toEqual([]);
  });

  // Test that public pages have proper headings
  base('A11y - Public page heading structure (anonymous)', async ({ page }) => {
    await page.goto('/public/members/list');
    await page.waitForSelector('h1, h2', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    const headingViolations = results.violations.filter(v =>
      v.id.includes('heading') || v.id.includes('landmark')
    );

    expect(headingViolations, formatViolations(headingViolations)).toEqual([]);
  });

  // Test alternative texts on public gallery
  base('A11y - Public gallery images alt text (anonymous)', async ({ page }) => {
    await page.goto('/public/members/gallery');
    await page.waitForSelector('body', { timeout: 10000 });

    const results = await axeBuilder(page).analyze();
    const imageViolations = results.violations.filter(v =>
      v.id.includes('image-alt') || v.id.includes('img')
    );

    expect(imageViolations, formatViolations(imageViolations)).toEqual([]);
  });

});

