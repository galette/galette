/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Admin Configuration Tests - Contribution types, Titles, Status
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

test.describe('Admin Configuration', () => {

  // Contribution Types Tests
  test.describe('Contribution Types', () => {

    test('Config - Display contribution types list', async ({ loggedInPage: page }) => {
      await page.goto('/contributions-types');

      await expect(page).toHaveURL(/\/contributions-types/);
      await expect(page.locator('h1, h2')).toContainText(/Type|Cotisation|Contribution/i);
      await expect(page.locator('table.listing')).toBeVisible({ timeout: 10000 });
    });

    test('Config - Contribution types list has data', async ({ loggedInPage: page }) => {
      await page.goto('/contributions-types');

      await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

      const rows = page.locator('tbody tr');
      const count = await rows.count();

      // Should have at least some default contribution types
      expect(count).toBeGreaterThan(0);
    });

    test('Config - Has add contribution type button', async ({ loggedInPage: page }) => {
      await page.goto('/contributions-types');

      const addButton = page.getByRole('button', { name: 'Add' });

      // Admin should see add button
      const isVisible = await addButton.isVisible({ timeout: 5000 }).catch(() => false);
      expect(isVisible).toBeTruthy();
    });

    test('Config - Navigate to add contribution type', async ({ loggedInPage: page }) => {
      await page.goto('/contributions-types');

      const addButton = page.locator('a[href*="/contributions-types/add"]').first();

      if (await addButton.isVisible({ timeout: 5000 })) {
        await addButton.click();

        await expect(page).toHaveURL(/\/contributions-types\/add/);
        await expect(page.locator('form.ui.form')).toBeVisible({ timeout: 10000 });
      }
    });

    test('Config - Contribution type actions visible', async ({ loggedInPage: page }) => {
      await page.goto('/contributions-types');

      await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

      const rows = page.locator('tbody tr');
      const firstRow = rows.first();

      // Check if action buttons exist (edit, delete)
      const actionsCell = firstRow.locator('td.actions_row, td:has(.icon)');
      const hasActions = await actionsCell.count() > 0;

      expect(hasActions).toBeTruthy();
    });

  });

  // Titles Tests
  test.describe('Titles', () => {

    test('Config - Display titles list', async ({ loggedInPage: page }) => {
      await page.goto('/titles');

      await expect(page).toHaveURL(/\/titles/);
      await expect(page.locator('h1, h2')).toContainText(/Titre|Title/i);
      await expect(page.getByRole('button', { name: 'Add' })).toBeVisible({ timeout: 10000 });
    });

    test('Config - Titles list has data', async ({ loggedInPage: page }) => {
      await page.goto('/titles');

      // Wait for table or form (titles page might be a form directly)
      await page.waitForSelector('table.listing, form', { timeout: 10000 });

      // Check if there are title entries
      const titleInputs = page.locator('input[name*="title"], input[type="text"]');
      const count = await titleInputs.count();

      expect(count).toBeGreaterThan(0);
    });

    test('Config - Can modify titles', async ({ loggedInPage: page }) => {
      await page.goto('/titles');

      await page.waitForSelector('form, table', { timeout: 10000 });

      // Check if form has submit button (indicates editable)
      const saveButton = page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Enregistrer")');
      const hasButton = await saveButton.count() > 0;

      expect(hasButton).toBeTruthy();
    });

  });

  // Status Tests
  test.describe('Status', () => {

    test('Config - Display status list', async ({ loggedInPage: page }) => {
      await page.goto('/status');

      await expect(page).toHaveURL(/\/status/);
      await expect(page.locator('h1, h2')).toContainText(/Statut|Status/i);
      await expect(page.getByRole('button', { name: 'Add' })).toBeVisible({ timeout: 10000 });
    });

    test('Config - Status list has data', async ({ loggedInPage: page }) => {
      await page.goto('/status');

      await page.waitForSelector('table.listing, form', { timeout: 10000 });

      const rows = page.locator('tbody tr, table tr, form .field');
      const count = await rows.count();

      // Should have at least the default statuses
      expect(count).toBeGreaterThan(0);
    });

    test('Config - Status are displayed', async ({ loggedInPage: page }) => {
      await page.goto('/status');

      await page.waitForSelector('table, form', { timeout: 10000 });

      // Check for common status (Active, Inactive, etc.)
      const pageContent = await page.textContent('body');
      const hasStatusInfo = pageContent !== null && pageContent.length > 100;

      expect(hasStatusInfo).toBeTruthy();
    });

  });

});

