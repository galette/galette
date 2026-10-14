/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Members and managers table on the group edition form
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import { GroupListPage } from '../pages/GroupListPage';

/**
 * Open the edition form of the first group of the list
 */
async function editFirstGroup(page: Page): Promise<void> {
  const listPage = new GroupListPage(page);
  await listPage.goto();
  await listPage.getGroupRows().first().locator('a[href*="/group/edit/"]').click();
  await expect(page).toHaveURL(/\/group\/edit\/\d+/);
  await expect(page.locator('#group_persons_list')).toBeVisible();
}

test.describe('Group persons', () => {

  test('Group persons - Change a manager, cancel a removal', async ({ loggedInPage: page }) => {
    await editFirstGroup(page);

    const row = page.locator('#group_persons_list tbody tr[data-person]').first();
    const manager = row.locator('.person-manager');
    const wasManager = await manager.isChecked();

    await row.locator('.person-manager + label').click();
    await expect(manager).toBeChecked({ checked: !wasManager });
    await expect(row.locator('.person-state[data-state="changed"]')).toBeVisible();
    await expect(page.locator('#group_persons_status .message')).toBeVisible();

    await row.locator('.person-remove').click();
    await expect(row.locator('.person-state[data-state="removed"]')).toBeVisible();
    await expect(row.locator('.person-member')).toBeDisabled();
    await expect(manager).toBeDisabled();
    await expect(row.locator('.person-undo')).toBeFocused();

    //cancelling the removal also restores the initial state
    await row.locator('.person-undo').click();
    await expect(manager).toBeChecked({ checked: wasManager });
    await expect(manager).toBeEnabled();
    await expect(row.locator('.person-remove')).toBeFocused();
    await expect(row.locator('.person-state:visible')).toHaveCount(0);
    await expect(page.locator('#group_persons_status .message')).toBeHidden();
  });

  test('Group persons - Group managers cannot change persons', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await editFirstGroup(page);

    const toggles = page.locator('#group_persons_list input[type=checkbox]');
    await expect(toggles).not.toHaveCount(0);
    for (const toggle of await toggles.all()) {
      await expect(toggle).toBeDisabled();
    }
    await expect(page.locator('#person_add')).toHaveCount(0);
    await expect(page.locator('.person-remove')).toHaveCount(0);
  });

  test('Group persons - Accessibility', async ({ loggedInPage: page }) => {
    await editFirstGroup(page);

    const results = await axeBuilder(page)
      // Fomantic search dropdowns always use this autocomplete value
      .exclude('*[autocomplete="fomantic-search"]')
      .include('#group_persons')
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });
});
