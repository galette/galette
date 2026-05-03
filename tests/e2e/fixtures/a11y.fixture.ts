/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';

/**
 * Builds a pre-configured AxeBuilder instance for WCAG 2.0 A/AA audits.
 *
 * To suppress a known false positive, call .disableRules(['rule-id']) on the
 * returned builder and document the reason in a comment at the call site.
 */
export function axeBuilder(page: Page): AxeBuilder {
  //FIXME: real A11y issues must be fixed in order to activate all tags.
  // See available tags: https://www.deque.com/axe/core-documentation/api-documentation/#axecore-tags
  return new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'RGAAv4', 'best-practice', 'EN-301-549']);
}

/**
 * Formats axe violations into a readable one-line-per-violation summary,
 * used as the assertion failure message so the developer can triage quickly
 * without parsing raw JSON.
 */
export function formatViolations(
  violations: Array<{ id: string; description: string; nodes: unknown[] }>
): string {
  if (violations.length === 0) {
    return 'No violations';
  }
  return violations
    .map(v => `[${v.id}] ${v.description} — ${v.nodes.length} node(s)`)
    .join('\n');
}
