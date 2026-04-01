/*!
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

import { test as base, expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

/**
 * E2E tests for the API clients admin page (/api-clients).
 *
 * Requires:
 *   - A running Galette instance on E2E_BASE_URL (default http://127.0.0.1:8080)
 *   - Admin credentials via E2E_ADMIN_USER / E2E_ADMIN_PASS
 *   - The api_client table present in the database
 */

/** Unique prefix to avoid conflicts between test runs */
const RUN_ID = Date.now();

test.describe('API Clients management', () => {

    base.describe('Unauthenticated access', () => {
        base.test('redirects to login page', async ({ page }) => {
            await page.goto('/api-clients');
            await expect(page).toHaveURL(/login/);
        });
    });

    test.describe('Authenticated admin', () => {

        test('can navigate to the API clients page', async ({ loggedInPage: page }) => {
            await page.goto('/api-clients');
            await expect(page).toHaveURL(/api-clients/);
            // Page title heading is visible
            await expect(page.locator('h1, .page_title, h2').first()).toBeVisible();
        });

        test('can create a new API client and the secret is shown once', async ({ loggedInPage: page }) => {
            const clientId = `e2e_create_${RUN_ID}`;

            await page.goto('/api-clients');

            // Fill the inline add form
            await page.locator('input[name="client_id"]').fill(clientId);
            await page.locator('input[name="client_name"]').fill('E2E Test Client');
            await page.locator('button[name="valid"]').click();

            // Secret banner must be visible
            await expect(page.locator('.ui.warning.message code')).toBeVisible({ timeout: 10000 });

            // The new client appears in the table
            await expect(page.locator(`code:text("${clientId}")`)).toBeVisible();

            // After reload the secret banner is gone (shown only once)
            await page.reload();
            await expect(page.locator('.ui.warning.message code')).not.toBeVisible();
        });

        test('shows an error for a duplicate client_id', async ({ loggedInPage: page }) => {
            const clientId = `e2e_dup_${RUN_ID}`;

            // First creation — must succeed
            await page.goto('/api-clients');
            await page.locator('input[name="client_id"]').fill(clientId);
            await page.locator('input[name="client_name"]').fill('Original');
            await page.locator('button[name="valid"]').click();
            await expect(page).toHaveURL(/api-clients/);

            // Second creation with same ID — must fail
            await page.locator('input[name="client_id"]').fill(clientId);
            await page.locator('input[name="client_name"]').fill('Duplicate');
            await page.locator('button[name="valid"]').click();

            // Error toast or inline error message
            await expect(
                page.locator('.ui.toast.error, noscript .ui.error.message, .ui.negative.message')
            ).toBeVisible({ timeout: 10000 });
        });

        test('can delete a client via confirmation modal', async ({ loggedInPage: page }) => {
            const clientId = `e2e_delete_${RUN_ID}`;

            // Create the client first
            await page.goto('/api-clients');
            await page.locator('input[name="client_id"]').fill(clientId);
            await page.locator('input[name="client_name"]').fill('To Be Deleted');
            await page.locator('button[name="valid"]').click();
            await expect(page.locator(`code:text("${clientId}")`)).toBeVisible({ timeout: 10000 });

            // Click the delete link for this client
            const row = page.locator('tr', { has: page.locator(`code:text("${clientId}")`) });
            await row.locator('a.delete').click();

            // Confirmation modal appears — approve deletion
            await expect(page.locator('.ui.modal')).toBeVisible({ timeout: 10000 });
            await page.locator('.ui.modal .approve.button').click();

            // Success feedback and client no longer in the table
            await expect(
                page.locator('.ui.toast.success, noscript .ui.success.message, .ui.positive.message')
            ).toBeVisible({ timeout: 10000 });
            await expect(page.locator(`code:text("${clientId}")`)).not.toBeVisible();
        });

    });
});
