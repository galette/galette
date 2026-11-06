/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Roles administration: list, system roles, custom roles and their members
 *
 * System roles are only read here; their permissions are changed in
 * roles-changes.spec.ts, which runs in a project of its own. Custom roles created
 * here have no permission, giving them changes nobody's rights.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { AclHelper, SYSTEM_ROLES } from '../helpers/acl';
import { enableDarkMode, expectContrasted } from '../fixtures/a11y.fixture';

/** Fixture roles, see SeedFixtures::getRoleDefinitions() */
const TREASURY = 'Trésorerie';
const GROUP_SECRETARIAT = 'Secrétariat de groupe';
/** Permissions domain of the test plugin, loaded by tests/router_e2e.php */
const TEST_PLUGIN = 'plugin1';

let ids: Record<'han' | 'anakin' | 'hermione' | 'sally', number>;

test.beforeAll(async ({ browser }) => {
  ids = await AclHelper.memberIds(browser, {
    han: 'SOLO Han',
    anakin: 'SKYWALKER Anakin',
    hermione: 'GRANGER Hermione',
    sally: 'RAGDOLL Sally',
  });
});

/**
 * Permissions a role holds by itself, checked and not inherited
 */
async function ownPermissions(page: Page, roleId: number): Promise<string[]> {
  await page.goto(`/roles/edit/${roleId}`);
  const names = await page.locator('input[name^="permissions["]:checked:not([disabled])').evaluateAll(
    inputs => inputs.map(input => (input.getAttribute('name') ?? '').replace(/^permissions\[(.*)\]$/, '$1'))
  );
  return names.sort();
}

/**
 * Create a custom role from the list, and return its identifier
 */
async function createRole(page: Page, name: string, parentId?: number): Promise<number> {
  await page.goto('/roles');
  await page.locator('tfoot input#name').fill(name);
  if (parentId !== undefined) {
    await page.locator('tfoot select#parent').evaluate((select: HTMLSelectElement, value: string) => {
      select.value = value;
    }, String(parentId));
  }
  await Promise.all([
    page.waitForURL(/\/roles\/edit\/\d+$/),
    page.locator('tfoot button[name="valid"]').click(),
  ]);
  await expect(page.locator('.ui.toast.success')).toBeVisible();
  return Number(page.url().match(/\/roles\/edit\/(\d+)$/)?.[1]);
}

/**
 * Remove a custom role, from its confirmation page
 */
async function removeRole(page: Page, roleId: number): Promise<void> {
  await page.goto(`/roles/remove/${roleId}`);
  await Promise.all([
    page.waitForURL(/\/roles$/),
    page.locator('input#delete').click(),
  ]);
  await expect(page.locator(`a[href="/roles/edit/${roleId}"]`)).toHaveCount(0);
}

