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
 * Import/Export Tests - CSV export and import functionality
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

test.describe('Import/Export', () => {

  // Export Tests
  test.describe('Export', () => {

    test('Export - Display export page', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      await expect(page).toHaveURL(/\/export/);
      await expect(page.locator('h1, h2')).toContainText(/Export/i);
      await expect(page.locator('form')).toBeVisible({ timeout: 10000 });
    });

    test('Export - Has export options', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      await page.locator('form').waitFor({ state: 'visible', timeout: 10000 });

      // Should have checkboxes for export options
      const checkboxes = page.locator('input[type="checkbox"]');
      const count = await checkboxes.count();

      expect(count).toBeGreaterThan(0);
    });

    test('Export - Has submit button', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      const submitButton = page.locator('button[type="submit"], input[type="submit"]').first();
      await expect(submitButton).toBeVisible({ timeout: 10000 });
    });

    test('Export - Generate CSV export', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      await page.locator('form').waitFor({ state: 'visible', timeout: 10000 });

      // Select at least one export option (e.g., members)
      const membersCheckbox = page.locator('#galete_adherents');

      if (await membersCheckbox.isVisible()) {
        await membersCheckbox.check();

        // Submit the form
        await page.locator('button[type="submit"], input[type="submit"]').first().click();

        // Wait for page to reload or show success message
        await page.waitForLoadState('networkidle');

        // Check for success message or existing exports table
        const hasSuccessMessage = await page.locator('.ui.success.message, .ui.positive.message').count();
        const hasExportsTable = await page.locator('table.listing').count();

        expect(hasSuccessMessage + hasExportsTable).toBeGreaterThan(0);
      }
    });

    test('Export - Existing exports are listed', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      await page.waitForSelector('form, table', { timeout: 10000 });

      // If there are existing exports, they should be in a table
      const exportsTable = page.locator('table.listing');
      const tableExists = await exportsTable.count() > 0;

      if (tableExists) {
        // Table should have columns: Name, Date, Size, Actions
        const headers = exportsTable.locator('thead th');
        const headerCount = await headers.count();

        expect(headerCount).toBeGreaterThanOrEqual(3); // At least Name, Date, Actions
      }
    });

    test('Export - Can download existing export', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      await page.waitForSelector('form, table', { timeout: 10000 });

      // Check if there are existing exports
      const downloadLink = page.locator('a[href*="/export/get/"]').first();
      const linkExists = await downloadLink.count() > 0;

      if (linkExists) {
        // Verify the link is visible
        await expect(downloadLink).toBeVisible();

        // The link should have a filename
        const href = await downloadLink.getAttribute('href');
        expect(href).toContain('/export/get/');
      }
    });

  });

  // Import Tests
  test.describe('Import', () => {

    test('Import - Display import page', async ({ loggedInPage: page }) => {
      await page.goto('/import');

      await expect(page).toHaveURL(/\/import/);
      await expect(page.locator('h1, h2')).toContainText(/Import/i);
    });

    test('Import - Has file upload form', async ({ loggedInPage: page }) => {
      await page.goto('/import');

      // Should have a file input for CSV upload
      const fileInput = page.locator('input[type="file"]');

      const fileInputExists = await fileInput.count() > 0;
      expect(fileInputExists).toBeTruthy();
    });

    test('Import - Has import model link', async ({ loggedInPage: page }) => {
      await page.goto('/import');

      // Should have a link to download import model/template
      const modelLink = page.locator('a[href*="/import/model"]');
      const linkExists = await modelLink.count() > 0;

      if (linkExists) {
        await expect(modelLink.first()).toBeVisible();
      }
    });

    test('Import - Display form structure', async ({ loggedInPage: page }) => {
      await page.goto('/import');

      // Page should have import instructions or form
      const hasForm = await page.locator('form').count() > 0;
      const hasSegment = await page.locator('.ui.segment').count() > 0;

      expect(hasForm || hasSegment).toBeTruthy();
    });

  });

  // CSV File Verification
  test.describe('CSV Validation', () => {

    test('Export - Verify page loads without errors', async ({ loggedInPage: page }) => {
      await page.goto('/export');

      // Check that page loaded successfully
      await expect(page.locator('form')).toBeVisible({ timeout: 10000 });

      // No error messages should be visible
      const errorMessages = page.locator('.ui.error.message, .ui.negative.message');
      const errorCount = await errorMessages.count();

      expect(errorCount).toBe(0);
    });

    test('Import - Verify page loads without errors', async ({ loggedInPage: page }) => {
      await page.goto('/import');

      // Check that page loaded successfully
      await page.waitForSelector('h1, h2, form', { timeout: 10000 });

      // No error messages should be visible
      const errorMessages = page.locator('.ui.error.message, .ui.negative.message');
      const errorCount = await errorMessages.count();

      expect(errorCount).toBe(0);
    });

  });

});

