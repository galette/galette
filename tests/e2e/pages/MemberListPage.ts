/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import type { Page, Locator } from '@playwright/test';

/**
 * Page Object for the members list page (/members).
 *
 * Encapsulates selectors and interactions so that specs stay readable
 * and selector changes need to be fixed in one place only.
 */
export class MemberListPage {
  readonly page: Page;
  readonly filterInput: Locator;
  readonly filterButton: Locator;
  readonly clearFilterButton: Locator;
  readonly addMemberButton: Locator;
  readonly memberTable: Locator;

  constructor(page: Page) {
    this.page             = page;
    this.filterInput      = page.locator('#filter_str');
    this.filterButton     = page.locator('button[name="filter"]');
    this.clearFilterButton = page.locator('button[name="clear_filter"]');
    this.addMemberButton  = page.locator('a[href*="/member/add"]').first();
    this.memberTable      = page.locator('table.listing');
  }

  async goto(): Promise<void> {
    await this.page.goto('/members');
  }

  async filterByName(name: string): Promise<void> {
    await this.filterInput.fill(name);
    await this.filterButton.click();
    // Wait for the table to reflect the filtered results
    await this.memberTable.waitFor({ state: 'visible' });
  }

  async clearFilter(): Promise<void> {
    await this.clearFilterButton.click();
    await this.memberTable.waitFor({ state: 'visible' });
  }

  /** Locator for a member row matched by visible name text (case-sensitive). */
  getMemberRowByName(name: string): Locator {
    return this.memberTable.locator('tbody tr').filter({ hasText: name });
  }

  /** All data rows in the member table (excludes empty-list placeholder rows). */
  getDataRows(): Locator {
    return this.memberTable.locator('tbody tr:not(.emptylist)');
  }
}
