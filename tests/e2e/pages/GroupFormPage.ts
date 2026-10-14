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
  readonly addMemberButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.groupNameInput = page.locator('input[name="group_name"]');
    this.saveButton = page.locator('button[type="submit"][name="valid"]');
    this.cancelButton = page.locator('a:has-text("Cancel"), a:has-text("Annuler")').first();
    this.addMemberButton = page.locator('#person_add_btn');
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
   * Add a member to the group, through the search dropdown and the add button
   */
  async addMember(search: string): Promise<void> {
    await this.page.locator('#person_add input.search').fill(search);
    const item = this.page.locator('#person_add .menu .item').first();
    await item.waitFor({ state: 'visible' });
    await item.click();
    await this.addMemberButton.click();
    await this.page.locator('#group_persons_list tr[data-linked="0"]').filter({ hasText: search }).waitFor({ state: 'visible' });
  }

  /**
   * Row of a person in the group, by its displayed name
   */
  getPersonRow(name: string): Locator {
    return this.page.locator('#group_persons_list tbody tr[data-person]').filter({ hasText: name });
  }

  /**
   * Get the list of current members in the group
   */
  getMembersList(): Locator {
    return this.page.locator('#group_persons_list tbody tr[data-person]').filter({
      has: this.page.locator('.person-member:checked'),
    });
  }

  /**
   * Get the list of current managers in the group
   */
  getManagersList(): Locator {
    return this.page.locator('#group_persons_list tbody tr[data-person]').filter({
      has: this.page.locator('.person-manager:checked'),
    });
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

