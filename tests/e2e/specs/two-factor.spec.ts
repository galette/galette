/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { expect, Page } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { ROLE_CREDENTIALS } from '../fixtures/auth.fixture';
import { TwoFactorHelper, TWO_FACTOR_MODE } from '../helpers/twofactor';
import { nextTotp, totp } from '../helpers/totp';

const MEMBER = ROLE_CREDENTIALS.member;

//the fixture members speak French: what the manage page says is read from its
//markup rather than from its text, which translations change
const ENABLED = 'form[action$="/two-factor/disable"]';
const NOT_ENABLED = 'a.button[href$="/two-factor/enrol"]';

/**
 * Send a login and a password, without expecting to land anywhere in
 * particular: with a second factor enabled, that lands on the challenge.
 *
 * @param page     Playwright page instance
 * @param login    Login to send
 * @param password Password to send
 */
async function submitPassword(page: Page, login: string, password: string): Promise<void> {
  await page.goto('/login');
  await page.locator('input#login').fill(login);
  await page.locator('input#password').fill(password);
  await page.locator('input[type="submit"]').click();
}

/**
 * Enable a second factor on the account the page is logged in as, and return
 * the secret it was given.
 *
 * @param page Playwright page instance, logged in
 */
async function enrol(page: Page): Promise<string> {
  await page.goto('/two-factor/enrol');
  const secret = await page.locator('#tfa_secret').inputValue();
  await page.locator('input#code').fill(totp(secret));
  await page.locator('button[type="submit"]').click();

  return secret;
}

/*
 * These specs hold an instance wide policy, and enrol accounts every other
 * spec logs in with: they run in the 'two-factor' Playwright project, which
 * CI gives a job and an instance of its own. Moving them back in with the
 * rest would have another spec's login land on the challenge.
 */
