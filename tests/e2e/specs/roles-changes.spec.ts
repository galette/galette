/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Access control after a role change: permissions given to system roles, roles taken back
 *
 * Those tests change roles everybody depends on: they run in the "roles" project,
 * one at a time, against an instance of their own on CI. Each of them puts the
 * roles back as they were, even when it fails.
 */

import { type APIRequestContext, type Page, request } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { AclHelper, SYSTEM_ROLES } from '../helpers/acl';
import { DropdownHelper } from '../helpers/dropdown';

/** Fixture roles, see SeedFixtures::getRoleDefinitions() */
const TREASURY = 'Trésorerie';
const GROUP_SECRETARIAT = 'Secrétariat de groupe';
const LOST_SOULS = 'Les Âmes Errantes';

/** Permissions the manual test plan gives to group managers */
const MANAGERS_EXTRA = [
  'member:edit',
  'contribution:read',
  'contribution:create',
  'transaction:read',
  'transaction:create',
  'transaction:attach',
  'group:edit',
  'mailing:send',
];

const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://127.0.0.1:8025';

const NAMES = {
  anakin: 'SKYWALKER Anakin',
  luke: 'SKYWALKER Luke',
  leia: 'ORGANA Leia',
  ben: 'SKYWALKER Ben',
  han: 'SOLO Han',
  zia: 'DORÉ Zia',
  victor: 'VAN DORT Victor',
  sally: 'RAGDOLL Sally',
  emily: 'LA MARIÉE Emily',
};
let ids: Record<keyof typeof NAMES, number>;

test.beforeAll(async ({ browser }) => {
  ids = await AclHelper.memberIds(browser, NAMES);
});

/**
 * Open the fee form, choose a member by its identifier, and save
 */
async function storeFee(page: Page, memberId: number): Promise<void> {
  await page.goto(`/contribution/fee/add?id_adh=${ids.luke}`);
  await page.locator('input#id_adh_input').evaluate((input: HTMLInputElement, value: string) => {
    input.value = value;
  }, String(memberId));
  await DropdownHelper.selectFirst(page, 'id_type_cotis');
  await page.locator('#montant_cotis').fill('12');
  await Promise.all([
    page.waitForEvent('load'),
    page.locator('button[type="submit"][name="valid"]').first().click(),
  ]);
}

