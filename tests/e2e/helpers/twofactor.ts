/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { Page } from '@playwright/test';

/**
 * Second factor policy, mirroring Galette\Core\TwoFactorAuth
 */
export const TWO_FACTOR_MODE = {
  DISABLED: 0,
  OPTIONAL: 1,
  REQUIRED_STAFF: 2,
  REQUIRED_ALL: 3,
} as const;

export type TwoFactorMode = typeof TWO_FACTOR_MODE[keyof typeof TWO_FACTOR_MODE];

/**
 * Helper for driving the second factor in E2E tests
 */
export class TwoFactorHelper {
  /**
   * Set the policy
   *
   * @param page Playwright page instance
   * @param mode Policy to apply
   */
  static async setMode(page: Page, mode: TwoFactorMode): Promise<void> {
    await page.evaluate(async ({ m }) => {
      const response = await fetch('/test/preferences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_two_factor_mode', mode: m }),
      });
      if (!response.ok) {
        throw new Error(`Failed to set two-factor mode: ${response.statusText}`);
      }
    }, { m: mode });
  }

  /**
   * Clear every second factor and every throttling counter.
   *
   * Called before as well as after: a run that stopped halfway through would
   * otherwise leave an account asking for a code nobody has, and the next run
   * could never log in.
   *
   * @param page Playwright page instance
   */
  static async reset(page: Page): Promise<void> {
    await page.evaluate(async () => {
      const response = await fetch('/test/preferences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reset_two_factor' }),
      });
      if (!response.ok) {
        throw new Error(`Failed to reset two-factor: ${response.statusText}`);
      }
    });
  }
}
