/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Mailing queue E2E — real sending against a local SMTP catcher (Mailpit).
 *
 * Unlike mailings.spec.ts (UI only), this spec actually sends a mass mailing.
 * It never delivers real email: Galette is pointed at Mailpit, which captures
 * everything and exposes an HTTP API to assert on the result. The test is
 * skipped automatically when Mailpit is not reachable (e.g. local runs without
 * the catcher).
 */

import { expect, request, type APIRequestContext } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://127.0.0.1:8025';
const BATCH_SIZE = 2;

let mailpit: APIRequestContext | null = null;
let mailpitAvailable = false;

test.beforeAll(async () => {
  try {
    mailpit = await request.newContext({ baseURL: MAILPIT_URL });
    const res = await mailpit.get('/readyz', { timeout: 3000 });
    mailpitAvailable = res.ok();
  } catch {
    mailpitAvailable = false;
  }
});

test.afterAll(async () => {
  await mailpit?.dispose();
});

test.describe('Mailing queue (real send to Mailpit)', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!mailpitAvailable, 'Mailpit is not reachable');

    // point Galette at Mailpit and enable batching + a daily quota so mass
    // mailings go through the persistent queue and its AJAX drainer
    const res = await page.request.post('/test/preferences', {
      data: {
        action: 'configure_mail',
        host: '127.0.0.1',
        port: 1025,
        batch_size: BATCH_SIZE,
        daily_limit: 1000,
      },
    });
    expect(res.ok()).toBeTruthy();
  });

  test.afterEach(async ({ page }) => {
    if (mailpitAvailable) {
      await page.request.post('/test/preferences', { data: { action: 'reset_mail' } });
    }
  });

  test('sends a mass mailing in BCC batches through the queue', async ({ loggedInPage: page }) => {
    const subject = `E2E queue ${Date.now()}`;

    // select several members in the list
    await page.goto('/members');
    const checkboxes = page.locator('#listform input[name="entries_sel[]"]');
    const available = await checkboxes.count();
    expect(available).toBeGreaterThan(0);

    const toSelect = Math.min(5, available);
    for (let i = 0; i < toSelect; i++) {
      await checkboxes.nth(i).check();
    }

    // trigger the "send mail" batch action exactly like the app does
    await page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>('#listform');
      if (!form) {
        throw new Error('members list form not found');
      }
      for (const name of ['sendmail', 'mailing_new', 'mailing']) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = 'true';
        form.appendChild(input);
      }
      form.submit();
    });

    // mailing form: write the message and go to the preview step
    await page.waitForURL(/\/mailing(\?|$)/);
    await page.locator('#mailing_objet').fill(subject);
    await page.locator('#mailing_corps').fill('E2E queue body');
    const htmlToggle = page.locator('#mailing_html');
    if (await htmlToggle.isChecked()) {
      await htmlToggle.uncheck();
    }
    await page.locator('#btnpreview').click();

    // confirm sending: the mailing is queued and we land on the progress page
    await page.locator('button[name="mailing_confirm"]').click();
    await page.waitForURL(/\/mailing\/queue\/\d+/);

    const queued = parseInt((await page.locator('#queue_total').textContent()) ?? '0', 10);
    expect(queued).toBeGreaterThan(0);

    // the AJAX drainer empties the queue
    await expect(page.locator('#queue_remaining')).toHaveText('0', { timeout: 30000 });

    // Mailpit received exactly one message per BCC batch. Filter by our unique
    // subject so the assertion is immune to any other captured mail.
    const expectedMessages = Math.ceil(queued / BATCH_SIZE);
    await expect
      .poll(
        async () => {
          const res = await mailpit!.get('/api/v1/messages', { params: { limit: 200 } });
          const body = await res.json();
          const messages = (body.messages ?? []) as Array<{ Subject: string }>;
          return messages.filter((m) => m.Subject === subject).length;
        },
        { timeout: 15000 }
      )
      .toBe(expectedMessages);
  });
});
