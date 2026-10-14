/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Dynamic translations - labels translation modal, from contribution types list
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';
import { ModalHelper } from '../helpers';

test.describe('Dynamic translations', () => {
  test.describe.configure({ mode: 'serial' });

  test('Translations - core label is read-only', async ({ loggedInPage: page }) => {
    await page.goto('/contributions-types');
    await page.locator('a.single-translate[href$="text_orig=annual+fee"]').click();

    const modal = await ModalHelper.waitForOpen(page);
    await expect(modal.locator('> .header')).toContainText('annual fee');
    await expect(modal.locator('[data-modal-readonly]')).toBeVisible();

    //translations come from Galette, whatever the UI language is
    const german = modal.locator('#text_trans_de_DE\\.utf8');
    await expect(german).toHaveValue('Jährlicher Beitrag');
    await expect(german).toHaveAttribute('readonly', 'readonly');
    await expect(german).toHaveAttribute('aria-describedby', 'translations-hint');

    //nothing to save
    await expect(modal.locator('.actions .approve')).toHaveCount(0);
    await expect(modal.locator('input[name^="text_trans_"]')).toHaveCount(0);

    const results = await axeBuilder(page).include('.ui.modal.visible').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);

    await ModalHelper.clickCancel(page);
  });

  test('Translations - user label can be translated', async ({ loggedInPage: page }) => {
    const label = `E2E translated type ${Date.now()}`;

    await page.goto('/contributions-types');
    await page.locator('#libelle_type_cotis').fill(label);
    await page.locator('button[name="valid"]').click();
    await page.waitForURL(/\/contributions-types/);

    const row = page.locator('tbody tr', { hasText: label });
    await row.locator('a.single-translate').click();

    let modal = await ModalHelper.waitForOpen(page);
    await expect(modal.locator('> .header')).toContainText(label);
    await expect(modal.locator('[data-modal-readonly]')).toHaveCount(0);

    const french = modal.locator('#text_trans_fr_FR\\.utf8');
    await expect(french).not.toHaveAttribute('readonly');
    await expect(modal.locator('.actions .approve')).toHaveCount(1);

    const results = await axeBuilder(page).include('.ui.modal.visible').analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);

    await french.fill('Type traduit E2E');
    const saved = page.waitForResponse(
      response => response.url().includes('/dynamic-translations') && response.request().method() === 'POST'
    );
    //saving redirects to the list: links are not handled until the page has been fully reloaded
    const reloaded = page.waitForEvent('load');
    await modal.locator('.actions .approve').click();
    await saved;
    await reloaded;

    //translation has been stored
    await row.locator('a.single-translate').click();
    modal = await ModalHelper.waitForOpen(page);
    await expect(modal.locator('#text_trans_fr_FR\\.utf8')).toHaveValue('Type traduit E2E');
    await ModalHelper.clickCancel(page);
    await modal.waitFor({ state: 'hidden' });

    //cleanup
    await row.locator('a.delete').click();
    await ModalHelper.clickConfirm(page);
    await expect(page.locator('tbody tr', { hasText: label })).toHaveCount(0);
  });
});
