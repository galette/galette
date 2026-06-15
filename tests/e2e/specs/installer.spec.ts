/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Installer Tests - web installer gate and step navigation
 *
 * The instance under test is already installed, so the installer runs in
 * update mode. Every step exercised here is read only: the run is abandoned
 * on the version selection screen, before any update script is played.
 */

import { test, expect, type Page } from '@playwright/test';
import { InstallerHelper } from '../helpers';

// Screens are identified by their form controls rather than by their labels,
// the installer is displayed in the browser language.
const DISABLED_RELOAD = 'a[href="installer.php"].primary';
const CHECKS_STEP = 'input[name="install_permsok"]';
const DB_STEP = 'select[name="install_dbtype"]';
const DB_CHECKS_STEP = 'input[name="install_dbperms_ok"]';
const VERSION_STEP = 'button[name="force_select_version"]';

/**
 * Start a fresh installer session on the checks step
 */
async function gotoChecksStep(page: Page): Promise<void> {
  InstallerHelper.enable();
  await page.goto('/installer.php?raz');
  await expect(page.locator(CHECKS_STEP)).toBeAttached();
}

/**
 * Submit the step holding the given control
 */
async function submitStep(page: Page, control: string): Promise<void> {
  await page.locator(`form:has(${control}) button[type="submit"]`).first().click();
}

test.describe('Installer', () => {
  // All these tests create and remove galette/data/ENABLE_INSTALL, which is
  // global server state, shared with the installer audits of the a11y project.
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async () => {
    await InstallerHelper.acquire();
  });

  test.afterEach(() => {
    InstallerHelper.disable();
    InstallerHelper.release();
  });

  test('Installer - disabled without the enable file', async ({ page }) => {
    InstallerHelper.disable();

    await page.goto('/installer.php?raz');

    // Only a reload link, no way to start anything
    await expect(page.locator(DISABLED_RELOAD)).toBeVisible();
    await expect(page.locator(CHECKS_STEP)).toHaveCount(0);

    // Anyone can read that page: it names the file, never a server path. Which
    // form it takes depends on where the data directory sits - inside Galette
    // directory or, as under the e2e router, outside of it.
    const named = await page.locator('code').innerText();
    expect(named).toContain('ENABLE_INSTALL');
    expect(named.startsWith('/'), `server path disclosed: ${named}`).toBe(false);
  });

  test('Installer - enable file opens the checks step', async ({ page }) => {
    await gotoChecksStep(page);

    // Installation mode is deduced from config.inc.php, its step is gone
    await expect(page.locator('.steps i.question.icon')).toHaveCount(0);
  });

  test('Installer - update reads credentials from configuration file', async ({ page }) => {
    await gotoChecksStep(page);
    await submitStep(page, CHECKS_STEP);

    // Database step is skipped, credentials come from config.inc.php
    await expect(page.locator(DB_CHECKS_STEP)).toBeAttached();
    await expect(page.locator(DB_STEP)).toHaveCount(0);
  });

  test('Installer - going back from database step lands on checks', async ({ page }) => {
    await gotoChecksStep(page);
    await submitStep(page, CHECKS_STEP);

    // Back once: database step, where credentials can be entered by hand
    await page.locator('#btnback').click();
    await expect(page.locator(DB_STEP)).toBeAttached();

    // Back twice: checks step. There is no installation mode step anymore,
    // and no way out of it if the installer stops there.
    await page.locator('#btnback').click();
    await expect(page.locator(CHECKS_STEP)).toBeAttached();
  });

  test('Installer - up to date database asks for confirmation', async ({ page }) => {
    await gotoChecksStep(page);
    await submitStep(page, CHECKS_STEP);
    await submitStep(page, DB_CHECKS_STEP);

    // Version is detected, but there is nothing to update: the user has to
    // confirm before update scripts are played again.
    await expect(page.locator(VERSION_STEP)).toBeVisible();
  });

  test('Installer - removing the enable file closes a running installer', async ({ page }) => {
    await gotoChecksStep(page);
    await submitStep(page, CHECKS_STEP);
    await submitStep(page, DB_CHECKS_STEP);
    await expect(page.locator(VERSION_STEP)).toBeVisible();

    InstallerHelper.disable();
    await page.goto('/installer.php');

    // Session had passed database checks, it must not survive the removal
    await expect(page.locator(DISABLED_RELOAD)).toBeVisible();
    await expect(page.locator(VERSION_STEP)).toHaveCount(0);
  });
});
