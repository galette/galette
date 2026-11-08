/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Locator, type Page, expect } from '@playwright/test';

/**
 * Helper functions for flash messages (success, error, warning)
 */
export class FlashMessageHelper {

  /**
   * First visible flash message matching a selector
   *
   * Page scripts turn inline flash messages into Fomantic toasts: look for
   * both, the inline one being the only one left without JavaScript.
   */
  private static visible(page: Page, selector: string): Locator {
    return page.locator(selector).filter({ visible: true }).first();
  }

  /**
   * Wait for and assert success message is visible
   * @param page - Playwright Page object
   * @param messagePattern - Optional text pattern to match
   * @param timeout - Optional timeout in milliseconds
   */
  static async expectSuccess(page: Page, messagePattern?: string, timeout: number = 5000): Promise<void> {
    const flash = FlashMessageHelper.visible(page, '.ui.toast.success, .ui.success.message, .ui.positive.message, .message.success');
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
    const flash = FlashMessageHelper.visible(page, '.ui.toast.error, .ui.error.message, .ui.negative.message, .message.error');
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
    const flash = FlashMessageHelper.visible(page, '.ui.toast.warning, .ui.warning.message, .message.warning');
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
    const flash = FlashMessageHelper.visible(page, '.ui.toast.info, .ui.info.message, .message.info');
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