test.describe('Two-factor authentication', () => {
  test.afterEach(async ({ page }) => {
    //leave nothing enabled behind: another spec logging in with a password
    //alone would otherwise be held at a challenge it has no code for. Not on
    //the logged in page: cleaning up must not depend on being able to log in.
    await page.goto('/login');
    await TwoFactorHelper.reset(page);
  });

  test('2FA - The menu offers it only while the policy allows it', async ({ page, loggedInAs }) => {
    await page.goto('/login');
    await TwoFactorHelper.reset(page);

    const member = await loggedInAs('member');
    await expect(member.locator('a[href$="/two-factor/manage"]')).toHaveCount(0);

    //and the URL is not a way round the missing menu entry: a secret enrolled
    //while the policy is disabled would never be asked for
    await member.goto('/two-factor/manage');
    await expect(member).not.toHaveURL(/two-factor/);
    await member.goto('/two-factor/enrol');
    await expect(member).not.toHaveURL(/two-factor/);

    await TwoFactorHelper.setMode(member, TWO_FACTOR_MODE.OPTIONAL);
    await member.goto('/dashboard');
    //presence rather than visibility: the entry sits in a collapsed "My
    //account" menu, and twice over -- one menu for wide screens, one for narrow
    await expect(member.locator('a[href$="/two-factor/manage"]')).not.toHaveCount(0);
  });

  test('2FA - Enrolment hands out a key and recovery codes', async ({ page, loggedInAs }) => {
    await page.goto('/login');
    await TwoFactorHelper.reset(page);
    await TwoFactorHelper.setMode(page, TWO_FACTOR_MODE.OPTIONAL);

    const member = await loggedInAs('member');
    await member.goto('/two-factor/enrol');
    await expect(member.locator('#tfa_secret')).toBeVisible();
    const secret = await member.locator('#tfa_secret').inputValue();

    //160 bits rendered as base32: short enough to be typed in by hand, for
    //whoever cannot scan the QR code
    expect(secret).toMatch(/^[A-Z2-7]{32}$/);
    await expect(member.locator('img[src^="data:image/svg+xml"]')).toBeVisible();

    //a wrong code leaves it off
    await member.locator('input#code').fill('000000');
    await member.locator('button[type="submit"]').click();
    await expect(member.locator('.ui.error.message, .ui.toast.error')).toBeVisible({ timeout: 10000 });
    await member.goto('/two-factor/manage');
    await expect(member.locator(NOT_ENABLED)).toBeVisible();

    //a code computed from the key, here and not by Galette, turns it on: what
    //an authenticator application will do
    const enabled = await enrol(member);
    await expect(member.locator('#tfa_recovery_codes')).toBeVisible({ timeout: 10000 });
    const codes = await member.locator('#tfa_recovery_codes code').allTextContents();
    expect(codes).toHaveLength(10);
    for (const code of codes) {
      expect(code).toMatch(/^[0-9A-F]{5}-[0-9A-F]{5}$/);
    }
    expect(enabled).toBe(secret);

    //and they are shown that once only
    await member.goto('/two-factor/manage');
    await expect(member.locator(ENABLED)).toBeVisible();
    await expect(member.locator('#tfa_recovery_codes')).toHaveCount(0);
    await expect(member.locator('#tfa_remaining_codes')).toHaveText(/\b10\b/);
  });

  test('2FA - A password alone no longer gets in', async ({ page, loggedInAs }) => {
    await page.goto('/login');
    await TwoFactorHelper.reset(page);
    await TwoFactorHelper.setMode(page, TWO_FACTOR_MODE.OPTIONAL);

    const member = await loggedInAs('member');
    const secret = await enrol(member);
    await expect(member.locator('#tfa_recovery_codes')).toBeVisible({ timeout: 10000 });

    await member.goto('/logout');
    await submitPassword(member, MEMBER.login, MEMBER.password);

    //held at the challenge
    await expect(member).toHaveURL(/\/two-factor$/, { timeout: 10000 });
    await expect(member.locator('input#code')).toBeVisible();

    //and nothing behind it opens up
    await member.goto('/member/me');
    await expect(member).not.toHaveURL(/\/member\/me/);

    //a wrong code keeps it shut
    await member.goto('/two-factor');
    await member.locator('input#code').fill('000000');
    await member.locator('input[type="submit"]').click();
    await expect(member).toHaveURL(/\/two-factor$/, { timeout: 10000 });

    //the right one gets through. From the next period: enrolment just spent
    //the current one, and a code is single use.
    await member.locator('input#code').fill(nextTotp(secret));
    await member.locator('input[type="submit"]').click();
    await expect(member).not.toHaveURL(/\/two-factor$/, { timeout: 10000 });
    await member.goto('/member/me');
    await expect(member).toHaveURL(/\/member\/me/);
  });

  test('2FA - A recovery code gets in without the device', async ({ page, loggedInAs }) => {
    await page.goto('/login');
    await TwoFactorHelper.reset(page);
    await TwoFactorHelper.setMode(page, TWO_FACTOR_MODE.OPTIONAL);

    const member = await loggedInAs('member');
    await enrol(member);
    await expect(member.locator('#tfa_recovery_codes')).toBeVisible({ timeout: 10000 });
    const codes = await member.locator('#tfa_recovery_codes code').allTextContents();

    await member.goto('/logout');
    await submitPassword(member, MEMBER.login, MEMBER.password);
    await expect(member).toHaveURL(/\/two-factor$/, { timeout: 10000 });

    await member.locator('input#code').fill(codes[0]);
    await member.locator('input[type="submit"]').click();
    await expect(member).not.toHaveURL(/\/two-factor$/, { timeout: 10000 });

    //that one is spent, and cannot serve twice
    await member.goto('/two-factor/manage');
    await expect(member.locator('#tfa_remaining_codes')).toHaveText(/\b9\b/);

    await member.goto('/logout');
    await submitPassword(member, MEMBER.login, MEMBER.password);
    await member.locator('input#code').fill(codes[0]);
    await member.locator('input[type="submit"]').click();
    await expect(member).toHaveURL(/\/two-factor$/, { timeout: 10000 });
  });

  test('2FA - A required policy sends an unenrolled account to enrolment', async ({ loggedInPage: page }) => {
    await TwoFactorHelper.reset(page);
    await TwoFactorHelper.setMode(page, TWO_FACTOR_MODE.REQUIRED_ALL);

    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/two-factor\/enrol/, { timeout: 10000 });

    //the page it lands on has to render, or the two of them bounce for ever
    await expect(page.locator('#tfa_secret')).toBeVisible();
  });

  test('2FA - The super administrator has no recovery codes', async ({ loggedInPage: page }) => {
    await TwoFactorHelper.reset(page);
    await TwoFactorHelper.setMode(page, TWO_FACTOR_MODE.OPTIONAL);

    //not a member: no row to hang codes off, and the way back in is the
    //database. It still gets a second factor.
    await enrol(page);
    await expect(page.getByText('Two-factor authentication is enabled')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText('No recovery codes for this account')).toBeVisible();
    await expect(page.locator('#tfa_recovery_codes')).toHaveCount(0);
  });

  test('2FA - The challenge is not a page anyone can sit on', async ({ page }) => {
    await page.goto('/login');
    await TwoFactorHelper.reset(page);

    //nothing pending, so nothing to answer
    await page.goto('/two-factor');
    await expect(page).not.toHaveURL(/\/two-factor$/);
  });
});
