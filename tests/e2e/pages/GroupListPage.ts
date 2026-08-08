/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Page, type Locator } from '@playwright/test';

/**
 * Page Object for Groups List Page
 */
export class GroupListPage {
  readonly page: Page;
  readonly groupsTable: Locator;
  readonly newGroupButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.groupsTable = page.locator('table.listing');
    this.newGroupButton = page.locator('#newgroup');
  }

  /**
   * Navigate to groups list page
   */
  async goto(): Promise<void> {
    await this.page.goto('/groups');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get all group rows in the table
   */
  getGroupRows(): Locator {
    return this.groupsTable.locator('tbody tr');
  }

  /**
   * Get a specific group row by name
   */
  getGroupRowByName(name: string): Locator {
    return this.groupsTable.locator(`tbody tr:has-text("${name}")`);
  }

  /**
   * Click the "New group" button and fill the modal
   */
  async createGroup(groupName: string): Promise<void> {
    await this.newGroupButton.click();
    
    // Wait for modal to appear
    await this.page.locator('.ui.modal.visible').waitFor({ state: 'visible' });
    
    // Fill in the group name
    await this.page.locator('#new_group_name').fill(groupName);
    
    // Click the Create button
    await this.page.locator('.ui.modal .approve.button').click();
    
    // Wait for navigation to edit page
    await this.page.waitForURL(/\/group\/edit\/\d+/);
  }

  /**
   * Click edit button for a group by name
   */
  async editGroupByName(name: string): Promise<void> {
    const row = this.getGroupRowByName(name);
    await row.locator('a[href*="/group/edit/"]').click();
    await this.page.waitForURL(/\/group\/edit\/\d+/);
  }

  /**
   * Get the "display members" (or managers) button of a group
   */
  getPersonsButtonByName(name: string, mode: 'members' | 'managers'): Locator {
    return this.getGroupRowByName(name).locator(`a.group-persons[data-mode="${mode}"]`);
  }

  /**
   * Open the members (or managers) modal of a group from the list
   */
  async openPersonsModal(name: string, mode: 'members' | 'managers'): Promise<void> {
    await this.getPersonsButtonByName(name, mode).click();
    await this.page.locator('.ui.modal.group-persons-view.visible').waitFor({ state: 'visible' });
  }

  /**
   * Switch the persons modal to the selection interface
   */
  async manageInPersonsModal(): Promise<void> {
    await this.page.locator('.ui.modal.group-persons-view .manage-persons').click();
    await this.page.locator('.ui.modal.members-selection.visible').waitFor({ state: 'visible' });
  }

  /**
   * Click delete button for a group by name
   */
  async deleteGroupByName(name: string): Promise<void> {
    const row = this.getGroupRowByName(name);
    await row.locator('a[href*="/group/remove/"]').click();
    await this.page.locator('.ui.modal').waitFor({ state: 'visible' });
  }

  /**
   * Get the count of groups in the table
   */
  async getGroupCount(): Promise<number> {
    return await this.getGroupRows().count();
  }

  /**
   * Check if a group exists by name
   */
  async hasGroup(name: string): Promise<boolean> {
    const count = await this.getGroupRowByName(name).count();
    return count > 0;
  }

  /**
   * Export all groups as PDF
   */
  async exportAllGroupsPDF(): Promise<void> {
    const exportButton = this.page.locator('a[href*="/pdf/groups"]').first();
    await exportButton.click();
  }
}

