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

import { type Page, expect } from '@playwright/test';

/**
 * Helper functions for flash messages (success, error, warning)
 */
export class FlashMessageHelper {

  /**
   * Wait for and assert success message is visible
   * @param page - Playwright Page object
   * @param messagePattern - Optional text pattern to match
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectSuccess(page: Page, messagePattern?: string, timeout: number = 5000): Promise<void> {
    const flash = page.locator('.ui.success.message, .ui.positive.message, .message.success');
    await flash.waitFor({ state: 'visible', timeout });

    if (messagePattern) {
      await expect(flash).toContainText(messagePattern);
    }
  }

  /**
   * Wait for and assert error message is visible
   * @param page - Playwright Page object
   * @param messagePattern - Optional text pattern to match
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectError(page: Page, messagePattern?: string, timeout: number = 5000): Promise<void> {
    const flash = page.locator('.ui.error.message, .ui.negative.message, .message.error');
    await flash.waitFor({ state: 'visible', timeout });

    if (messagePattern) {
      await expect(flash).toContainText(messagePattern);
    }
  }

  /**
   * Wait for and assert warning message is visible
   * @param page - Playwright Page object
   * @param messagePattern - Optional text pattern to match
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectWarning(page: Page, messagePattern?: string, timeout: number = 5000): Promise<void> {
    const flash = page.locator('.ui.warning.message, .message.warning');
    await flash.waitFor({ state: 'visible', timeout });

    if (messagePattern) {
      await expect(flash).toContainText(messagePattern);
    }
  }

  /**
   * Wait for and assert info message is visible
   * @param page - Playwright Page object
   * @param messagePattern - Optional text pattern to match
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectInfo(page: Page, messagePattern?: string, timeout: number = 5000): Promise<void> {
    const flash = page.locator('.ui.info.message, .message.info');
    await flash.waitFor({ state: 'visible', timeout });

    if (messagePattern) {
      await expect(flash).toContainText(messagePattern);
    }
  }

  /**
   * Check if any flash message is visible
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   * @returns True if any message is visible
   */
  static async hasMessage(page: Page, timeout: number = 2000): Promise<boolean> {
    const flash = page.locator('.ui.message, .message');
    return await flash.isVisible({ timeout }).catch(() => false);
  }

  /**
   * Close/dismiss flash message
   * @param page - Playwright Page object
   */
  static async dismiss(page: Page): Promise<void> {
    const closeButton = page.locator('.ui.message .close.icon, .message .close');
    if (await closeButton.isVisible({ timeout: 1000 })) {
      await closeButton.click();
    }
  }
}

