/**
 * Copyright © 2003-2026 The Galette Team
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
 */

import { type Page, type Locator } from '@playwright/test';

/**
 * Page Object for Group Form Page (Add/Edit)
 */
export class GroupFormPage {
  readonly page: Page;
  readonly groupNameInput: Locator;
  readonly saveButton: Locator;
  readonly cancelButton: Locator;
  readonly membersButton: Locator;
  readonly managersButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.groupNameInput = page.locator('input[name="group_name"]');
    this.saveButton = page.locator('button[type="submit"][name="valid"]');
    this.cancelButton = page.locator('a:has-text("Cancel"), a:has-text("Annuler")').first();
    this.membersButton = page.locator('#btnusers_small');
    this.managersButton = page.locator('#btnmanagers_small');
  }

  /**
   * Navigate to group edit page
   */
  async goto(groupId: number): Promise<void> {
    await this.page.goto(`/group/edit/${groupId}`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get the current group name
   */
  async getGroupName(): Promise<string> {
    return await this.groupNameInput.inputValue();
  }

  /**
   * Set the group name
   */
  async setGroupName(name: string): Promise<void> {
    await this.groupNameInput.clear();
    await this.groupNameInput.fill(name);
  }

  /**
   * Save the form
   */
  async save(): Promise<void> {
    await this.saveButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Cancel and go back to groups list
   */
  async cancel(): Promise<void> {
    await this.cancelButton.click();
    await this.page.waitForURL(/\/groups/);
  }

  /**
   * Open the members selection modal
   */
  async openMembersSelection(): Promise<void> {
    await this.membersButton.click();
    await this.page.locator('.ui.modal.members-selection.visible').waitFor({ state: 'visible' });
  }

  /**
   * Open the managers selection modal
   */
  async openManagersSelection(): Promise<void> {
    await this.managersButton.click();
    await this.page.locator('.ui.modal.members-selection.visible').waitFor({ state: 'visible' });
  }

  /**
   * Add a member in the members selection modal
   * Assumes modal is already open
   */
  async addMemberInModal(memberName: string): Promise<void> {
    await this.page.getByRole('link', { name: '2', exact: true }).click();
    // Click on the row containing the member name
    const memberRow = this.page.locator('#listing tbody tr').filter({ hasText: memberName });
    await memberRow.nth(1).click();

    // Verify the member was added to the selected list
    await this.page.locator(`#selected_members li:has-text("${memberName}")`).waitFor({ state: 'visible' });
  }

  /**
   * Remove a member from the selected list in the modal
   * Assumes modal is already open
   */
  async removeMemberInModal(memberName: string): Promise<void> {
    const memberItem = this.page.locator(`#selected_members li:has-text("${memberName}")`);
    await memberItem.click();
    await memberItem.waitFor({ state: 'hidden' });
  }

  /**
   * Validate the members selection modal
   */
  async validateMembersModal(): Promise<void> {
    await this.page.locator('#btnvalid').click();

    // Wait for modal to close
    await this.page.locator('.ui.modal.members-selection.visible').waitFor({ state: 'hidden' });

    // Wait for the yellow warning message indicating unsaved changes
    await this.page.locator('.ui.icon.yellow.message').waitFor({ state: 'visible', timeout: 5000 });
  }

  /**
   * Get the list of current members in the group
   */
  getMembersList(): Locator {
    return this.page.locator('#group_members table.listing tbody tr');
  }

  /**
   * Get the list of current managers in the group
   */
  getManagersList(): Locator {
    return this.page.locator('#group_managers table.listing tbody tr');
  }

  /**
   * Check if a member is in the group
   */
  async hasMember(memberName: string): Promise<boolean> {
    const members = this.getMembersList();
    const count = await members.filter({ hasText: memberName }).count();
    return count > 0;
  }
}

