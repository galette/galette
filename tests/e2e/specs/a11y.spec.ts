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

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';

/**
 * Suite: Accessibility (axe-core, WCAG 2.0 A + AA)
 */
test.describe('Accessibility', () => {

  base.describe('Login page', () => {

    base.test('Login page should have no WCAG 2.x A/AA violations', async ({ page }) => {
      await page.goto('/login');
      await page.locator('form.ui.form').waitFor({ state: 'visible' });

      const results = await axeBuilder(page).analyze();

      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    });

  });

  test.describe('Dashboard page', () => {

    test('Dashboard page should have no WCAG 2.x A/AA violations', async ({ loggedInPage: page }) => {
      await page.locator('#main-activities').waitFor({ state: 'visible' });

      // Dismiss the "admin password" warning toast before scanning to avoid
      // flakiness caused by animation-phase contrast readings.
      const warningToast = page.locator('.ui.toast.warning');
      if (await warningToast.isVisible()) {
        await page.locator('.ui.toast.warning i[role="button"]').click();
        await warningToast.waitFor({ state: 'hidden' });
      }

      const results = await axeBuilder(page).analyze();

      expect(results.violations, formatViolations(results.violations)).toEqual([]);
    });

  });

});
