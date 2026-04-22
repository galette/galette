/*!
 * Copyright © 2007-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Javascript
 * @package   Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
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