test.describe('Roles - System roles', () => {

  test('Roles - List system and fixture roles', async ({ loggedInPage: page }) => {
    await AclHelper.expectAllowed(page, '/roles');

    const rows = page.locator('table.listing tbody tr');
    await expect(rows.filter({ has: page.locator('.ui.mini.label') })).toHaveCount(Object.keys(SYSTEM_ROLES).length);
    for (const id of Object.values(SYSTEM_ROLES)) {
      await expect(page.locator(`a[href="/roles/edit/${id}"]`), `role #${id}`).toHaveCount(1);
      await expect(page.locator(`a[href="/roles/remove/${id}"]`), `role #${id} removal`).toHaveCount(0);
    }
    for (const name of [TREASURY, GROUP_SECRETARIAT]) {
      const row = rows.filter({ hasText: name });
      await expect(row, name).toHaveCount(1);
      await expect(row.locator('a[href*="/roles/remove/"]'), `${name} removal`).toHaveCount(1);
    }
  });

  test('Roles - System role name and parent cannot change', async ({ loggedInPage: page }) => {
    await AclHelper.expectAllowed(page, `/roles/edit/${SYSTEM_ROLES.groupManager}`);
    await expect(page.locator('input#name')).toHaveCount(0);
    await expect(page.locator('select#parent')).toHaveCount(0);
    await expect(page.locator('#role_members form[action$="/members"]')).toHaveCount(0);
    await expect(page.locator('input[name^="permissions["]:not([disabled])')).not.toHaveCount(0);
  });

  test('Roles - Inherited permissions are checked and locked', async ({ loggedInPage: page }) => {
    //administrator inherits from staff, which inherits from member
    const staff = await ownPermissions(page, SYSTEM_ROLES.staff);
    expect(staff.length).toBeGreaterThan(0);

    await page.goto(`/roles/edit/${SYSTEM_ROLES.admin}`);
    for (const name of staff) {
      const input = page.locator(`input[name="permissions[${name}]"]`);
      await expect(input, name).toBeChecked();
      await expect(input, name).toBeDisabled();
    }
  });

  test('Roles - Default permissions of group managers and members', async ({ loggedInPage: page }) => {
    //permissions of the test plugin are checked below
    const core = (names: string[]) => names.filter(name => !name.startsWith(`${TEST_PLUGIN}:`));
    expect(core(await ownPermissions(page, SYSTEM_ROLES.groupManager))).toEqual(
      ['group:export', 'group:read', 'member:export', 'member:print', 'member:read']
    );
    expect(core(await ownPermissions(page, SYSTEM_ROLES.member))).toEqual([]);
  });

  test('Roles - Plugins permissions are listed, with their defaults', async ({ loggedInPage: page }) => {
    //tests/plugins/plugin-test1 declares a member permission, and a staff one open to managers
    expect(await ownPermissions(page, SYSTEM_ROLES.member)).toContain(`${TEST_PLUGIN}:read`);
    expect(await ownPermissions(page, SYSTEM_ROLES.staff)).toContain(`${TEST_PLUGIN}:manage`);
    expect(await ownPermissions(page, SYSTEM_ROLES.groupManager)).toContain(`${TEST_PLUGIN}:manage`);

    //the member one is inherited
    const read = page.locator(`input[name="permissions[${TEST_PLUGIN}:read]"]`);
    await expect(read).toBeChecked();
    await expect(read).toBeDisabled();
  });

  test('Roles - Superadmin, without a member card, reaches everything', async ({ loggedInPage: page }) => {
    await AclHelper.expectAllowed(page, '/dashboard');
    await AclHelper.expectMenu(page, ['/roles', '/preferences', '/members', '/contributions']);
    await AclHelper.expectAllowed(page, `/roles/edit/${SYSTEM_ROLES.admin}`);
  });
});

