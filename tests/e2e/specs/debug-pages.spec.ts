/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Debug Pages Tests
 *
 * The end to end server runs in debug mode (see tests/test_env.inc.php), the
 * only mode where debug pages routes are declared; PHPUnit cannot reach them.
 */

import { test, expect } from '@playwright/test';

test.describe('Debug Pages', () => {

  test('Debug - phpinfo is displayed', async ({ request }) => {
    const response = await request.get('/debug/phpinfo/');
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('PHP Version');
  });

  test('Debug - routes are listed', async ({ request }) => {
    const response = await request.get('/debug/routes/');
    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain(' → debugRoutes → /debug/routes/');
    expect(body).toContain(' → publicMembersListLegacy → /public/{orig:list|members}');
  });
});
