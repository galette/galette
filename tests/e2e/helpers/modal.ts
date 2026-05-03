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

import { type Page, type Locator, expect } from '@playwright/test';

/**
 * Helper functions for Fomantic UI modal interactions
 */
export class ModalHelper {

  /**
   * Wait for modal to open and return locator
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   * @returns Locator for the active modal
   */
  static async waitForOpen(page: Page, timeout: number = 10000): Promise<Locator> {
    const modal = page.locator('.ui.modal.active, .ui.modal.visible');
    await modal.waitFor({ state: 'visible', timeout });
    return modal;
  }

  /**
   * Click the confirm/approve button in modal
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   */
  static async clickConfirm(page: Page, timeout: number = 10000): Promise<void> {
    const modal = await this.waitForOpen(page, timeout);
    const confirmButton = modal.locator('button.approve, button.positive, button.ok');
    await confirmButton.click();

    // Wait for modal to close
    await modal.waitFor({ state: 'hidden', timeout });
  }

  /**
   * Click the cancel/deny button in modal
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   */
  static async clickCancel(page: Page, timeout: number = 10000): Promise<void> {
    const modal = await this.waitForOpen(page, timeout);
    const cancelButton = modal.locator('button.deny, button.negative, button.cancel');
    await cancelButton.click();
  }

  /**
   * Assert modal contains specific text
   * @param page - Playwright Page object
   * @param text - Text to find in modal
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectModalText(page: Page, text: string, timeout: number = 10000): Promise<void> {
    const modal = await this.waitForOpen(page, timeout);
    await expect(modal).toContainText(text);
  }

  /**
   * Close modal by clicking dimmer (background)
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   */
  static async clickDimmer(page: Page, timeout: number = 10000): Promise<void> {
    const dimmer = page.locator('.ui.dimmer.active');
    await dimmer.waitFor({ state: 'visible', timeout });
    await dimmer.click({ position: { x: 10, y: 10 } }); // Click top-left corner
  }

  /**
   * Check if modal is visible
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   * @returns True if modal is visible
   */
  static async isVisible(page: Page, timeout: number = 2000): Promise<boolean> {
    const modal = page.locator('.ui.modal.active, .ui.modal.visible');
    return await modal.isVisible({ timeout }).catch(() => false);
  }
}

