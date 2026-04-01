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

