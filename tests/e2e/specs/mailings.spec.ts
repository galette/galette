/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Mailings Tests - Email/mailing functionality (UI only, NO real email sending)
 *
 * IMPORTANT: These tests DO NOT send real emails. They only test the mailing
 * interface, form validation, and preview functionality.
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

test.describe('Mailings', () => {

    // General mailings UI test
  test('Mailings - UI loads without errors', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    await page.getByLabel('Main menu', { exact: true }).getByText('Management').click();
    await page.getByRole('link', { name: 'Mailings' }).click();

    await expect(page).toHaveURL(/\/mailings/);

    // Check for error messages
    const errorMessages = page.locator('.ui.error.message, .ui.negative.message');
    const errorCount = await errorMessages.count();

    // Should not have critical errors on load
    expect(errorCount).toBe(0);
  });

  /**
   * Mailing are not enabled when there is no mail method configured.
   * For now; I do not want to change the default configuration; see that later.
   *
  // Test mailing form display
  test('Mailings - Display mailing form', async ({ loggedInPage: page }) => {
    // Try to access mailing directly
    await page.goto('/members');

    // Navigate through members to mailing
    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();

      // Should display mailing form or members selection
      await page.waitForSelector('h1, h2, form', { timeout: 10000 });

      const hasForm = await page.locator('form').count() > 0;
      const hasTitle = await page.locator('h1, h2').count() > 0;

      expect(hasForm || hasTitle).toBeTruthy();
    }
  });

  // Test mailing form elements
  test('Mailings - Form has required fields', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('form, h1', { timeout: 10000 });

      // Check for common mailing form elements
      const hasSubject = await page.locator('input[name*="subject"], input[id*="subject"]').count() > 0;
      const hasBody = await page.locator('textarea[name*="body"], textarea[name*="message"]').count() > 0;
      const hasRecipients = await page.locator('select, input[type="checkbox"]').count() > 0;

      // Form should have at least some of these elements
      expect(hasSubject || hasBody || hasRecipients).toBeTruthy();
    }
  });

  // Test sender selection
  test('Mailings - Sender selection available', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('form, body', { timeout: 10000 });

      // Check for sender options
      const senderSelect = page.locator('select[name="sender"], select[id="sender"]');

      if (await senderSelect.count() > 0) {
        await expect(senderSelect).toBeVisible();

        // Should have options
        const options = await senderSelect.locator('option').count();
        expect(options).toBeGreaterThan(0);
      }
    }
  });

  // Test recipients display
  test('Mailings - Recipients information visible', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('body', { timeout: 10000 });

      // Look for recipients count or list
      const hasRecipientsList = await page.locator('[class*="recipient"]').count() > 0;
      const hasUsersList = await page.locator('.users, .members').count() > 0;
      const hasCount = await page.textContent('body').then(text =>
        text?.match(/\d+\s+(member|adhérent|recipient)/i) !== null
      );

      // Should display some recipient information
      expect(hasRecipientsList || hasUsersList || hasCount).toBeTruthy();
    }
  });

  // Test preview/review step
  test('Mailings - Has preview or review capability', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('body', { timeout: 10000 });

      // Look for preview button or step indicator
      const hasPreview = await page.locator('button:has-text("Preview"), button:has-text("Prévisualisation")').count() > 0;
      const hasReview = await page.locator('button:has-text("Review"), button:has-text("Vérifier")').count() > 0;
      const hasSteps = await page.locator('.steps, .ui.steps').count() > 0;

      // Should have some form of review/preview
      expect(hasPreview || hasReview || hasSteps).toBeTruthy();
    }
  });

  // Test that form does NOT automatically send (safety check)
  test('Mailings - No automatic email sending', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('body', { timeout: 10000 });

      // Verify that there's no "sent" message immediately
      const hasSentMessage = await page.locator('.success:has-text("sent"), .success:has-text("envoyé")').count() > 0;

      // On initial load, should NOT show "sent" message
      expect(hasSentMessage).toBe(false);
    }
  });

  // Test cancel/back functionality
  test('Mailings - Can cancel or go back', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('body', { timeout: 10000 });

      // Look for cancel/back button
      const cancelButton = page.locator('a:has-text("Cancel"), a:has-text("Annuler"), a:has-text("Back"), a:has-text("Retour")').first();

      if (await cancelButton.count() > 0) {
        await expect(cancelButton).toBeVisible();
      }
    }
  });

  // Test attachment capability
  test('Mailings - Attachment support visible', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const mailingLink = page.locator('a[href*="mailing"]').first();

    if (await mailingLink.count() > 0) {
      await mailingLink.click();
      await page.waitForSelector('body', { timeout: 10000 });

      // Check for file input for attachments
      const hasFileInput = await page.locator('input[type="file"]').count() > 0;
      const hasAttachmentLabel = await page.textContent('body').then(text =>
        text?.match(/attach|pièce jointe/i) !== null
      );

      // Some indication of attachment capability
      expect(hasFileInput || hasAttachmentLabel).toBeTruthy();
    }
  });*/
});

// Mailing History/List
test.describe('Mailings History', () => {

  test('Mailings - Access mailings history/list', async ({ loggedInPage: page }) => {
    // Try direct access to mailings list
    const response = await page.goto('/mailings').catch(() => null);

    if (response && response.status() !== 404) {
      await page.waitForSelector('h1, h2, table', { timeout: 10000 });

      const hasTitle = await page.locator('h1, h2').count() > 0;
      const hasTable = await page.locator('table').count() > 0;

      expect(hasTitle || hasTable).toBeTruthy();
    }
  });

  test('Mailings - History page structure', async ({ loggedInPage: page }) => {
    const response = await page.goto('/mailings').catch(() => null);

    if (response && response.status() !== 404) {
      await page.waitForSelector('body', { timeout: 10000 });

      // Should have some content
      const bodyContent = await page.textContent('body');
      expect(bodyContent).toBeTruthy();
      expect(bodyContent!.length).toBeGreaterThan(50);
    }
  });

});

