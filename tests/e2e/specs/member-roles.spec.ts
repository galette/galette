/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Member's roles table on the member edition form
 */

import { type Page } from '@playwright/test';
import { test, expect, logIn } from '../fixtures/auth.fixture';
import { axeBuilder, enableDarkMode, expectContrasted, formatViolations } from '../fixtures/a11y.fixture';
import { AclHelper } from '../helpers/acl';
import { MemberListPage } from '../pages/MemberListPage';

// Roles seeded by fixtures, given to a member no other spec logs in as, reads or edits:
// files run in parallel, the roles given here would change what other specs check.
const MEMBER = 'WEASLEY';
const MEMBER_NAME = 'WEASLEY Ron';
const ROLE_TO_ADD = 'Secrétariat de groupe';
const GIVEN_ROLE = 'Trésorerie';

let memberId: number;
let roleIds: Record<'given' | 'added', number>;

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

  //the member gets a role to take back or restore, and loses every role given here at the end
  test.beforeAll(async ({ browser }) => {
    memberId = (await AclHelper.memberIds(browser, { member: MEMBER_NAME })).member;
    const context = await browser.newContext();
    const page = await context.newPage();
    await logIn(page, 'superadmin');
    roleIds = {
      given: await AclHelper.roleId(page, GIVEN_ROLE),
      added: await AclHelper.roleId(page, ROLE_TO_ADD),
    };
    await AclHelper.giveRole(page, roleIds.given, memberId);
    await expect(AclHelper.roleAssignments(page, memberId)).toHaveCount(1);
    await context.close();
  });

  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await logIn(page, 'superadmin');
    await AclHelper.takeRole(page, roleIds.added, memberId);
    await AclHelper.takeRole(page, roleIds.given, memberId);
    await context.close();
  });

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

  test('Member roles - Pending changes are readable, in light and dark modes', async ({ loggedInPage: page }) => {
    const pending = async () => {
      await roleRow(page, GIVEN_ROLE).locator('.role-remove').click();
      await addRole(page, ROLE_TO_ADD);
      await expect(page.locator('.role-state[data-state="added"]')).toBeVisible();
      await expect(page.locator('.role-state[data-state="removed"]')).toBeVisible();
    };

    await editMember(page, MEMBER);
    await pending();
    await expectContrasted(page, '#member_roles .role-state');

    //switching reloads the form: changes are made again
    await enableDarkMode(page);
    await pending();
    await expectContrasted(page, '#member_roles .role-state');
  });
});
