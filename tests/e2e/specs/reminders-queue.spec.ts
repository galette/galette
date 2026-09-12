/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Reminders queue E2E — real sending against a local SMTP catcher (Mailpit).
 *
 * Reminders are individual personalized messages (one per member), routed
 * through the same queue as mass mailings. The test skips when Mailpit is not
 * reachable, and when no reminder is due in the seeded data.
 */

import { expect, request, type APIRequestContext } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';

const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://127.0.0.1:8025';

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

test.describe('Reminders queue (real send to Mailpit)', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!mailpitAvailable, 'Mailpit is not reachable');

    const res = await page.request.post('/test/preferences', {
      data: {
        action: 'configure_mail',
        host: '127.0.0.1',
        port: 1025,
        batch_size: 2,
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

  test('sends reminders individually through the queue', async ({ loggedInPage: page }) => {
    await page.goto('/reminders');

    // reminder type checkboxes are disabled when nothing is due
    const lateEnabled = await page.locator('#reminder_late').isEnabled();
    const impendingEnabled = await page.locator('#reminder_impending').isEnabled();
    test.skip(!lateEnabled && !impendingEnabled, 'No reminder due in the seeded data');

    // start from an empty mailbox (reminder subjects are not unique)
    await mailpit!.delete('/api/v1/messages');

    // select every enabled reminder type (Fomantic checkboxes are hidden, so
    // toggle them programmatically) and submit the "send" action
    await page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>('#send_reminders');
      if (!form) {
        throw new Error('reminders form not found');
      }
      for (const id of ['reminder_late', 'reminder_impending']) {
        const cb = document.getElementById(id) as HTMLInputElement | null;
        if (cb && !cb.disabled) {
          cb.checked = true;
        }
      }
    });
    await page.locator('button[name="valid"]').click();

    // reminders are queued and drained on the progress page
    await page.waitForURL(/\/reminders\/queue/);
    const queued = parseInt((await page.locator('#queue_total').textContent()) ?? '0', 10);
    expect(queued).toBeGreaterThan(0);

    await expect(page.locator('#queue_remaining')).toHaveText('0', { timeout: 30000 });

    // one individual message per queued reminder reached Mailpit
    await expect
      .poll(
        async () => {
          const res = await mailpit!.get('/api/v1/messages', { params: { limit: 500 } });
          const body = await res.json();
          return (body.messages ?? []).length as number;
        },
        { timeout: 15000 }
      )
      .toBe(queued);
  });
});
