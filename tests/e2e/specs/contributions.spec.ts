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
 * Contributions Tests - CRUD operations
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { MemberListPage } from '../pages/MemberListPage';
import { DropdownHelper } from '../helpers/dropdown';

/**
 * Navigates to a member's contributions page via the members list,
 * then clicks the "Add fee" or "Add donation" button.
 */
async function openContributionFormForMember(
  page: import('@playwright/test').Page,
  memberName: string,
  type: 'fee' | 'donation'
): Promise<void> {
  const listPage = new MemberListPage(page);
  await listPage.goto();
  await listPage.filterByName(memberName);

  await listPage.getMemberRowByName(memberName)
    .locator('a:has(i.receipt.green.icon)').nth(1)
    .click();

  await expect(page).toHaveURL(/\/contributions\/member\//);

  await page.locator(`a[href*="/contribution/${type}/add"]`).nth(1).click();
  await expect(page).toHaveURL(new RegExp(`/contribution/${type}/add`));
}

test.describe('Contributions', () => {

  test('Contributions - Display list', async ({ loggedInPage: page }) => {
    await page.goto('/contributions');

    await expect(page).toHaveURL(/\/contributions/);
    await expect(page.locator('table.listing')).toBeVisible();
  });

  test('Contributions - Add membership fee', async ({ loggedInPage: page }) => {
    await openContributionFormForMember(page, 'SKYWALKER', 'fee');

    await expect(page.locator('#montant_cotis')).toBeVisible();

    await DropdownHelper.selectFirst(page, 'id_adh');
    await DropdownHelper.selectFirst(page, 'id_type_cotis');
    await page.locator('#montant_cotis').fill('30');
    await page.locator('button[type="submit"][name="valid"]').click();

    await page.waitForURL(/\/contributions/);
    await expect(page).toHaveURL(/\/contributions/);
  });

  test('Contributions - Add donation', async ({ loggedInPage: page }) => {
    await openContributionFormForMember(page, 'SKYWALKER', 'donation');

    await expect(page.locator('#montant_cotis')).toBeVisible();

    await DropdownHelper.selectFirst(page, 'id_adh');
    await DropdownHelper.selectFirst(page, 'id_type_cotis');
    await page.locator('#montant_cotis').fill('50');
    await page.locator('button[type="submit"][name="valid"]').click();

    await page.waitForURL(/\/contributions/);
    await expect(page).toHaveURL(/\/contributions/);
  });

});

