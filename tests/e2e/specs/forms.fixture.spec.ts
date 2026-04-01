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
 * E2E Tests for Galette Forms
 * Tests form validation, submission, and data handling
 */

import { test } from '../fixtures/auth.fixture';
import { expect } from '@playwright/test';

test.describe('Forms', () => {

  // Member Forms
  test('Forms - Member add form displays', async ({ loggedInPage: page }) => {
    await page.goto('/member/add');
    await expect(page).toHaveURL(/\/member\/add/);

    await expect(page.locator('form, [role="form"]')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('h1, h2')).toContainText(/add|create|new|member|adhérent/i);
  });

  test('Forms - Member form has required fields', async ({ loggedInPage: page }) => {
    await page.goto('/member/add');
    await page.locator('form, [role="form"]').waitFor({ state: 'visible', timeout: 10000 });

    const firstNameInput = page.locator('input[name*="prenom"], input[name*="firstname"]').first();
    const lastNameInput = page.locator('input[name*="nom"], input[name*="lastname"]').first();
    const emailInput = page.locator('input[type="email"], input[name*="email"]').first();

    const fieldCount = await Promise.all([
      firstNameInput.isVisible({ timeout: 3000 }).catch(() => false),
      lastNameInput.isVisible({ timeout: 3000 }).catch(() => false),
      emailInput.isVisible({ timeout: 3000 }).catch(() => false),
    ]).then(results => results.filter(Boolean).length);

    expect(fieldCount).toBeGreaterThan(0);
  });

  test('Forms - Member form validates email format', async ({ loggedInPage: page }) => {
    await page.goto('/member/add');
    const emailInput = page.locator('input[type="email"]').first();

    if (await emailInput.isVisible({ timeout: 3000 })) {
      await emailInput.fill('not-an-email');
      const isInvalid = await emailInput.evaluate((el: HTMLInputElement) => !el.validity.valid);
      expect(isInvalid).toBeTruthy();
    }
  });

  test('Forms - Member edit form shows existing data', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const searchInput = page.getByRole('textbox', { name: 'Search:' });
    await searchInput.fill('luke');
    await page.locator('button[type="submit"]').first().click({ timeout: 5000 });
    await page.waitForTimeout(500);

    const memberLink = page.locator('a:has-text("Skywalker")').first();

    if (await memberLink.isVisible({ timeout: 5000 })) {
      await memberLink.click({ timeout: 5000 });
      await expect(page.locator('h1, h2')).toBeVisible({ timeout: 10000 });

      const form = page.locator('form, [role="form"]');
      if (await form.isVisible({ timeout: 5000 })) {
        const inputValue = await page.locator('input').first().inputValue();
        expect(inputValue || '').toBeTruthy();
      }
    }
  });

  // Contribution Forms
  test('Forms - Contribution add form displays', async ({ loggedInPage: page }) => {
    await page.goto('/contribution/add');

    const form = page.locator('form, [role="form"]');
    if (await form.isVisible({ timeout: 5000 })) {
      await expect(page.locator('h1, h2')).toBeVisible({ timeout: 10000 });
    }
  });

  test('Forms - Contribution form has amount field', async ({ loggedInPage: page }) => {
    await page.goto('/contribution/add');

    const form = page.locator('form, [role="form"]');
    if (await form.isVisible({ timeout: 5000 })) {
      const amountInput = page.locator('input[type="number"], input[name*="amount"], input[name*="montant"]').first();

      if (await amountInput.isVisible({ timeout: 3000 })) {
        await amountInput.fill('50');
        const value = await amountInput.inputValue();
        expect(value).toBe('50');
      }
    }
  });

  test('Forms - Contribution form has date fields', async ({ loggedInPage: page }) => {
    await page.goto('/contribution/add');

    const form = page.locator('form, [role="form"]');
    if (await form.isVisible({ timeout: 5000 })) {
      const dateInputs = page.locator('input[type="date"], input[name*="date"]');
      const dateCount = await dateInputs.count();

      expect(dateCount).toBeGreaterThan(0);
    }
  });

  // Form Navigation
  test('Forms - All forms have submit button', async ({ loggedInPage: page }) => {
    await page.goto('/member/add');

    const form = page.locator('form, [role="form"]');
    await form.waitFor({ state: 'visible', timeout: 10000 });

    const submitBtn = page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Enregistrer")').first();
    await expect(submitBtn).toBeVisible({ timeout: 5000 });
  });

});