test.describe.serial('Roles changes', () => {
  //each test changes roles and puts them back, with several accounts logged in
  test.describe.configure({ timeout: 120000 });

  test('Roles changes - Group managers given more permissions', async ({ loggedInAs }) => {
    const admin = await loggedInAs('admin');
    const previous = await AclHelper.permissionsOf(admin, SYSTEM_ROLES.groupManager, MANAGERS_EXTRA);

    try {
      await AclHelper.setPermissions(
        admin,
        SYSTEM_ROLES.groupManager,
        Object.fromEntries(MANAGERS_EXTRA.map(name => [name, true]))
      );
      const page = await loggedInAs('groupManager');
      await AclHelper.expectMenu(page, ['/contributions', '/transactions', '/contribution/fee/add', '/transaction/add']);

      //members of his group, not the others
      await AclHelper.expectAllowed(page, `/member/edit/${ids.luke}`);
      await AclHelper.expectRefused(page, `/member/edit/${ids.han}`);

      //contributions: only his members are offered, and stored
      await page.goto('/contribution/fee/add');
      await page.locator('#id_adh').click();
      const offered = await page.locator('#id_adh .menu .item[data-value]').evaluateAll(
        items => items.map(item => Number(item.getAttribute('data-value')))
      );
      expect(offered.length).toBeGreaterThan(0);
      for (const id of offered) {
        expect([ids.anakin, ids.luke, ids.leia, ids.ben], `member #${id} offered`).toContain(id);
      }

      await storeFee(page, ids.han);
      await expect(page.locator('.ui.toast.error, .ui.error.message').first()).toBeVisible();
      await AclHelper.expectNoContributionsOf(page, ids.han, NAMES.han);

      await storeFee(page, ids.luke);
      await expect(page.locator('.ui.toast.success')).toBeVisible();
      await AclHelper.expectContributionsOf(page, ids.luke);

      await AclHelper.expectAllowed(page, '/transaction/add');
      await page.goto('/transactions');
      await expect(page.locator(`table.listing tbody a[href="/member/${ids.han}"]`)).toHaveCount(0);

      //his group only
      await page.goto('/groups');
      const groups = await page.locator('a[href*="/group/edit/"]').evaluateAll(
        links => [...new Set(links.map(link => link.getAttribute('href')))]
      );
      expect(groups).toHaveLength(1);
      await AclHelper.expectAllowed(page, groups[0] as string);

      //another manager, on his own group
      const other = await loggedInAs('otherGroupManager');
      await AclHelper.expectAllowed(other, `/member/edit/${ids.zia}`);
      await AclHelper.expectRefused(other, `/member/edit/${ids.luke}`);
    } finally {
      await AclHelper.setPermissions(admin, SYSTEM_ROLES.groupManager, previous);
    }
  });

  test('Roles changes - Members allowed to add children', async ({ loggedInAs }) => {
    const admin = await loggedInAs('admin');
    const previous = await AclHelper.permissionsOf(admin, SYSTEM_ROLES.member, ['member:create-child']);
    let child: number | undefined;

    try {
      await AclHelper.setPermissions(admin, SYSTEM_ROLES.member, { 'member:create-child': true });
      const page = await loggedInAs('member');
      await AclHelper.expectMenu(page, ['/member/add/child'], ['/member/add']);
      await AclHelper.expectRefused(page, '/member/add');

      await AclHelper.expectAllowed(page, '/member/add/child');
      const stamp = Date.now();
      await page.locator('input#nom_adh').fill('E2E');
      await page.locator('input#prenom_adh').fill(`Child ${stamp}`);
      await page.locator('input#login_adh').fill(`e2e.child.${stamp}`);
      await page.locator('input#mdp_adh').fill('G@l3tte-E2E-child!');
      await page.locator('input#mdp_adh2').fill('G@l3tte-E2E-child!');
      await Promise.all([
        page.waitForURL(/\/member\/\d+$/),
        page.locator('button[type="submit"][name="valid"]').first().click(),
      ]);
      child = Number(page.url().match(/\/member\/(\d+)$/)?.[1]);
      expect(child).not.toBe(ids.luke);
      await expect(page.locator(`main a[href="/member/${ids.luke}"]`).first()).toBeVisible();
    } finally {
      if (child !== undefined) {
        await admin.goto(`/member/remove/${child}`);
        await admin.locator('input#delete').click();
        await admin.waitForURL(/\/members/);
      }
      await AclHelper.setPermissions(admin, SYSTEM_ROLES.member, previous);
    }
  });

  test('Roles changes - A role taken back applies at once', async ({ loggedInAs }) => {
    const sally = await loggedInAs('groupSecretary');
    await AclHelper.expectAllowed(sally, `/member/${ids.victor}`);

    const admin = await loggedInAs('admin');
    const secretariat = await AclHelper.roleId(admin, GROUP_SECRETARIAT);

    try {
      await AclHelper.takeRole(admin, secretariat, ids.sally);
      //same session, nothing to log in again
      await AclHelper.expectRefused(sally, `/member/${ids.victor}`);
      await AclHelper.expectRefused(sally, '/members');
    } finally {
      //given back only if it was taken: the test may have failed before
      await admin.goto(`/roles/edit/${secretariat}`);
      if (await AclHelper.roleAssignments(admin, ids.sally).count() === 0) {
        await AclHelper.giveRole(admin, secretariat, ids.sally, LOST_SOULS);
      }
      await expect(AclHelper.roleAssignments(admin, ids.sally)).toHaveCount(1);
    }
  });

  test('Roles changes - A permission taken back applies at once', async ({ loggedInAs }) => {
    const hermione = await loggedInAs('treasury');
    await AclHelper.expectContributionsOf(hermione, ids.han);

    const admin = await loggedInAs('admin');
    const treasury = await AclHelper.roleId(admin, TREASURY);
    const previous = await AclHelper.permissionsOf(admin, treasury, ['contribution:read']);

    try {
      await AclHelper.setPermissions(admin, treasury, { 'contribution:read': false });
      await AclHelper.expectNoContributionsOf(hermione, ids.han, NAMES.han);
    } finally {
      await AclHelper.setPermissions(admin, treasury, previous);
    }
  });

  test('Roles changes - Saving preferences keeps rights to roles', async ({ loggedInAs }) => {
    const admin = await loggedInAs('admin');
    await admin.goto('/preferences');
    await Promise.all([
      admin.waitForEvent('load'),
      admin.locator('form button[type="submit"].primary.action').click(),
    ]);
    await expect(admin.locator('.ui.toast.error')).toHaveCount(0);
    await expect(admin.locator('.ui.toast.success')).toBeVisible();

    await admin.goto('/preferences?tab=rights');
    await expect(admin.locator('.tab[data-tab="rights"] input')).toHaveCount(0);
  });
});

/**
 * Select members on the list and open the mailing form for them, as the list
 * "Mail" batch action does
 *
 * @param forged - identifiers posted although not shown in the list
 */
async function mailFromList(page: Page, memberIds: number[], forged: number[] = []): Promise<void> {
  await page.goto('/members');
  await page.evaluate(([ids, extra]) => {
    const form = document.querySelector<HTMLFormElement>('#listform');
    if (!form) {
      throw new Error('members list form not found');
    }
    form.querySelectorAll<HTMLInputElement>('input[name="entries_sel[]"]').forEach(box => {
      box.checked = ids.includes(Number(box.value));
    });
    const add = (name: string, value: string) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    };
    extra.forEach(id => add('entries_sel[]', String(id)));
    ['sendmail', 'mailing_new', 'mailing'].forEach(name => add(name, 'true'));
    form.submit();
  }, [memberIds, forged] as const);
  await page.waitForURL(/\/mailing(\?|$)/);
}

