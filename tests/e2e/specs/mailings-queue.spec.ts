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
    expect(await checkboxes.count()).toBeGreaterThan(0);

    // Check the boxes and trigger the "send mail" batch action programmatically.
    // The Fomantic checkboxes are visually hidden (their label intercepts
    // clicks, so .check() would time out); this mirrors exactly what the app's
    // _sendmail() helper does on the members list.
    const selected = await page.evaluate((max) => {
      const form = document.querySelector<HTMLFormElement>('#listform');
      if (!form) {
        throw new Error('members list form not found');
      }
      const boxes = Array.from(
        form.querySelectorAll<HTMLInputElement>('input[name="entries_sel[]"]')
      );
      const count = Math.min(max, boxes.length);
      for (let i = 0; i < count; i++) {
        boxes[i].checked = true;
      }
      for (const name of ['sendmail', 'mailing_new', 'mailing']) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = 'true';
        form.appendChild(input);
      }
      form.submit();
      return count;
    }, 5);
    expect(selected).toBeGreaterThan(0);

    // mailing form: write the message. The "Preview" button only opens an AJAX
    // modal (it does not submit), so we send directly with the confirm button.
    await page.waitForURL(/\/mailing(\?|$)/);
    await page.locator('#mailing_objet').fill(subject);
    await page.locator('#mailing_corps').fill('E2E queue body');

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
