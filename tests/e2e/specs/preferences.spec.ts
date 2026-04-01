/*!
 * Copyright © 2007-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the License, or
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

