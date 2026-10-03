/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Preferences Tests - Application settings
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

test.describe('Preferences', () => {

  test('Preferences - Display preferences page', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');

    await expect(page).toHaveURL(/\/preferences/);
    await expect(page.locator('h1, h2')).toContainText(/Préférences|Preferences|Settings/i);
    await expect(page.locator('form.ui.form')).toBeVisible({ timeout: 10000 });
  });

  test('Preferences - Form has settings fields', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');

    await page.locator('form.ui.form').waitFor({ state: 'visible', timeout: 10000 });

    // Check for common preference fields
    const fields = page.locator('input, select, textarea');
    const fieldCount = await fields.count();

    // Should have multiple settings fields
    expect(fieldCount).toBeGreaterThan(5);
  });

  test('Preferences - Has save button', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');

    await page.locator('form.ui.form').waitFor({ state: 'visible', timeout: 10000 });

    const saveButton = page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Enregistrer")').first();
    await expect(saveButton).toBeVisible({ timeout: 5000 });
  });

  test('Preferences - Verify form structure', async ({ loggedInPage: page }) => {
    await page.goto('/preferences');

    await page.locator('form.ui.form').waitFor({ state: 'visible', timeout: 10000 });

    // Check if the form has sections/tabs
    const hasTabs = await page.locator('.ui.tabular.menu, .ui.tab').count();
    const hasSegments = await page.locator('.ui.segment').count();

    // Form should be organized in tabs or segments
    expect(hasTabs + hasSegments).toBeGreaterThan(0);
  });

});

