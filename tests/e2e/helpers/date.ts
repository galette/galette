/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Page } from '@playwright/test';

/**
 * Helper functions for date input fields (Fomantic UI calendar)
 */
export class DateHelper {

  /**
   * Fill a date input field
   * @param page - Playwright Page object
   * @param fieldId - ID of the date input field (without #)
   * @param dateStr - Date string in format YYYY-MM-DD
   */
  static async fillDate(page: Page, fieldId: string, dateStr: string): Promise<void> {
    const input = page.locator(`#${fieldId}`);
    await input.fill(dateStr);

    // Close calendar popup if opened
    const calendar = page.locator('.ui.calendar.popup.active, .ui.calendar.visible');
    if (await calendar.isVisible({ timeout: 1000 }).catch(() => false)) {
      await page.keyboard.press('Escape');
    }
  }

  /**
   * Fill a date input field using locator
   * @param page - Playwright Page object
   * @param selector - CSS selector for the date input
   * @param dateStr - Date string in format YYYY-MM-DD
   */
  static async fillDateBySelector(page: Page, selector: string, dateStr: string): Promise<void> {
    const input = page.locator(selector);
    await input.fill(dateStr);

    // Close calendar popup if opened
    const calendar = page.locator('.ui.calendar.popup.active');
    if (await calendar.isVisible({ timeout: 1000 }).catch(() => false)) {
      await page.keyboard.press('Escape');
    }
  }

  /**
   * Get current value of date input
   * @param page - Playwright Page object
   * @param fieldId - ID of the date input field (without #)
   * @returns Date string value
   */
  static async getDate(page: Page, fieldId: string): Promise<string> {
    const input = page.locator(`#${fieldId}`);
    return await input.inputValue();
  }

  /**
   * Clear a date input field
   * @param page - Playwright Page object
   * @param fieldId - ID of the date input field (without #)
   */
  static async clearDate(page: Page, fieldId: string): Promise<void> {
    const input = page.locator(`#${fieldId}`);
    await input.clear();
  }
}