/**
 * Write and send the mailing opened on the page; wait for the queue to be drained
 */
async function sendMailing(page: Page, subject: string): Promise<void> {
  await page.locator('#mailing_objet').fill(subject);
  await page.locator('#mailing_corps').fill('E2E roles mailing');
  await Promise.all([
    page.waitForURL(/\/mailing\/queue\/\d+/),
    page.locator('button[name="mailing_confirm"]').click(),
  ]);
  await expect(page.locator('#queue_remaining')).toHaveText('0', { timeout: 30000 });
}

test.describe.serial('Roles changes - Mailings', () => {
  test.describe.configure({ timeout: 120000 });

  let mailpit: APIRequestContext | null = null;
  let mailpitAvailable = false;

  /**
   * Addresses that received a mail with this subject, To, Cc and Bcc alike;
   * the sender is left out, it is the To of mails sent in blind copy
   */
  async function recipients(subject: string): Promise<string[]> {
    const response = await (mailpit as APIRequestContext).get('/api/v1/search', {
      params: { query: `subject:"${subject}"`, limit: 200 },
    });
    const body = await response.json();
    const found = new Set<string>();
    for (const message of body.messages ?? []) {
      const sender = String(message.From?.Address ?? '').toLowerCase();
      for (const field of ['To', 'Cc', 'Bcc']) {
        for (const address of message[field] ?? []) {
          const recipient = String(address.Address).toLowerCase();
          if (recipient !== sender) {
            found.add(recipient);
          }
        }
      }
    }
    return [...found].sort();
  }

  test.beforeAll(async () => {
    try {
      mailpit = await request.newContext({ baseURL: MAILPIT_URL });
      mailpitAvailable = (await mailpit.get('/readyz', { timeout: 3000 })).ok();
    } catch {
      mailpitAvailable = false;
    }
  });

  test.afterAll(async () => {
    await mailpit?.dispose();
  });

  test.beforeEach(async ({ page }) => {
    test.skip(!mailpitAvailable, 'Mailpit is not reachable');
    //fixtures have mail disabled: send to Mailpit, in one batch
    const response = await page.request.post('/test/preferences', {
      data: { action: 'configure_mail', host: '127.0.0.1', port: 1025, batch_size: 50, daily_limit: 1000 },
    });
    expect(response.ok()).toBeTruthy();
  });

  test.afterEach(async ({ page }) => {
    if (mailpitAvailable) {
      await page.request.post('/test/preferences', { data: { action: 'reset_mail' } });
    }
  });

  test('Roles changes - Staff mails any member, and reads the history', async ({ loggedInAs }) => {
    const page = await loggedInAs('staff');
    const subject = `E2E staff ${Date.now()}`;

    await mailFromList(page, [], [ids.han, ids.luke]);
    await sendMailing(page, subject);
    await expect.poll(() => recipients(subject), { timeout: 15000 })
      .toEqual(['han.solo@faucon-millenium.net', 'luke.skywalker@rebellion.org']);

    await AclHelper.expectAllowed(page, '/mailings');
    await expect(page.locator('table.listing tbody', { hasText: subject })).toHaveCount(1);
  });

  test('Roles changes - Group secretariat mails the group only', async ({ loggedInAs }) => {
    const page = await loggedInAs('groupSecretary');
    const subject = `E2E secretariat ${Date.now()}`;

    //han is not in the group: posted anyway, he is dropped
    await mailFromList(page, [ids.victor, ids.emily], [ids.han]);
    await sendMailing(page, subject);
    await expect.poll(() => recipients(subject), { timeout: 15000 })
      .toEqual(['emily@noces-funebres.mort', 'victor@noces-funebres.mort']);
  });

  test('Roles changes - Group managers given mailings mail their members only', async ({ loggedInAs }) => {
    const anakin = await loggedInAs('groupManager');
    await AclHelper.expectRefused(anakin, '/mailing');

    const admin = await loggedInAs('admin');
    const previous = await AclHelper.permissionsOf(admin, SYSTEM_ROLES.groupManager, ['mailing:send']);

    try {
      await AclHelper.setPermissions(admin, SYSTEM_ROLES.groupManager, { 'mailing:send': true });
      const subject = `E2E manager ${Date.now()}`;
      await mailFromList(anakin, [ids.luke, ids.ben], [ids.han]);
      await sendMailing(anakin, subject);
      await expect.poll(() => recipients(subject), { timeout: 15000 })
        .toEqual(['ben.skywalker@rebellion.org', 'luke.skywalker@rebellion.org']);
    } finally {
      await AclHelper.setPermissions(admin, SYSTEM_ROLES.groupManager, previous);
    }
  });

  test('Roles changes - No mailing without the permission', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasury');
    await AclHelper.expectRefused(page, '/mailing');
    await AclHelper.expectRefused(page, '/mailings');
  });
});
