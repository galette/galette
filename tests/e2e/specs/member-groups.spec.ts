/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Member's groups table on the member edition form
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import { MemberListPage } from '../pages/MemberListPage';

const GROUP_TO_ADD = 'Équipage du Planet Express';

/**
 * Open the edition form of the only member matching the filter
 */
async function editMember(page: Page, name: string): Promise<void> {
  const listPage = new MemberListPage(page);
  await listPage.goto();
  await listPage.filterByName(name);
  const href = await listPage.getDataRows().first().locator('a[href*="/member/edit/"]').first().getAttribute('href');
  expect(href).not.toBeNull();
  await page.goto(href as string);
  await expect(page.locator('#member_groups')).toBeVisible();
}

/**
 * Row of a group in the table, by its displayed name
 */
function groupRow(page: Page, name: string) {
  return page.locator('#member_groups tbody tr[data-group]').filter({ hasText: name });
}

async function save(page: Page): Promise<void> {
  await Promise.all([
    page.waitForURL(/\/member\/\d+$/),
    page.locator('button[type="submit"][name="valid"]').first().click(),
  ]);
}

test.describe.serial('Member groups', () => {

  test('Member groups - Add a group, then remove it', async ({ loggedInPage: page }) => {
    await editMember(page, 'RAGDOLL');

    const row = groupRow(page, GROUP_TO_ADD);
    await expect(row).toBeHidden();

    //add the group from the search dropdown, with the keyboard
    const search = page.locator('#groups_field .ui.search.dropdown input.search');
    await search.fill('Planet');
    await expect(page.locator('#groups_field .menu .item.selected')).toHaveText(GROUP_TO_ADD);
    //Enter only picks an entry once the menu is open; before, it opens it
    await expect(page.locator('#groups_field .ui.search.dropdown')).toHaveClass(/\bvisible\b/);
    await search.press('Enter');
    await expect(page.locator('#group_add')).not.toHaveValue('');
    await page.locator('#group_add_btn').click();

    await expect(row).toBeVisible();
    await expect(row.locator('.group-member')).toBeChecked();
    await expect(row.locator('.group-member')).toBeFocused();
    await expect(row.locator('.group-state[data-state="added"]')).toBeVisible();
    await expect(page.locator('#member_groups_status .message')).toBeVisible();

    await save(page);
    await expect(page.locator('body')).toContainText(GROUP_TO_ADD);

    //remove it again, so the fixtures stay as they were
    await editMember(page, 'RAGDOLL');
    await expect(row.locator('.group-member')).toBeChecked();
    await row.locator('.group-remove').click();
    await expect(row.locator('.group-state[data-state="removed"]')).toBeVisible();
    await expect(row.locator('.group-member')).not.toBeChecked();
    await expect(row.locator('.group-undo')).toBeFocused();

    await save(page);
    await editMember(page, 'RAGDOLL');
    await expect(row).toBeHidden();
  });

  test('Member groups - Cancel a removal', async ({ loggedInPage: page }) => {
    await editMember(page, 'RAGDOLL');

    const row = page.locator('#member_groups tbody tr[data-group]:visible').first();
    await expect(row.locator('.group-member')).toBeChecked();

    await row.locator('.group-remove').click();
    await expect(row.locator('.group-member')).not.toBeChecked();
    await row.locator('.group-undo').click();

    await expect(row.locator('.group-member')).toBeChecked();
    await expect(row.locator('.group-member')).toBeEnabled();
    await expect(row.locator('.group-remove')).toBeFocused();
    await expect(row.locator('.group-state:visible')).toHaveCount(0);
    await expect(page.locator('#member_groups_status .message')).toBeHidden();
  });

  test('Member groups - Group managers cannot change managers', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await page.goto('/member/me');
    await page.locator('a[href*="/member/edit/"]').first().click();
    await expect(page.locator('#member_groups')).toBeVisible();

    const managers = page.locator('#member_groups .group-manager');
    await expect(managers).not.toHaveCount(0);
    for (const manager of await managers.all()) {
      await expect(manager).toBeDisabled();
    }
  });

  test('Member groups - Accessibility', async ({ loggedInPage: page }) => {
    await editMember(page, 'RAGDOLL');

    const results = await axeBuilder(page)
      // Fomantic search dropdowns always use this autocomplete value
      .exclude('*[autocomplete="fomantic-search"]')
      .include('#groups_field')
      .analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });
});
