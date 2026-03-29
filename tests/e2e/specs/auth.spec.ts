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
 * @since     Available since 0.7dev - 2007-10-06
 */

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

/**
 * Suite: Authentication
 */
test.describe('Login page', () => {

  base.describe('Display', () => {
    base.test('Login page display', async ({ page }) => {
      await page.goto('/login');

      await expect(page).toHaveURL(/\/login/);
      await expect(page).toHaveTitle(/Galette/i);
      await expect(page.locator('form.ui.form')).toBeVisible();
      await expect(page.locator('input#login')).toBeVisible();
      await expect(page.locator('input#password')).toBeVisible();
      await expect(page.locator('input[type="submit"]')).toBeVisible();
    });

    base.test('Shows an error message if credentials are invalid', async ({ page }) => {
      await page.goto('/login');

      await page.locator('input#login').fill('non_existing_user');
      await page.locator('input#password').fill('wrong_password');
      await page.locator('input[type="submit"]').click();

      await expect(page).toHaveURL(/\/login/);
      // Wait for the error toast to appear. It can take some time in CI.
      await expect(page.locator('.ui.toast.error')).toBeVisible({ timeout: 10000 });
    });
  });

  test.describe('Login/Logout', () => {

    test('Redirects to dashboard when logged-in', async ({ loggedInPage: page }) => {
      // Fixture already proceeds login
      await expect(page).toHaveURL('/dashboard');

      // User password is 'admin', so the warning toast should be displayed.
      // Increase timeout for slow CI environments.
      await expect(page.locator('.ui.toast.warning')).toBeVisible({ timeout: 10000 });

      // Dashboard shows "Activities" section
      await expect(page.locator('#main-activities')).toBeVisible();
    });

    test('Logout and redirect to login page', async ({ loggedInPage: page }) => {
      //hide toast
      await expect(page.locator('.ui.toast.warning')).toBeVisible();
      await page.locator('.ui.toast.warning i[role="button"]').click();
      await expect(page.locator('.ui.toast.warning')).toBeHidden();

      // /!\ There are 2 logout links in the page, one is visible, the other is not (mobile menu).
      const logoutLink = page.locator('[href*="/logout"]')
      await logoutLink.last().click();

      // After logout, return to login page
      await expect(page).toHaveURL('/login');
      await expect(page.locator('input#login')).toBeVisible();
    });

  });

});
