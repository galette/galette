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
 * Helper functions for Fomantic UI dropdown interactions
 */
export class DropdownHelper {

  /**
   * Select the first item in a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   */
  static async selectFirst(page: Page, dropdownId: string): Promise<void> {
    await page.locator(`#${dropdownId}`).click();
    const firstItem = page.locator(`#${dropdownId} .menu .item`).first();
    await firstItem.waitFor({ state: 'visible' });
    await firstItem.click();
  }

  /**
   * Select an item by its text in a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   * @param text - The text of the item to select
   */
  static async selectByText(page: Page, dropdownId: string, text: string): Promise<void> {
    await page.locator(`#${dropdownId}`).click();
    const item = page.locator(`#${dropdownId} .menu .item:has-text("${text}")`);
    await item.waitFor({ state: 'visible' });
    await item.click();
  }

  /**
   * Select an item by its index in a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   * @param index - The index of the item (0-based)
   */
  static async selectByIndex(page: Page, dropdownId: string, index: number): Promise<void> {
    await page.locator(`#${dropdownId}`).click();
    const item = page.locator(`#${dropdownId} .menu .item`).nth(index);
    await item.waitFor({ state: 'visible' });
    await item.click();
  }

  /**
   * Clear the selection in a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   */
  static async clear(page: Page, dropdownId: string): Promise<void> {
    const clearIcon = page.locator(`#${dropdownId} .clear.icon`);
    const isVisible = await clearIcon.isVisible();

    if (isVisible) {
      await clearIcon.click();
    }
  }

  /**
   * Get the currently selected text from a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   * @returns The text of the selected item
   */
  static async getSelectedText(page: Page, dropdownId: string): Promise<string> {
    const selectedText = await page.locator(`#${dropdownId} .text`).textContent();
    return selectedText?.trim() || '';
  }

  /**
   * Check if a dropdown is enabled
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   * @returns True if enabled, false otherwise
   */
  static async isEnabled(page: Page, dropdownId: string): Promise<boolean> {
    const dropdown = page.locator(`#${dropdownId}`);
    const hasDisabledClass = await dropdown.evaluate((el) => el.classList.contains('disabled'));
    return !hasDisabledClass;
  }

  /**
   * Get all available options from a Fomantic UI dropdown
   * @param page - Playwright Page object
   * @param dropdownId - The ID of the dropdown element (without #)
   * @returns Array of option texts
   */
  static async getOptions(page: Page, dropdownId: string): Promise<string[]> {
    await page.locator(`#${dropdownId}`).click();
    const items = page.locator(`#${dropdownId} .menu .item`);
    const count = await items.count();
    const options: string[] = [];

    for (let i = 0; i < count; i++) {
      const text = await items.nth(i).textContent();
      if (text) {
        options.push(text.trim());
      }
    }

    // Close the dropdown
    await page.locator(`#${dropdownId}`).click();

    return options;
  }
}

