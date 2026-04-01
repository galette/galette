/*!
 * Copyright © 2007-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Javascript
 * @package   Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
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

