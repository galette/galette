/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Access control, as given by the roles of a fresh installation and the fixtures
 *
 * Nothing here changes a role: those tests are in roles-changes.spec.ts, which runs
 * in a project of its own. Accounts, see fixtures/auth.fixture.ts:
 * - staff: morpheus, treasurer status, not administrator;
 * - groupManager: anakin.skywalker, manager of Skywalker Family (luke, leia, ben);
 * - member: luke.skywalker, parent of ben.skywalker;
 * - treasury: hermione.granger, "Trésorerie" role for all groups;
 * - groupSecretary: sally.ragdoll, "Secrétariat de groupe" role for Les Âmes Errantes.
 *
 * Interface of the fixtures members is in French: assertions rely on links and
 * styles, not on texts.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { AclHelper } from '../helpers/acl';

/** Fixture members, as displayed */
const NAMES = {
  anakin: 'SKYWALKER Anakin',
  luke: 'SKYWALKER Luke',
  leia: 'ORGANA Leia',
  ben: 'SKYWALKER Ben',
  han: 'SOLO Han',
  victor: 'VAN DORT Victor',
  emily: 'LA MARIÉE Emily',
  sally: 'RAGDOLL Sally',
};
let ids: Record<keyof typeof NAMES, number>;

test.beforeAll(async ({ browser }) => {
  ids = await AclHelper.memberIds(browser, NAMES);
});

/** Configuration entries a staff member manages, with references and models */
const STAFF_CONFIGURATION = ['/contributions-types', '/payment-types', '/status', '/titles', '/models/pdf', '/texts'];
/** Configuration entries kept to administrators */
const ADMIN_CONFIGURATION = ['/preferences', '/fields/core/configure', '/fields/dynamic/configure', '/plugins', '/roles'];

/**
 * Batch actions offered on the members list
 */
function batchAction(page: Page, name: string) {
  return page.locator(`.batch-action[data-value="${name}"]`);
}

test.describe('ACL - Preferences', () => {

  test('ACL - Rights tab only links to roles', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await page.goto('/preferences?tab=rights');

    const tab = page.locator('.tab[data-tab="rights"]');
    await expect(tab.locator('a[href="/roles"]')).toBeVisible();
    await expect(tab.locator('input')).toHaveCount(0);
  });
});

test.describe('ACL - Staff', () => {

  test('ACL - Staff configuration menu', async ({ loggedInAs }) => {
    const page = await loggedInAs('staff');
    await AclHelper.expectMenu(page, STAFF_CONFIGURATION, ADMIN_CONFIGURATION);
  });

  test('ACL - Staff cannot open preferences nor roles', async ({ loggedInAs }) => {
    const page = await loggedInAs('staff');
    await AclHelper.expectRefused(page, '/preferences');
    await AclHelper.expectRefused(page, '/roles');
  });

  test('ACL - Staff manages every member', async ({ loggedInAs }) => {
    const page = await loggedInAs('staff');
    await AclHelper.expectMenu(page, ['/members', '/member/add']);

    await page.goto('/members');
    await expect(page.locator('main a[href="/member/add"]').first()).toBeVisible();
    for (const action of ['masschange', 'delete', 'csv__directdownload']) {
      await expect(batchAction(page, action), `batch action ${action}`).not.toHaveCount(0);
    }

    await AclHelper.expectAllowed(page, `/member/${ids.han}`);
    await expect(page.locator(`a[href="/member/edit/${ids.han}"]`).first()).toBeVisible();
    await AclHelper.expectAllowed(page, `/member/edit/${ids.han}`);
    await AclHelper.expectAllowed(page, '/member/add');
  });

  test('ACL - Staff manages contributions and transactions of every member', async ({ loggedInAs }) => {
    const page = await loggedInAs('staff');
    await AclHelper.expectMenu(page, ['/contributions', '/transactions', '/contribution/fee/add', '/transaction/add', '/mailings']);

    await AclHelper.expectContributionsOf(page, ids.han);
    await AclHelper.expectAllowed(page, '/contribution/fee/add');
    await AclHelper.expectAllowed(page, '/transaction/add');
    await AclHelper.expectAllowed(page, '/mailings');
  });
});

