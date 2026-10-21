/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Member's roles table on the member edition form
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import { MemberListPage } from '../pages/MemberListPage';

// Roles seeded by fixtures; hermione.granger has the second one for all groups.
// Not sally.ragdoll: member-groups.spec.ts edits her card, and files run in parallel,
// so saving one form would overwrite what the other just stored.
const MEMBER = 'GRANGER';
const ROLE_TO_ADD = 'Secrétariat de groupe';
const GIVEN_ROLE = 'Trésorerie';

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
  await expect(page.locator('#member_roles')).toBeVisible();
}

/**
 * Row of a given role in the table, by its displayed name
 */
function roleRow(page: Page, name: string) {
  return page.locator('#member_roles tbody tr[data-linked]').filter({ hasText: name });
}

async function addRole(page: Page, name: string): Promise<void> {
  await page.locator('#role_add').locator('xpath=..').click();
  await page.locator('#roles_field .menu .item', { hasText: name }).click();
  await page.locator('#role_add_btn').click();
}

async function save(page: Page): Promise<void> {
  await Promise.all([
    page.waitForURL(/\/member\/\d+$/),
    page.locator('button[type="submit"][name="valid"]').first().click(),
  ]);
}

test.describe.serial('Member roles', () => {

  test('Member roles - Give a role, then take it back', async ({ loggedInPage: page }) => {
    await editMember(page, MEMBER);
    await expect(roleRow(page, ROLE_TO_ADD)).toHaveCount(0);

    await addRole(page, ROLE_TO_ADD);
    const row = roleRow(page, ROLE_TO_ADD);
    await expect(row.locator('.role-state[data-state="added"]')).toBeVisible();
    await expect(row.locator('.role-remove')).toBeFocused();
    await expect(page.locator('#member_roles_status .message')).toBeVisible();

    await save(page);
    await expect(page.locator(`a.ui.blue.label:has-text("${ROLE_TO_ADD}")`)).toBeVisible();

    //take it back, so the fixtures stay as they were
    await editMember(page, MEMBER);
    await row.locator('.role-remove').click();
    await expect(row.locator('.role-state[data-state="removed"]')).toBeVisible();
    await expect(row.locator('.role-undo')).toBeFocused();

    await save(page);
    await expect(page.locator(`a.ui.blue.label:has-text("${ROLE_TO_ADD}")`)).toHaveCount(0);
    await expect(page.locator(`a.ui.blue.label:has-text("${GIVEN_ROLE}")`)).toBeVisible();
  });

  test('Member roles - Cancel a removal', async ({ loggedInPage: page }) => {
    await editMember(page, MEMBER);

    const row = roleRow(page, GIVEN_ROLE);
    await row.locator('.role-remove').click();
    await expect(row.locator('input[name="roles_adh[]"]')).toBeDisabled();
    await row.locator('.role-undo').click();

    await expect(row.locator('input[name="roles_adh[]"]')).toBeEnabled();
    await expect(row.locator('.role-remove')).toBeFocused();
    await expect(row.locator('.role-state:visible')).toHaveCount(0);
    await expect(page.locator('#member_roles_status .message')).toBeHidden();
  });

  test('Member roles - Hidden from group managers', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await page.goto('/member/me');
    await page.locator('a[href*="/member/edit/"]').first().click();
    await expect(page.locator('#member_groups')).toBeVisible();
    await expect(page.locator('#roles_field')).toHaveCount(0);
  });

  test('Member roles - Accessibility', async ({ loggedInPage: page }) => {
    await editMember(page, MEMBER);

    const results = await axeBuilder(page).include('#roles_field').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });

  test('Member roles - Accessibility, with pending changes', async ({ loggedInPage: page }) => {
    await editMember(page, MEMBER);

    await roleRow(page, GIVEN_ROLE).locator('.role-remove').click();
    await addRole(page, ROLE_TO_ADD);
    await expect(page.locator('#member_roles_status .message')).toBeVisible();

    const results = await axeBuilder(page).include('#roles_field').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
  });
});
