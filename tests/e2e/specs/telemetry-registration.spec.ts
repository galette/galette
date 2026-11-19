/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Registration on the telemetry server
 *
 * The registration form lives on the telemetry server: Galette opens it, then
 * asks that server -- from PHP, not from the browser -- whether it has been
 * sent, each time the page is shown again. A fake telemetry server stands for
 * the real one; the PHP server must have been started with E2E_TELEMETRY_URI
 * pointing to it (see tests/test_env.inc.php).
 */

import http from 'node:http';
import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { ModalHelper } from '../helpers/modal';

const telemetryUri = process.env.E2E_TELEMETRY_URI ?? '';

/**
 * Fake telemetry server: answers the registration status for the UUIDs it has
 * been told are registered, and records the UUIDs it has been asked about
 */
class FakeTelemetry {
  readonly registered = new Set<string>();
  readonly asked: string[] = [];
  private server?: http.Server;

  async start(uri: string): Promise<void> {
    const url = new URL(uri);
    this.server = http.createServer((request, response) => {
      const path = new URL(request.url ?? '/', url).pathname;
      if (request.method === 'POST' && path.endsWith('/reference/status')) {
        let body = '';
        request.on('data', (chunk) => body += chunk);
        request.on('end', () => {
          let uuid = '';
          try {
            uuid = String(JSON.parse(body).uuid ?? '');
          } catch {
            //recorded empty: the assertions on it will tell
          }
          this.asked.push(uuid);
          response.writeHead(200, { 'Content-Type': 'application/json' });
          response.end(JSON.stringify({ registered: this.registered.has(uuid) }));
        });
        return;
      }
      if (request.method === 'GET' && path.endsWith('/reference')) {
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end('<!doctype html><title>Registration form</title><h1>Registration form</h1>');
        return;
      }
      response.writeHead(404);
      response.end();
    });
    await new Promise<void>((resolve) => this.server!.listen(Number(url.port), url.hostname, resolve));
  }

  async stop(): Promise<void> {
    await new Promise<void>((resolve) => this.server ? this.server.close(() => resolve()) : resolve());
  }
}

async function resetRegistration(page: Page): Promise<void> {
  const response = await page.request.post('/test/preferences', {
    data: { action: 'reset_registration' },
  });
  expect(response.ok()).toBeTruthy();
}

/**
 * Open the registration form from the button, as the administrator does, and
 * give back the UUID it has been opened with
 */
async function openRegistrationForm(page: Page): Promise<string> {
  const button = page.locator('button#register');
  const url = new URL(await button.getAttribute('data-url') ?? '');
  const uuid = url.searchParams.get('uuid') ?? '';
  expect(uuid).toMatch(/^[0-9A-Za-z]{40}$/);

  const popupPromise = page.waitForEvent('popup');
  await button.click();
  const popup = await popupPromise;
  await expect(popup.getByRole('heading', { name: 'Registration form' })).toBeVisible();
  await popup.close();
  return uuid;
}

type RegistrationCheck = { registered: boolean; message?: string; date?: string };

/**
 * Come back to the Galette page, and give back the answer of the check that follows
 */
async function comeBack(page: Page): Promise<RegistrationCheck> {
  const check = page.waitForResponse((response) => response.url().includes('/ajax/telemetry/registration'));
  await page.bringToFront();
  //headless browsers do not always tell a tab it has been shown again
  await page.evaluate(() => window.dispatchEvent(new Event('focus')));
  return await (await check).json() as RegistrationCheck;
}

test.describe('Telemetry registration', () => {
  //one fake server, on the port of E2E_TELEMETRY_URI
  test.describe.configure({ mode: 'serial' });
  test.skip(telemetryUri === '', 'E2E_TELEMETRY_URI is not set: the PHP server would ask the real telemetry server');

  const telemetry = new FakeTelemetry();

  test.beforeAll(async () => {
    await telemetry.start(telemetryUri);
  });

  test.afterAll(async () => {
    await telemetry.stop();
  });

  test('Preferences - registration is shown once the form has been sent', async ({ loggedInPage: page }) => {
    await resetRegistration(page);
    try {
      await page.goto('/preferences');
      const date = page.locator('#registration_date');
      const button = page.locator('button#register');
      //the administrator language is the one of the instance: compare to what is shown
      const notRegistered = (await date.innerText()).trim();
      const register = (await button.innerText()).trim();

      const uuid = await openRegistrationForm(page);

      //back before sending the form: nothing to show, and checks go on
      expect((await comeBack(page)).registered).toBe(false);
      expect(telemetry.asked.at(-1)).toBe(uuid);
      await expect(date).toHaveText(notRegistered);
      await expect(page.locator('.ui.modal.visible')).toHaveCount(0);

      //form sent on the telemetry server
      telemetry.registered.add(uuid);
      const answer = await comeBack(page);
      expect(answer.registered).toBe(true);
      expect(telemetry.asked.at(-1)).toBe(uuid);

      await ModalHelper.expectModalText(page, answer.message ?? '');
      await ModalHelper.clickCancel(page);

      //shown without reloading the page
      await expect(date).toHaveText(answer.date ?? '');
      await expect(button).not.toHaveText(register);

      //nothing pending any more: no check, no thanks again
      const asked = telemetry.asked.length;
      await page.reload();
      await expect(date).toHaveText(answer.date ?? '');
      await expect(button).not.toHaveText(register);
      await expect(page.locator('.ui.modal.visible')).toHaveCount(0);
      expect(telemetry.asked.length).toBe(asked);
    } finally {
      await resetRegistration(page);
    }
  });

  test('Dashboard - register button goes away once the form has been sent', async ({ loggedInPage: page }) => {
    await resetRegistration(page);
    try {
      await page.goto('/dashboard');
      const uuid = await openRegistrationForm(page);
      telemetry.registered.add(uuid);
      const answer = await comeBack(page);
      expect(answer.registered).toBe(true);
      expect(telemetry.asked.at(-1)).toBe(uuid);

      await ModalHelper.expectModalText(page, answer.message ?? '');
      await ModalHelper.clickCancel(page);
      await expect(page.locator('button#register')).toHaveCount(0);
    } finally {
      await resetRegistration(page);
    }
  });
});
