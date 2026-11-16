/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Content Security Policy violations
 *
 * Browsers fire a securitypolicyviolation event for each violation, the
 * policy being enforced or report-only.
 */

import { expect, type Page } from '@playwright/test';

export type Violation = {
  directive: string;
  blocked: string;
  source: string;
  line: number;
  sample: string;
};

declare global {
  interface Window {
    __cspViolations: Violation[];
  }
}

/**
 * Record violations of every document the page loads
 */
export async function listenViolations(page: Page): Promise<void> {
  await page.addInitScript(() => {
    window.__cspViolations = [];
    document.addEventListener('securitypolicyviolation', (event) => {
      window.__cspViolations.push({
        directive: event.effectiveDirective,
        blocked: event.blockedURI,
        source: event.sourceFile,
        line: event.lineNumber,
        sample: event.sample,
      });
    });
  });
}

/**
 * Get violations of the current document
 *
 * Waits for the network to settle first: scripts loaded late, fetch calls and
 * images are checked when they are requested.
 */
export async function violations(page: Page): Promise<Violation[]> {
  await page.waitForLoadState('networkidle');
  return page.evaluate(() => window.__cspViolations ?? []);
}

/**
 * Format violations for an assertion message
 */
export function format(found: Violation[]): string {
  return found
    .map((v) => `${v.directive} blocked ${v.blocked || '(inline)'} (from ${v.source}:${v.line}) ${v.sample}`)
    .join('\n');
}

/**
 * Browse pages, and check none breaks the policy
 */
export async function expectNoViolation(page: Page, urls: string[], status = 200): Promise<void> {
  for (const url of urls) {
    const response = await page.goto(url);
    expect(response?.status(), url).toBe(status);
    const found = await violations(page);
    expect(found, `${url}\n${format(found)}`).toEqual([]);
  }
}
