/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Groups Tests - CRUD operations with isolated test data
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { GroupListPage } from '../pages/GroupListPage';
import { GroupFormPage } from '../pages/GroupFormPage';

// Generate unique group name with timestamp to avoid conflicts
const TEST_GROUP = {
  name: `E2E Test Group ${Date.now()}`,
};

test.describe('Groups', () => {

  test('Groups - Display list', async ({ loggedInPage: page }) => {
    const listPage = new GroupListPage(page);
    await listPage.goto();

    await expect(page).toHaveURL(/\/groups/);
    await expect(listPage.groupsTable).toBeVisible();
  });

  test('Groups - New group button visible for admin', async ({ loggedInPage: page }) => {
    const listPage = new GroupListPage(page);
    await listPage.goto();

    await expect(listPage.newGroupButton).toBeVisible();
  });

  // CRUD Operations (serial execution to maintain order)
  test.describe('Groups CRUD', () => {
    test.describe.configure({ mode: 'serial' });

    let createdGroupUrl = '';
    let createdGroupId = '';

    test('Groups - Create new group', async ({ loggedInPage: page }) => {
      const listPage = new GroupListPage(page);
      await listPage.goto();

      await listPage.createGroup(TEST_GROUP.name);

      // Should redirect to edit page
      await expect(page).toHaveURL(/\/group\/edit\/\d+/);
      createdGroupUrl = page.url();
      createdGroupId = createdGroupUrl.match(/\/group\/edit\/(\d+)$/)?.[1] || '';

      // Verify group name is in the form
      const formPage = new GroupFormPage(page);
      const groupName = await formPage.getGroupName();
      expect(groupName).toBe(TEST_GROUP.name);
    });

    test('Groups - Edit group name', async ({ loggedInPage: page }) => {
      const formPage = new GroupFormPage(page);
      await formPage.goto(parseInt(createdGroupId));

      const newName = `${TEST_GROUP.name} - Modified`;
      await formPage.setGroupName(newName);
      await formPage.save();

      // Verify we're still on a valid page (either edit or groups list)
      await page.waitForLoadState('networkidle');
      const url = page.url();
      const isValidUrl = url.includes('/groups') || url.includes('/group/edit/');
      expect(isValidUrl).toBeTruthy();
    });

    test('Groups - Add member to group', async ({ loggedInPage: page }) => {
      const formPage = new GroupFormPage(page);
      await formPage.goto(parseInt(createdGroupId));

      expect(await formPage.getMembersCount()).toBe(0);

      // Open members selection modal
      await formPage.openMembersSelection();

      // Add a member (using fixture member Luke Skywalker)
      await formPage.addMemberInModal('SKYWALKER');

      // Validate the modal; selection is stored right away
      await formPage.validateMembersModal();

      // Members table and count are refreshed without reloading the page
      expect(await formPage.getMembersCount()).toBe(1);
      await expect(formPage.getMembersList()).toHaveCount(1);

      // ... and it is persisted
      await formPage.goto(parseInt(createdGroupId));
      expect(await formPage.getMembersCount()).toBe(1);
      expect(await formPage.hasMember('SKYWALKER')).toBeTruthy();
    });

    test('Groups - Display members from the list', async ({ loggedInPage: page }) => {
      const listPage = new GroupListPage(page);
      await listPage.goto();

      const modifiedName = `${TEST_GROUP.name} - Modified`;
      await listPage.openPersonsModal(modifiedName, 'members');

      const modal = page.locator('.ui.modal.group-persons-view.visible');
      await expect(modal.locator('table.listing tbody tr')).toHaveCount(1);
      await expect(modal).toContainText('SKYWALKER');

      // Read-only listing, hidden inputs belong to the group form only
      await expect(modal.locator('input[name="members[]"]')).toHaveCount(0);

      // Switch to the selection interface and empty the group
      await listPage.manageInPersonsModal();
      await page.locator('#selected_members li[data-id]').first().click();
      await expect(page.locator('#selected_members li[data-id]')).toHaveCount(0);
      await page.locator('#btnvalid').click();

      await expect(page.locator('.ui.toast.success')).toBeVisible({ timeout: 10000 });

      // Group is now empty
      await listPage.goto();
      await listPage.openPersonsModal(modifiedName, 'members');
      await expect(page.locator('.ui.modal.group-persons-view.visible')).toContainText(/No member|Aucun membre/i);
    });

    test('Groups - Delete group', async ({ loggedInPage: page }) => {
      const listPage = new GroupListPage(page);
      await listPage.goto();

      // Find and delete the test group
      const modifiedName = `${TEST_GROUP.name} - Modified`;

      // Check if group exists (it might have been deleted in previous run)
      const hasGroup = await listPage.hasGroup(modifiedName);

      await listPage.deleteGroupByName(modifiedName);

      await page.getByRole('checkbox', { name: 'Cascade delete' }).click();
      await page.getByRole('button', { name: 'Remove' }).click();

      // Should redirect back to groups list
      await expect(page).toHaveURL(/\/groups/);

      await expect(page.locator('.ui.toast.success')).toBeVisible({ timeout: 10000 });
    });

  });

  test('Groups - Navigate to group details from list', async ({ loggedInPage: page }) => {
    const listPage = new GroupListPage(page);
    await listPage.goto();

    // Get the first group if any exists
    const groupRows = listPage.getGroupRows();
    const count = await groupRows.count();

    if (count > 0) {
      // Click edit on first group
      await groupRows.first().locator('a[href*="/group/edit/"]').click();
      await expect(page).toHaveURL(/\/group\/edit\/\d+/);
    }
  });

});

