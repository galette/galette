/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Browser, type Locator, type Page, expect } from '@playwright/test';
import { logIn } from '../fixtures/auth.fixture';
import { MemberListPage } from '../pages/MemberListPage';

/**
 * Identifiers of the system roles, set by the installer
 */
export const SYSTEM_ROLES = {
  member: 1,
  upToDate: 2,
  groupManager: 3,
  staff: 4,
  admin: 5,
} as const;

/**
 * Helpers for access control: menus, refused pages, roles permissions
 */
export class AclHelper {

  /**
   * Link of the main menu to a path; folded entries are in the DOM, but hidden
   */
  static menuLink(page: Page, path: string): Locator {
    return page.locator(`#sidemenu nav.vertical.menu a[href="${path}"]`);
  }

  /**
   * Assert the main menu has, or has not, a link to each path
   */
  static async expectMenu(page: Page, present: string[], absent: string[] = []): Promise<void> {
    for (const path of present) {
      await expect(AclHelper.menuLink(page, path), `menu link to ${path}`).toHaveCount(1);
    }
    for (const path of absent) {
      await expect(AclHelper.menuLink(page, path), `menu link to ${path}`).toHaveCount(0);
    }
  }

  /**
   * Open a path, which must be refused: the member is sent elsewhere, with an error
   */
  static async expectRefused(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await expect(page, `${path} must be refused`).not.toHaveURL(new RegExp(`${AclHelper.escape(path)}$`));
    await expect(page.locator('.ui.toast.error'), `${path} must show an error`).toBeVisible();
  }

  /**
   * Open a path, which must be displayed
   */
  static async expectAllowed(page: Page, path: string): Promise<void> {
    const response = await page.goto(path);
    expect(response?.status(), `${path} status`).toBeLessThan(400);
    await expect(page, `${path} must be displayed`).toHaveURL(new RegExp(`${AclHelper.escape(path)}$`));
    await expect(page.locator('.ui.toast.error'), `${path} must not show an error`).toHaveCount(0);
  }

  /**
   * Open the contributions of a member, which must be listed
   */
  static async expectContributionsOf(page: Page, memberId: number): Promise<void> {
    await AclHelper.expectAllowed(page, `/contributions/member/${memberId}`);
    await expect(page.locator(`.member-filtered a[href="/member/${memberId}"]`), 'list filtered on the member')
      .not.toHaveCount(0);
    await expect(page.locator('table.listing tbody tr[id^="row_"]'), 'contributions listed').not.toHaveCount(0);
  }

  /**
   * Open the contributions of a member, which must not be listed: the list is not
   * filtered on that member, nor shows any of his contributions
   *
   * @param name - member name, as displayed
   */
  static async expectNoContributionsOf(page: Page, memberId: number, name: string): Promise<void> {
    await page.goto(`/contributions/member/${memberId}`);
    const filtered = page.locator('.member-filtered');
    if (await filtered.count() > 0) {
      await expect(filtered, 'list must not be filtered on the member').not.toContainText(name);
    }
    await expect(page.locator(`table.listing tbody a[href="/member/${memberId}"]`), 'contributions of the member')
      .toHaveCount(0);
  }

  /**
   * Identifiers of the members displayed in the current members list page
   */
  static async listedMembers(page: Page): Promise<number[]> {
    const hrefs = await page.locator('table.listing tbody td.username_row a[href*="/member/"]')
      .evaluateAll(links => links.map(link => link.getAttribute('href') ?? ''));
    return hrefs
      .map(href => href.match(/\/member\/(\d+)$/))
      .filter(match => match !== null)
      .map(match => Number(match[1]));
  }

  /**
   * Identifiers of fixture members, looked up as the super administrator
   *
   * @param names - key => name as displayed in the members list, like "SKYWALKER Luke"
   */
  static async memberIds<K extends string>(browser: Browser, names: Record<K, string>): Promise<Record<K, number>> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await logIn(page, 'superadmin');

    const list = new MemberListPage(page);
    await list.goto();
    const ids = {} as Record<K, number>;
    for (const [key, name] of Object.entries(names) as [K, string][]) {
      //filter on the longest word without accent, the exact name is matched below
      const words = name.split(' ').filter(word => /^[A-Za-z]+$/.test(word));
      words.sort((a, b) => b.length - a.length);
      await list.filterByName(words[0] ?? name);
      const link = page.locator('table.listing tbody td.username_row a[href*="/member/"]')
        .filter({ hasText: new RegExp(String.raw`^\s*${AclHelper.escape(name)}\s*$`) });
      await expect(link, `member "${name}"`).toHaveCount(1);
      const match = (await link.getAttribute('href'))?.match(/\/member\/(\d+)$/);
      expect(match, `link to member "${name}"`).not.toBeNull();
      ids[key] = Number((match as RegExpMatchArray)[1]);
    }
    await list.clearFilter();
    await context.close();

