/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { Page } from '@playwright/test';

/**
 * Public Pages Visibility Constants (from Galette\Core\Preferences)
 */
export const PUBLIC_PAGES_VISIBILITY = {
  /** Public pages are publicly visible (no authentication required) */
  PUBLIC: 0,
  /** Public pages are visible for up-to-date members only */
  RESTRICTED: 1,
  /** Public pages are visible for admin and staff members only */
  PRIVATE: 2,
  /** Public pages are hidden (not visible to anyone) */
  HIDDEN: 3,
  /** Inherit from generic setting */
  INHERIT: 4,
} as const;

export type PublicPagesVisibility = typeof PUBLIC_PAGES_VISIBILITY[keyof typeof PUBLIC_PAGES_VISIBILITY];

/**
 * Public page preference names
 */
export const PUBLIC_PAGE_PREFS = {
  GENERIC: 'pref_publicpages_visibility_generic',
  MEMBERS_LIST: 'pref_publicpages_visibility_memberslist',
  MEMBERS_GALLERY: 'pref_publicpages_visibility_membersgallery',
  STAFF_LIST: 'pref_publicpages_visibility_stafflist',
  STAFF_GALLERY: 'pref_publicpages_visibility_staffgallery',
  DOCUMENTS: 'pref_publicpages_visibility_documents',
} as const;

/**
 * Helper for managing public pages preferences in E2E tests
 */
export class PreferencesHelper {
  /**
   * Enable public pages globally
   *
   * @param page Playwright page instance
   * @param visibility Default visibility level (default: RESTRICTED)
   */
  static async enablePublicPages(
    page: Page,
    visibility: PublicPagesVisibility = PUBLIC_PAGES_VISIBILITY.RESTRICTED
  ): Promise<void> {
    await page.evaluate(
      async ({ vis }) => {
        const response = await fetch('/test/preferences', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'enable_public_pages',
            visibility: vis,
          }),
        });
        if (!response.ok) {
          throw new Error(`Failed to enable public pages: ${response.statusText}`);
        }
      },
      { vis: visibility }
    );
  }

  /**
   * Disable public pages globally
   *
   * @param page Playwright page instance
   */
  static async disablePublicPages(page: Page): Promise<void> {
    await page.evaluate(async () => {
      const response = await fetch('/test/preferences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'disable_public_pages',
        }),
      });
      if (!response.ok) {
        throw new Error(`Failed to disable public pages: ${response.statusText}`);
      }
    });
  }

  /**
   * Set visibility for a specific public page
   *
   * @param page Playwright page instance
   * @param pageName Public page preference name (use PUBLIC_PAGE_PREFS constants)
   * @param visibility Visibility level
   */
  static async setPublicPageVisibility(
    page: Page,
    pageName: string,
    visibility: PublicPagesVisibility
  ): Promise<void> {
    await page.evaluate(
      async ({ name, vis }) => {
        const response = await fetch('/test/preferences', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'set_public_page_visibility',
            page_name: name,
            visibility: vis,
          }),
        });
        if (!response.ok) {
          throw new Error(`Failed to set page visibility: ${response.statusText}`);
        }
      },
      { name: pageName, vis: visibility }
    );
  }

  /**
   * Restore default public pages configuration
   * (enabled with RESTRICTED visibility)
   *
   * @param page Playwright page instance
   */
  static async restoreDefaultPublicPages(page: Page): Promise<void> {
    await page.evaluate(async () => {
      const response = await fetch('/test/preferences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'restore_default_public_pages',
        }),
      });
      if (!response.ok) {
        throw new Error(`Failed to restore default public pages: ${response.statusText}`);
      }
    });
  }

  /**
   * Get current public pages configuration
   *
   * @param page Playwright page instance
   * @returns Current public pages preferences
   */
  static async getPublicPagesConfig(page: Page): Promise<{
    enabled: boolean;
    generic: number;
    memberslist: number;
    membersgallery: number;
    stafflist: number;
    staffgallery: number;
    documents: number;
  }> {
    return await page.evaluate(async () => {
      const response = await fetch('/test/preferences?action=get_public_pages_config');
      if (!response.ok) {
        throw new Error(`Failed to get public pages config: ${response.statusText}`);
      }
      return await response.json();
    });
  }
}