test.describe.serial('Roles - Custom roles', () => {

  test('Roles - Create, rename and remove a role', async ({ loggedInPage: page }) => {
    const name = `E2E role ${Date.now()}`;
    const roleId = await createRole(page, name);

    //custom roles can be renamed, and inherit from another one
    await expect(page.locator('input#name')).toHaveValue(name);
    await page.locator('input#name').evaluate((input: HTMLInputElement, value: string) => {
      input.value = value;
    }, `${name} renamed`);
    await page.locator('select#parent').evaluate((select: HTMLSelectElement, value: string) => {
      select.value = value;
    }, String(SYSTEM_ROLES.member));
    await Promise.all([
      page.waitForURL(new RegExp(`/roles/edit/${roleId}$`)),
      page.locator('form.ui.form button[type="submit"].primary').click(),
    ]);
    await expect(page.locator('.ui.toast.success')).toBeVisible();
    await expect(page.locator('input#name')).toHaveValue(`${name} renamed`);
    await expect(page.locator('select#parent')).toHaveValue(String(SYSTEM_ROLES.member));

    await removeRole(page, roleId);
  });

  test('Roles - A parent making a loop is refused', async ({ loggedInPage: page }) => {
    const stamp = Date.now();
    const parentId = await createRole(page, `E2E parent ${stamp}`);
    const childId = await createRole(page, `E2E child ${stamp}`, parentId);

    try {
      //the child is not even offered as a parent of its parent
      await page.goto(`/roles/edit/${parentId}`);
      const parent = page.locator('select#parent');
      await expect(parent.locator(`option[value="${childId}"]`)).toHaveCount(0);

      //and a forged one is refused
      await parent.evaluate((select: HTMLSelectElement, value: string) => {
        select.add(new Option('forged', value));
        select.value = value;
      }, String(childId));
      await Promise.all([
        page.waitForEvent('load'),
        page.locator('form.ui.form button[type="submit"].primary').click(),
      ]);
      await expect(page.locator('.ui.toast.error')).toBeVisible();

      await page.goto(`/roles/edit/${parentId}`);
      await expect(page.locator('select#parent')).toHaveValue('');
    } finally {
      await removeRole(page, childId);
      await removeRole(page, parentId);
    }
  });

  test('Roles - Give a role for all groups, then for one, and take them back', async ({ loggedInPage: page }) => {
    const roleId = await createRole(page, `E2E given ${Date.now()}`);

    try {
      await AclHelper.giveRole(page, roleId, ids.han);
      await expect(page.locator('.ui.toast.success')).toBeVisible();
      await AclHelper.giveRole(page, roleId, ids.han, 'Skywalker Family');
      await expect(page.locator('.ui.toast.success')).toBeVisible();
      await expect(AclHelper.roleAssignments(page)).toHaveCount(2);

      //the same assignment twice is refused
      await AclHelper.giveRole(page, roleId, ids.han);
      await expect(page.locator('.ui.toast.error')).toBeVisible();
      await expect(AclHelper.roleAssignments(page)).toHaveCount(2);

      //member card shows both, linked to the role
      await page.goto(`/member/${ids.han}`);
      const given = page.locator(`a.ui.blue.label[href="/roles/edit/${roleId}"]`);
      await expect(given).toHaveCount(2);
      await expect(given.locator('.detail', { hasText: 'Skywalker Family' })).toHaveCount(1);

      await AclHelper.takeRole(page, roleId, ids.han);
      await page.goto(`/member/${ids.han}`);
      await expect(page.locator(`a[href="/roles/edit/${roleId}"]`)).toHaveCount(0);
    } finally {
      await removeRole(page, roleId);
    }
  });
});

test.describe('Roles - On member cards', () => {

  test('Roles - Given and computed roles on a card', async ({ loggedInPage: page }) => {
    await page.goto(`/member/${ids.hermione}`);
    //given role in blue, computed ones in grey, all linked to their role
    await expect(page.locator('a.ui.blue.label', { hasText: TREASURY })).toBeVisible();
    await expect(page.locator(`a.ui.label:not(.blue)[href="/roles/edit/${SYSTEM_ROLES.member}"]`)).toBeVisible();
    await expect(page.locator('a[href^="/roles/edit/"]').locator('xpath=ancestor::form')).toHaveCount(0);

    await page.goto(`/member/${ids.anakin}`);
    const manager = page.locator(`a.ui.label[href="/roles/edit/${SYSTEM_ROLES.groupManager}"]`);
    await expect(manager).toBeVisible();
    await expect(manager.locator('.detail')).toHaveText('Skywalker Family');
  });

  test('Roles - Labels on a card are readable, in light and dark modes', async ({ loggedInPage: page }) => {
    //a role given for one group: blue label, with the group name in its detail
    await page.goto(`/member/${ids.sally}`);
    const labels = 'a.ui.label[href^="/roles/edit/"]';
    await expect(page.locator('a.ui.blue.label[href^="/roles/edit/"] .detail')).toBeVisible();

    await expectContrasted(page, labels);
    await enableDarkMode(page);
    await expectContrasted(page, labels);
  });

  test('Roles - No roles line on his own card', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasury');
    await page.goto('/member/me');
    await expect(page.locator('a[href^="/roles/edit/"]')).toHaveCount(0);
  });

  test('Roles - Member form only offers custom roles', async ({ loggedInPage: page }) => {
    await page.goto(`/member/edit/${ids.hermione}`);
    const offered = await page.locator('select#role_add option').evaluateAll(
      options => options.map(option => (option as HTMLOptionElement).value).filter(value => value !== '')
    );
    expect(offered.length).toBeGreaterThan(0);
    for (const id of Object.values(SYSTEM_ROLES)) {
      expect(offered, `system role #${id} offered`).not.toContain(String(id));
    }
    //computed roles cannot be taken back
    await expect(page.locator('#roles_field tbody tr:not([data-linked]) .role-remove')).toHaveCount(0);
  });
});
