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

  /**
   * Assert that the page has been redirected to a given URL pattern.
   * Replaces fragile `waitForTimeout` + `page.url()` patterns.
   *
   * @param page - Playwright Page object
   * @param expectedUrl - URL string, substring or RegExp to match
   * @param timeout - Optional timeout in milliseconds (default: 10000)
   *
   * @example
   * await page.goto('/public/members/list');
   * await NavigationHelper.expectRedirectTo(page, /\/login/);
   *
   * @example
   * await page.goto('/public/members/list');
   * await NavigationHelper.expectRedirectTo(page, '/login');
   */
  static async expectRedirectTo(
    page: Page,
    expectedUrl: string | RegExp,
    timeout: number = 10000
  ): Promise<void> {
    await page.waitForURL(
      typeof expectedUrl === 'string' ? new RegExp(expectedUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) : expectedUrl,
      { timeout }
    );
    await expect(page).toHaveURL(
      typeof expectedUrl === 'string' ? new RegExp(expectedUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) : expectedUrl
    );
  }

  /**
   * Assert that the page has NOT been redirected to a given URL pattern.
   * Waits for the page to settle before checking the URL.
   *
   * @param page - Playwright Page object
   * @param unexpectedUrl - URL string, substring or RegExp that must NOT match
   * @param timeout - Optional timeout in milliseconds (default: 10000)
   *
   * @example
   * await page.goto('/public/members/list');
   * await NavigationHelper.expectNoRedirectTo(page, /\/login/);
   */
  static async expectNoRedirectTo(
    page: Page,
    unexpectedUrl: string | RegExp,
    timeout: number = 10000
  ): Promise<void> {
    await page.waitForLoadState('domcontentloaded', { timeout });
    await expect(page).not.toHaveURL(
      typeof unexpectedUrl === 'string' ? new RegExp(unexpectedUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) : unexpectedUrl
    );
  }
}