test.describe('ACL - Group manager, default permissions', () => {

  test('ACL - Group manager menu', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await AclHelper.expectMenu(
      page,
      ['/members', '/groups'],
      [...STAFF_CONFIGURATION, ...ADMIN_CONFIGURATION, '/member/add', '/contributions', '/transactions', '/mailings']
    );
  });

  test('ACL - Group manager lists members of his group only', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await page.goto('/members');
    await expect(page.locator('main a[href="/member/add"]')).toHaveCount(0);

    //members of the group, and himself
    const listed = await AclHelper.listedMembers(page);
    expect(listed.sort()).toEqual([ids.anakin, ids.luke, ids.leia, ids.ben].sort());
  });

  test('ACL - Group manager reads, but does not edit, his members', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await AclHelper.expectAllowed(page, `/member/${ids.luke}`);
    await expect(page.locator(`a[href="/member/edit/${ids.luke}"]`)).toHaveCount(0);

    await AclHelper.expectRefused(page, `/member/edit/${ids.luke}`);
    await AclHelper.expectRefused(page, `/member/${ids.han}`);
  });

  test('ACL - Group manager exports his members', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await page.goto('/members');
    for (const action of ['csv__directdownload', 'cards__directdownload', 'labels__directdownload', 'attendance_sheet']) {
      await expect(batchAction(page, action), `batch action ${action}`).not.toHaveCount(0);
    }
    for (const action of ['masschange', 'masscontributions', 'delete', 'sendmail']) {
      await expect(batchAction(page, action), `batch action ${action}`).toHaveCount(0);
    }
  });

  test('ACL - Group manager has no access to contributions nor transactions', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await AclHelper.expectRefused(page, '/contribution/fee/add');
    await AclHelper.expectRefused(page, '/transaction/add');

    await AclHelper.expectNoContributionsOf(page, ids.luke, NAMES.luke);
  });

  test('ACL - Group manager only sees his group', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupManager');
    await page.goto('/groups');

    //group pages link the edition of each group shown
    const groups = await page.locator('a[href*="/group/edit/"]').evaluateAll(
      links => [...new Set(links.map(link => link.getAttribute('href')))]
    );
    expect(groups).toHaveLength(1);
    await expect(page.locator('a[href*="/group/add"], .ui.button[href*="/group/remove/"]')).toHaveCount(0);
  });
});

test.describe('ACL - Member', () => {

  test('ACL - Member reads and edits his card and his child one', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    for (const id of [ids.luke, ids.ben]) {
      await AclHelper.expectAllowed(page, `/member/${id}`);
      await AclHelper.expectAllowed(page, `/member/edit/${id}`);
    }
  });

  test('ACL - Member cannot see other members', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    await AclHelper.expectRefused(page, `/member/${ids.han}`);
    await AclHelper.expectRefused(page, `/member/edit/${ids.han}`);
    await AclHelper.expectRefused(page, '/members');
  });

  test('ACL - Member only sees his own contributions', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    await AclHelper.expectContributionsOf(page, ids.luke);
    await AclHelper.expectNoContributionsOf(page, ids.han, NAMES.han);
  });

  test('ACL - Member cannot add members, nor children', async ({ loggedInAs }) => {
    const page = await loggedInAs('member');
    await AclHelper.expectMenu(page, ['/member/me'], ['/member/add', '/member/add/child', ...STAFF_CONFIGURATION]);
    await AclHelper.expectRefused(page, '/member/add');
    await AclHelper.expectRefused(page, '/member/add/child');
  });
});

test.describe('ACL - Treasury role, for all groups', () => {

  test('ACL - Treasury menu', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasury');
    await AclHelper.expectMenu(
      page,
      ['/members', '/contributions', '/transactions', '/contribution/fee/add', '/transaction/add', '/reminders'],
      [...STAFF_CONFIGURATION, ...ADMIN_CONFIGURATION, '/member/add']
    );
  });

  test('ACL - Treasury manages contributions of every member', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasury');
    await AclHelper.expectContributionsOf(page, ids.han);
    await AclHelper.expectAllowed(page, `/contribution/fee/add?id_adh=${ids.han}`);
    await AclHelper.expectAllowed(page, '/transaction/add');
    await AclHelper.expectAllowed(page, '/reminders');
  });

  test('ACL - Treasury reads, but does not edit, members', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasury');
    await AclHelper.expectAllowed(page, `/member/${ids.han}`);
    await expect(page.locator(`a[href="/member/edit/${ids.han}"]`)).toHaveCount(0);
    await AclHelper.expectRefused(page, `/member/edit/${ids.han}`);

    await page.goto('/members');
    await expect(batchAction(page, 'delete')).toHaveCount(0);
  });
});

test.describe('ACL - Group secretariat role, for one group', () => {

  test('ACL - Group secretariat lists members of the group only', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupSecretary');
    await page.goto('/members');

    const listed = await AclHelper.listedMembers(page);
    expect(listed.sort()).toEqual([ids.victor, ids.emily, ids.sally].sort());
  });

  test('ACL - Group secretariat edits members of the group only', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupSecretary');
    for (const id of [ids.victor, ids.emily]) {
      await AclHelper.expectAllowed(page, `/member/edit/${id}`);
    }
    await AclHelper.expectRefused(page, `/member/${ids.luke}`);
    await AclHelper.expectRefused(page, `/member/edit/${ids.luke}`);
  });

  test('ACL - Group secretariat sees the group, not the others', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupSecretary');
    await page.goto('/groups');
    const groups = await page.locator('a[href*="/group/edit/"]').evaluateAll(
      links => [...new Set(links.map(link => link.getAttribute('href')))]
    );
    expect(groups).toHaveLength(1);
  });

  test('ACL - Group secretariat has no access to contributions', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupSecretary');
    await AclHelper.expectMenu(page, ['/members', '/groups'], ['/contributions', '/transactions', ...ADMIN_CONFIGURATION]);
    await AclHelper.expectNoContributionsOf(page, ids.victor, NAMES.victor);
  });
});
