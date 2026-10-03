/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Members Tests - CRUD operations with isolated test data
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { MemberListPage } from '../pages/MemberListPage';
import { MemberFormPage } from '../pages/MemberFormPage';

const TEST_MEMBER = {
  lastName:  'E2ETest',
  firstName: 'User',
  address:   'Test address',
  zipcode:   '1234',
  city:      'New-New-York',
  login:     'e2e.test.user',
  password:  'E2E-T3st!',
  email:     'e2e.test.user@example.test',

};

test.describe('Members', () => {

  test('Members - Display list with data', async ({ loggedInPage: page }) => {
    const listPage = new MemberListPage(page);
    await listPage.goto();

    await expect(page).toHaveURL(/\/members/);
    await expect(listPage.memberTable).toBeVisible();
    await expect(listPage.getDataRows()).not.toHaveCount(0);
  });

  test('Members - Filter by name', async ({ loggedInPage: page }) => {
    const listPage = new MemberListPage(page);
    await listPage.goto();
    await listPage.filterByName('SKYWALKER');

    await expect(page.locator('.infoline')).toContainText('3 members');
  });

  test('Members - Clear filter', async ({ loggedInPage: page }) => {
    const listPage = new MemberListPage(page);
    await listPage.goto();
    await listPage.filterByName('SKYWALKER');

    await expect(page.locator('.infoline')).toContainText('3 members');
    const filteredCount = await listPage.getDataRows().count();

    await listPage.clearFilter();

    await expect(page.locator('.infoline')).toContainText('55 members');
    const totalCount = await listPage.getDataRows().count();

    expect(totalCount).toBeGreaterThan(filteredCount);
  });

  test('Members - Navigate to detail page', async ({ loggedInPage: page }) => {
    const listPage = new MemberListPage(page);
    await listPage.goto();
    await listPage.filterByName('SKYWALKER');

    await listPage.getMemberRowByName('SKYWALKER')
      .locator('a[href*="/member/"]')
      .first()
      .click();

    await expect(page).toHaveURL(/\/member\/\d+$/);
  });

  // CRUD Operations (serial execution)
  test.describe('Members CRUD', () => {
    test.describe.configure({ mode: 'serial' });

    let createdMemberUrl = '';
    let memberId;

    test('Members - Create new member', async ({ loggedInPage: page }) => {
      const formPage = new MemberFormPage(page);
      await formPage.goto();
      await formPage.fill(TEST_MEMBER);
      await formPage.submit();

      // Should redirect back to groups list
      await expect(page).toHaveURL(/\/contribution\/fee\/add/);
      await expect(page.locator('.ui.toast.success')).toBeVisible({ timeout: 10000 });

      debugger;
      createdMemberUrl = page.url();
      memberId = createdMemberUrl.match(/\/contribution\/fee\/add\?id_adh=(\d+)$/)[1];
    });

    test('Members - Edit member', async ({ loggedInPage: page }) => {
      await page.goto(`/member/edit/${memberId}`);

      await expect(page).toHaveURL(/\/member\/edit\/\d+/);

      await page.locator('#prenom_adh').fill('UpdatedUser');
      await page.getByRole('button', { name: 'Save' }).nth(1).click();

      await page.waitForURL(/\/member\/\d+$/);
      await expect(page).toHaveURL(/\/member\/\d+$/);
    });

    test('Members - Delete member', async ({ loggedInPage: page }) => {

      await page.goto(`/member/remove/${memberId}`);
      await expect(page.locator('input#delete')).toBeVisible();
      await page.locator('input#delete').click();

      await page.waitForURL(/\/members/);
      await expect(page).toHaveURL(/\/members/);
    });

  });

});
