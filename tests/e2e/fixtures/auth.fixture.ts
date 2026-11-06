/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { test as base, Page } from '@playwright/test';

/**
 * User roles available in Galette fixtures
 */
export type UserRole =
  | 'superadmin'
  | 'admin'
  | 'staff'
  | 'member'
  | 'groupManager'
  | 'otherGroupManager'
  | 'treasury'
  | 'groupSecretary';

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
  staff: {
    login: 'morpheus', // treasurer status, staff without being an administrator
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
  otherGroupManager: {
    login: 'esteban.dore', // Group manager of Les Explorateurs Solaires
    password: 'G@l3tte-E2E!',
  },
  treasury: {
    login: 'hermione.granger', // "Trésorerie" role, for all groups
    password: 'G@l3tte-E2E!',
  },
  groupSecretary: {
    login: 'sally.ragdoll', // "Secrétariat de groupe" role, for Les Âmes Errantes only
    password: 'G@l3tte-E2E!',
  },
};

/**
 * Log a page in with the account of a role, and wait for the dashboard.
 * Also usable outside of a test, from a hook that gets the browser only.
 */
export async function logIn(page: Page, role: UserRole): Promise<void> {
  const credentials = ROLE_CREDENTIALS[role];

  await page.goto('/login');
  await page.locator('input#login').fill(credentials.login);
  await page.locator('input#password').fill(credentials.password);
  await page.locator('input[type="submit"]').click();

  // Wait for dashboard redirection
  await page.waitForURL('/dashboard', { timeout: 10000 });
}

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
      await logIn(page, role);

      return page;
    };

    await use(loginAs);
  },
});

export { expect } from '@playwright/test';
