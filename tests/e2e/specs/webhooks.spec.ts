/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Webhooks Tests - CRUD operations, deliveries log and access control
 *
 * Webhooks point to a closed local port: sending a test event fails right away,
 * without any external service, and the failure shows up in the deliveries log.
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

const TEST_WEBHOOK = {
  name: `E2E Test Webhook ${Date.now()}`,
  url: 'http://127.0.0.1:9/hook',
};

test.describe('Webhooks', () => {

  test('Webhooks - Configuration menu entry', async ({ loggedInPage: page }) => {
    await page.goto('/dashboard');

    // Desktop and mobile menus both have the entry
    const menuLink = page.locator('nav.menu a[href$="/webhooks"]');
    await expect(menuLink.first()).toBeAttached();
    await page.goto('/webhooks');
    await expect(page.locator('a[href$="/webhooks/add"]')).toBeVisible();
  });

  // CRUD Operations (serial execution to maintain order)
  test.describe('Webhooks CRUD', () => {
    test.describe.configure({ mode: 'serial' });

    let webhookId = '';
    let secret = '';

    test('Webhooks - Create new webhook', async ({ loggedInPage: page }) => {
      await page.goto('/webhooks/add');

      await page.locator('input#name').fill(TEST_WEBHOOK.name);
      await page.locator('input#url').fill(TEST_WEBHOOK.url);
      await page.locator('input[name="events[]"][value="member.created"]').check({ force: true });
      await page.locator('input[name="events[]"][value="group.deleted"]').check({ force: true });
      await page.locator('form.ui.form button[type="submit"].primary').click();

      // Redirected to edit page, with a generated secret
      await expect(page).toHaveURL(/\/webhooks\/edit\/\d+$/);
      webhookId = page.url().match(/\/webhooks\/edit\/(\d+)$/)?.[1] ?? '';
      expect(webhookId).not.toBe('');

      secret = await page.locator('input#secret').inputValue();
      expect(secret).toMatch(/^whsec_[A-Za-z0-9+/]+=*$/);
      await expect(page.locator('input[name="events[]"][value="member.created"]')).toBeChecked();
      await expect(page.locator('input[name="events[]"][value="group.deleted"]')).toBeChecked();
      await expect(page.locator('input[name="events[]"][value="member.updated"]')).not.toBeChecked();
    });

    test('Webhooks - Refuse invalid URL', async ({ loggedInPage: page }) => {
      await page.goto(`/webhooks/edit/${webhookId}`);

      // ftp passes the browser url check, not the server one
      await page.locator('input#url').fill('ftp://127.0.0.1/hook');
      await page.locator('form.ui.form button[type="submit"].primary').click();

      await expect(page).toHaveURL(/\/webhooks\/edit\/\d+$/);
      await expect(page.locator('.ui.toast.error, .ui.error.message').first()).toBeVisible({ timeout: 10000 });

      // Nothing has been stored
      await page.goto(`/webhooks/edit/${webhookId}`);
      await expect(page.locator('input#url')).toHaveValue(TEST_WEBHOOK.url);
    });

    test('Webhooks - Edit webhook', async ({ loggedInPage: page }) => {
      await page.goto(`/webhooks/edit/${webhookId}`);

      await page.locator('input#name').fill(`${TEST_WEBHOOK.name} - Modified`);
      await page.locator('input[name="events[]"][value="member.updated"]').check({ force: true });
      await page.locator('form.ui.form button[type="submit"].primary').click();

      await page.goto('/webhooks');
      const row = page.locator('table.listing tbody tr', { hasText: `${TEST_WEBHOOK.name} - Modified` });
      await expect(row).toHaveCount(1);
      await expect(row).toContainText('member.updated');
      await expect(row).toContainText(TEST_WEBHOOK.url);
    });

    test('Webhooks - Generate a new secret', async ({ loggedInPage: page }) => {
      await page.goto(`/webhooks/edit/${webhookId}`);
      await expect(page.locator('input#secret')).toHaveValue(secret);

      await page.locator(`form[action$="/webhooks/${webhookId}/secret"] button[type="submit"]`).click();

      await expect(page).toHaveURL(/\/webhooks\/edit\/\d+$/);
      const newSecret = await page.locator('input#secret').inputValue();
      expect(newSecret).toMatch(/^whsec_/);
      expect(newSecret).not.toBe(secret);
    });

    test('Webhooks - Failed test event shows in deliveries', async ({ loggedInPage: page }) => {
      await page.goto(`/webhooks/${webhookId}/deliveries`);

      await page.locator(`form[action$="/webhooks/${webhookId}/ping"] button[type="submit"]`).click();

      await expect(page).toHaveURL(new RegExp(`/webhooks/${webhookId}/deliveries$`));
      const rows = page.locator('#webhook_deliveries tbody tr');
      await expect(rows).toHaveCount(1);
      await expect(rows.first()).toContainText('webhook.ping');
      // Can be sent again
      await expect(rows.first().locator('form[action*="/retry"] button')).toBeVisible();
    });

    test('Webhooks - Delete webhook', async ({ loggedInPage: page }) => {
      await page.goto('/webhooks');

      const row = page.locator('table.listing tbody tr', { hasText: `${TEST_WEBHOOK.name} - Modified` });
      await row.locator(`a[href$="/webhooks/remove/${webhookId}"]`).click();
      await page.locator('.ui.modal').waitFor({ state: 'visible' });
      await page.getByRole('button', { name: 'Remove' }).click();

      await expect(page).toHaveURL(/\/webhooks$/);
      await expect(page.locator('table.listing tbody tr', { hasText: TEST_WEBHOOK.name })).toHaveCount(0);
    });
  });

  test('Webhooks - Staff member cannot manage webhooks', async ({ loggedInAs }) => {
    const page = await loggedInAs('treasurer');

    await page.goto('/dashboard');
    await expect(page.locator('nav.menu a[href$="/webhooks"]')).toHaveCount(0);

    await page.goto('/webhooks');
    await expect(page).not.toHaveURL(/\/webhooks$/);

    await page.close();
  });

});
