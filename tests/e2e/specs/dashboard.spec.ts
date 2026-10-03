/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Dashboard Tests
 */

import type { Response } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';

test.describe('Dashboard', () => {

  test('Dashboard - News are loaded from an ajax call', async ({ loggedInPage: page }) => {
    // The served page reserves the column, but carries no post: feeds reach the
    // network, and the dashboard must not wait for them.
    const served = await page.request.get('/dashboard');
    expect(served.ok()).toBeTruthy();
    const html = await served.text();
    expect(html).toContain('id="dashboard-news"');
    expect(html).not.toContain('Galette 1.0.0rc1');

    const news = page.waitForResponse(
      (response: Response) => response.url().includes('/ajax/news') && response.status() === 200
    );
    await page.goto('/dashboard');

    const column = page.locator('#dashboard-news');
    await expect(column).toBeVisible();

    // ... and the ajax call fills it (tests/feed.xml, see News::getFeedURL())
    await news;
    await expect(column).toContainText('Galette news');
    await expect(column.locator('.ui.bulleted.list .item').first()).toBeVisible();
    await expect(column).toHaveAttribute('aria-busy', 'false');
  });

});
