/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { type Page, expect } from '@playwright/test';

/**
 * Helper functions for navigation and URL assertions
 */
export class NavigationHelper {

  /**
   * Navigate to a page and assert URL matches
   * @param page - Playwright Page object
   * @param path - Path to navigate to (e.g., '/members')
   * @param timeout - Optional timeout in milliseconds
   */
  static async goTo(page: Page, path: string, timeout: number = 30000): Promise<void> {
    await page.goto(path, { timeout });
    await expect(page).toHaveURL(new RegExp(path), { timeout });
  }

  /**
   * Assert current URL contains a segment
   * @param page - Playwright Page object
   * @param segment - URL segment to match
   */
  static async expectUrlContains(page: Page, segment: string): Promise<void> {
    await expect(page).toHaveURL(new RegExp(segment));
  }

  /**
   * Assert current URL matches exactly
   * @param page - Playwright Page object
   * @param url - Exact URL to match
   */
  static async expectUrl(page: Page, url: string): Promise<void> {
    await expect(page).toHaveURL(url);
  }

  /**
   * Wait for navigation to complete
   * @param page - Playwright Page object
   * @param timeout - Optional timeout in milliseconds
   */
  static async waitForNavigation(page: Page, timeout: number = 30000): Promise<void> {
    await page.waitForLoadState('networkidle', { timeout });
  }
}

