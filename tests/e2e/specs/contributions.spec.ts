/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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

