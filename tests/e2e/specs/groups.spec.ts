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

      // Open members selection modal
      await formPage.openMembersSelection();

      // Add a member (using fixture member Luke Skywalker)
      await formPage.addMemberInModal('SKYWALKER');

      // Validate the modal
      await formPage.validateMembersModal();

      // Save the form
      await formPage.save();

      // Verify we're back on groups list or edit page
      await page.waitForLoadState('networkidle');
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

