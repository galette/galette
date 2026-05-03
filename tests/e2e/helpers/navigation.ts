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

