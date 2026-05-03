/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Authentication Tests
 */

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

test.describe('Authentication', () => {

  base('Auth - Login page displays', async ({ page }) => {
    await page.goto('/login');

    await expect(page).toHaveURL(/\/login/);
    await expect(page).toHaveTitle(/Galette/i);
    await expect(page.locator('form.ui.form')).toBeVisible();
    await expect(page.locator('input#login')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('input[type="submit"]')).toBeVisible();
  });

  base('Auth - Shows error with invalid credentials', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input#login').fill('non_existing_user');
    await page.locator('input#password').fill('wrong_password');
    await page.locator('input[type="submit"]').click();

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.ui.toast.error')).toBeVisible({ timeout: 10000 });
  });

  test('Auth - Redirects to dashboard when logged in', async ({ loggedInPage: page }) => {
    await expect(page).toHaveURL('/dashboard');
    await expect(page.locator('.ui.toast.warning')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#main-activities')).toBeVisible();
  });

  test('Auth - Logout and redirect to login page', async ({ loggedInPage: page }) => {
    await expect(page.locator('.ui.toast.warning')).toBeVisible();
    await page.locator('.ui.toast.warning i[role="button"]').click();
    await expect(page.locator('.ui.toast.warning')).toBeHidden();

    const logoutLink = page.locator('[href*="/logout"]');
    await logoutLink.last().click();

    await expect(page).toHaveURL('/login');
    await expect(page.locator('input#login')).toBeVisible();
  });

});