    return ids;
  }

  /**
   * Permissions a role holds, read before changing them so they can be restored
   *
   * @param page   - page logged in with the right to manage roles
   * @param roleId - role identifier
   * @param names  - permissions names
   *
   * @returns permission name => granted or not
   */
  static async permissionsOf(page: Page, roleId: number, names: string[]): Promise<Record<string, boolean>> {
    await page.goto(`/roles/edit/${roleId}`);

    const states: Record<string, boolean> = {};
    for (const name of names) {
      const input = page.locator(`input[name="permissions[${name}]"]`);
      await expect(input, `permission ${name}`).toHaveCount(1);
      states[name] = await input.isChecked();
    }
    return states;
  }

  /**
   * Change permissions of a role, from its edition page; call it inside the
   * try block that restores them, it saves before checking what was saved
   *
   * @param page   - page logged in with the right to manage roles
   * @param roleId - role identifier
   * @param wanted - permission name => granted or not
   */
  static async setPermissions(page: Page, roleId: number, wanted: Record<string, boolean>): Promise<void> {
    await page.goto(`/roles/edit/${roleId}`);

    for (const [name, granted] of Object.entries(wanted)) {
      //toggles are styled by Fomantic, and folded with their domain: set the input itself
      const input = page.locator(`input[name="permissions[${name}]"]`);
      await expect(input, `permission ${name}`).toHaveCount(1);
      await expect(input, `permission ${name} must not be inherited`).toBeEnabled();
      await input.evaluate((element: HTMLInputElement, value: boolean) => {
        element.checked = value;
      }, granted);
    }

    await Promise.all([
      page.waitForEvent('load'),
      page.locator('form.ui.form button[type="submit"].primary').click(),
    ]);
    await expect(page.locator('.ui.toast.error')).toHaveCount(0);

    await page.goto(`/roles/edit/${roleId}`);
    for (const [name, granted] of Object.entries(wanted)) {
      await expect(page.locator(`input[name="permissions[${name}]"]`), `permission ${name} saved`)
        .toBeChecked({ checked: granted });
    }
  }

  /**
   * Identifier of a custom role, from the roles list
   */
  static async roleId(page: Page, name: string): Promise<number> {
    await page.goto('/roles');
    const href = await page.locator('table.listing tbody tr').filter({ hasText: name })
      .locator('a[href*="/roles/edit/"]').getAttribute('href');
    const match = href?.match(/\/roles\/edit\/(\d+)$/);
    expect(match, `role "${name}"`).toBeTruthy();
    return Number((match as RegExpMatchArray)[1]);
  }

  /**
   * Give a custom role to a member, from the role page
   *
   * @param group - group name, all groups when missing
   */
  static async giveRole(page: Page, roleId: number, memberId: number, group?: string): Promise<void> {
    await page.goto(`/roles/edit/${roleId}`);
    const form = page.locator('#role_members form[action$="/members"]');
    //member and group fields are Fomantic dropdowns, the member one searching with ajax: set the values
    await form.locator('input[name="id_adh"]').evaluate((input: HTMLInputElement, value: string) => {
      input.value = value;
    }, String(memberId));
    if (group !== undefined) {
      await form.locator('select#id_group').evaluate((select: HTMLSelectElement, name: string) => {
        const option = Array.from(select.options).find(candidate => candidate.text.trim() === name);
        select.value = option?.value ?? '';
      }, group);
    }
    await Promise.all([
      page.waitForURL(new RegExp(`/roles/edit/${roleId}$`)),
      form.locator('button[type="submit"]').click(),
    ]);
  }

  /**
   * Assignments of a custom role, on its page
   */
  static roleAssignments(page: Page, memberId?: number): Locator {
    const rows = page.locator('#role_members tbody tr:has(form[action*="/members/remove/"])');
    return memberId === undefined ? rows : rows.filter({ has: page.locator(`a[href="/member/${memberId}"]`) });
  }

  /**
   * Take every assignment of a custom role back from a member
   */
  static async takeRole(page: Page, roleId: number, memberId: number): Promise<void> {
    await page.goto(`/roles/edit/${roleId}`);
    while (await AclHelper.roleAssignments(page, memberId).count() > 0) {
      await Promise.all([
        page.waitForURL(new RegExp(`/roles/edit/${roleId}$`)),
        AclHelper.roleAssignments(page, memberId).first().locator('button[type="submit"]').click(),
      ]);
      await expect(page.locator('.ui.toast.success')).toBeVisible();
    }
  }

  private static escape(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, String.raw`\$&`);
  }
}
