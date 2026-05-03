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

import { test as base, Page } from '@playwright/test';

/**
 * User roles available in Galette fixtures
 */
export type UserRole = 'superadmin' | 'admin' | 'treasurer' | 'secretary' | 'member' | 'groupManager';

/**
 * Role credentials mapping
 */
export const ROLE_CREDENTIALS: Record<UserRole, { login: string; password: string }> = {
  superadmin: {
    login: process.env.E2E_ADMIN_USER ?? 'admin',
    password: process.env.E2E_ADMIN_PASS ?? 'admin',
  },
  admin: {
    login: 'leia.organa',
    password: 'G@l3tte-E2E!',
  },
  treasurer: {
    login: 'morpheus',
    password: 'G@l3tte-E2E!',
  },
  secretary: {
    login: 'turanga.leela',
    password: 'G@l3tte-E2E!',
  },
  member: {
    login: 'luke.skywalker',
    password: 'G@l3tte-E2E!',
  },
  groupManager: {
    login: 'anakin.skywalker', // Group manager of Skywalker Family
    password: 'G@l3tte-E2E!',
  },
};

/**
 * Login fixture for E2E tests.
 * Reusable login fixture for all test suites.
 *
 * Usage in a spec:
 *   import { test } from '../fixtures/auth.fixture';
 *   test('my test', async ({ loggedInPage }) => { ... });
 *
 * With specific role:
 *   test('admin test', async ({ loggedInAs }) => {
 *     const page = await loggedInAs('admin');
 *     // ... test with admin role
 *   });
 */

type AuthFixtures = {
  loggedInPage: Page;
  loggedInAs: (role: UserRole) => Promise<Page>;
};

export const test = base.extend<AuthFixtures>({
  loggedInPage: async ({ page }, use) => {
    const login    = process.env.E2E_ADMIN_USER ?? 'admin';
    const password = process.env.E2E_ADMIN_PASS ?? 'admin';

    await page.goto('/login');
    await page.locator('input#login').fill(login);
    await page.locator('input#password').fill(password);
    await page.locator('input[type="submit"]').click();

    // Wait for dashboard redirection
    await page.waitForURL('/dashboard');

    // Provide the authenticated page to test
    await use(page);
  },

  loggedInAs: async ({ browser }, use) => {
    const loginAs = async (role: UserRole): Promise<Page> => {
      const context = await browser.newContext();
      const page = await context.newPage();

      const credentials = ROLE_CREDENTIALS[role];

      await page.goto('/login');
      await page.locator('input#login').fill(credentials.login);
      await page.locator('input#password').fill(credentials.password);
      await page.locator('input[type="submit"]').click();

      // Wait for dashboard redirection
      await page.waitForURL('/dashboard', { timeout: 10000 });

      return page;
    };

    await use(loginAs);
  },
});

export { expect } from '@playwright/test';
