/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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

