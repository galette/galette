/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Page, type Locator } from '@playwright/test';

/**
 * Helper functions for table (listing) interactions
 */
export class TableHelper {

  /**
   * Get the main listing table
   * @param page - Playwright Page object
   * @param selector - Optional custom table selector
   * @returns Locator for the table
   */
  static getTable(page: Page, selector: string = 'table.listing'): Locator {
    return page.locator(selector);
  }

  /**
   * Get count of data rows (excluding empty list placeholders)
   * @param page - Playwright Page object
   * @param tableSelector - Optional custom table selector
   * @returns Number of data rows
   */
  static async getRowCount(page: Page, tableSelector: string = 'table.listing'): Promise<number> {
    const rows = this.getTable(page, tableSelector).locator('tbody tr:not(.emptylist)');
    return await rows.count();
  }

  /**
   * Get a table row by text content
   * @param page - Playwright Page object
   * @param text - Text to search for in row
   * @param tableSelector - Optional custom table selector
   * @returns Locator for the matching row
   */
  static getRowByText(page: Page, text: string, tableSelector: string = 'table.listing'): Locator {
    return this.getTable(page, tableSelector).locator('tbody tr').filter({ hasText: text });
  }

  /**
   * Click an action button in a specific row
   * @param page - Playwright Page object
   * @param rowText - Text to identify the row
   * @param actionSelector - Selector for the action (e.g., '.edit', 'a[href*="/delete"]')
   * @param tableSelector - Optional custom table selector
   */
  static async clickAction(page: Page, rowText: string, actionSelector: string, tableSelector: string = 'table.listing'): Promise<void> {
    const row = this.getRowByText(page, rowText, tableSelector);
    await row.locator(actionSelector).click();
  }

  /**
   * Get all data rows
   * @param page - Playwright Page object
   * @param tableSelector - Optional custom table selector
   * @returns Locator for all data rows
   */
  static getDataRows(page: Page, tableSelector: string = 'table.listing'): Locator {
    return this.getTable(page, tableSelector).locator('tbody tr:not(.emptylist)');
  }

  /**
   * Check if table is empty (has no data rows)
   * @param page - Playwright Page object
   * @param tableSelector - Optional custom table selector
   * @returns True if table is empty
   */
  static async isEmpty(page: Page, tableSelector: string = 'table.listing'): Promise<boolean> {
    const count = await this.getRowCount(page, tableSelector);
    return count === 0;
  }

  /**
   * Get cell value by row text and column index
   * @param page - Playwright Page object
   * @param rowText - Text to identify the row
   * @param columnIndex - 0-based column index
   * @param tableSelector - Optional custom table selector
   * @returns Cell text content
   */
  static async getCellValue(page: Page, rowText: string, columnIndex: number, tableSelector: string = 'table.listing'): Promise<string> {
    const row = this.getRowByText(page, rowText, tableSelector);
    const cell = row.locator('td').nth(columnIndex);
    return await cell.textContent() || '';
  }
}

