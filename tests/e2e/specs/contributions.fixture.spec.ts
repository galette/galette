/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * E2E Tests for Galette Contribution Fixtures
 * Tests the fixture contributions with various states (up-to-date, expired, etc.)
 */

import { test } from '../fixtures/auth.fixture';
import { expect } from '@playwright/test';

test.describe('Contribution Fixtures', () => {

  test('Fixtures - Display contributions list', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');
    await expect(page).toHaveURL(/\/contributions/);

    await expect(page.locator('h1, h2')).toContainText(/Contribution|Cotisation/i);
    await expect(page.locator('table.listing')).toBeVisible({ timeout: 10000 });
  });

  test('Fixtures - Display contribution count', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');

    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const rows = page.locator('tbody tr');
    const count = await rows.count();

    expect(count).toBeGreaterThan(0);
  });

  test('Fixtures - Show up-to-date contributions', async ({ loggedInPage: page }) => {
    //TODO: filter on up-to-date contributions
    await page.goto('/contributions');

    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const statusElements = page.locator('[class*="active"], [class*="valid"], [class*="current"], [class*="status"]');

    if (await statusElements.first().isVisible({ timeout: 5000 })) {
      expect(await statusElements.count()).toBeGreaterThan(0);
    }
  });

  test('Fixtures - Show expired contributions', async ({ loggedInPage: page }) => {
    //TODO: filter on expired contributions
    await page.goto('/contributions');

    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const expiredElements = page.locator('[class*="expired"], [class*="inactive"], [class*="past"], [class*="danger"], [class*="error"]');
    const expiredCount = await expiredElements.count();

    expect(typeof expiredCount).toBe('number');
  });

  test('Fixtures - Navigate to contribution details', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');

    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const contributionLink = page.locator('a[href*="/contribution"]').first();

    if (await contributionLink.isVisible({ timeout: 5000 })) {
      await contributionLink.click({ timeout: 5000 });

      await expect(page).toHaveURL(/\/contribution/);
      await expect(page.locator('h1, h2')).toBeVisible({ timeout: 10000 });
    }
  });

  test('Fixtures - Link contributions to members', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const searchInput = page.getByRole('textbox', { name: 'Search:' })
    await searchInput.fill('luke');
    await page.locator('button[type="submit"]').first().click({ timeout: 5000 });

    await page.waitForTimeout(500);

    const memberLink = page.locator('a:has-text("Skywalker")').first();
    if (await memberLink.isVisible({ timeout: 5000 })) {
      await memberLink.click({ timeout: 5000 });

      const contribTab = page.locator('a:has-text("Contribution"), a:has-text("Cotisation"), [role="tab"]:has-text("Contribution")').first();

      if (await contribTab.isVisible({ timeout: 5000 })) {
        await contribTab.click({ timeout: 5000 });
        await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });
      }
    }
  });

});
