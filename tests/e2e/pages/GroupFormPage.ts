/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    // Narrow the listing down to the wanted member
    await this.page.locator('#members_search').fill(memberName);
    await this.page.locator('#members_search_btn').click();

    // Click on the row containing the member name
    const memberRow = this.page.locator('#listing tbody tr').filter({ hasText: memberName });
    await memberRow.first().click();

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
   *
   * The selection is stored right away, so this waits for the success toast.
   */
  async validateMembersModal(): Promise<void> {
    await this.page.locator('#btnvalid').click();

    // Wait for modal to close
    await this.page.locator('.ui.modal.members-selection.visible').waitFor({ state: 'hidden' });

    // Selection is stored on the fly, a success toast is displayed
    await this.page.locator('.ui.toast.success').waitFor({ state: 'visible', timeout: 10000 });
  }

  /**
   * Get the members count displayed in the section header
   */
  async getMembersCount(): Promise<number> {
    return parseInt(await this.page.locator('#group_members .persons-count').innerText());
  }

  /**
   * Get the managers count displayed in the section header
   */
  async getManagersCount(): Promise<number> {
    return parseInt(await this.page.locator('#group_managers .persons-count').innerText());
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

