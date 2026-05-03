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
