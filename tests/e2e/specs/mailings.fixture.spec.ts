/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * E2E Tests for Galette Mailings Fixtures
 * Tests the mailings history seeded by the galette:seed-fixtures command
 *
 * IMPORTANT: These tests DO NOT send real emails. They only browse the
 * mailings history stored in database by the fixtures.
 */

import { test } from '../fixtures/auth.fixture';
import { expect, Page } from '@playwright/test';

/**
 * Rows of the mailings listing
 */
const mailingRows = (page: Page) => page.locator('table.listing tbody tr:not(:has(.emptylist))');

/**
 * Sent statuses of the displayed mailings.
 * Relies on the textual alternative of the status column rather than on its icon.
 */
const statuses = async (page: Page) => (
  await mailingRows(page).locator('td:nth-child(7) .visually-hidden').allTextContents()
).map((status) => status.trim());

/**
 * Normalize a string the way database collations do for sorting:
 * accents are folded and punctuation is ignored
 */
const collate = (value: string) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/[^a-z0-9]/gi, '')
  .toLowerCase();

/**
 * Submit the filters form
 */
const applyFilters = async (page: Page) => {
  await page.locator('form.filters button[name="filter"]').click();
  await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });
};

test.describe('Mailing Fixtures', () => {

  test('Fixtures - Display mailings list', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');

    await expect(page).toHaveURL(/\/mailings/);
    await expect(page.locator('h1')).toContainText(/Mailing/i);
    await expect(page.locator('table.listing')).toBeVisible({ timeout: 10000 });
  });

  test('Fixtures - List contains seeded mailings', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    // fixtures seed more than 50 mailings; listing is paginated
    const infoline = page.locator('.infoline .ui.label').first();
    await expect(infoline).toContainText(/\d+ entries/);

    const entries = parseInt((await infoline.textContent() ?? '').replace(/\D/g, ''), 10);
    expect(entries).toBeGreaterThanOrEqual(50);

    expect(await mailingRows(page).count()).toBeGreaterThan(0);
  });

  test('Fixtures - Rows expose date, sender and recipients', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const firstRow = mailingRows(page).first();

    // date column, formatted as Y-m-d H:i:s
    await expect(firstRow.locator('td').nth(1)).toContainText(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/);

    // sender is a member name, never empty
    await expect(firstRow.locator('td').nth(2)).not.toBeEmpty();

    // recipients count is a positive number
    const recipients = await firstRow.locator('td').nth(3).textContent();
    expect(parseInt(recipients ?? '0', 10)).toBeGreaterThan(0);

    // subject is filled in
    await expect(firstRow.locator('td').nth(4)).not.toBeEmpty();
  });

  test('Fixtures - Filter on sent mailings', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    await page.locator('label[for="filter_sent"]').click();
    await applyFilters(page);

    const count = await mailingRows(page).count();
    expect(count).toBeGreaterThan(0);

    // every displayed mailing is flagged as sent
    expect(await statuses(page)).toEqual(Array(count).fill('Sent'));
  });

  test('Fixtures - Filter on unsent mailings', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    await page.locator('label[for="filter_not_sent"]').click();
    await applyFilters(page);

    const count = await mailingRows(page).count();

    // fixtures include a few mailings that have not been sent yet
    expect(count).toBeGreaterThan(0);
    expect(await statuses(page)).toEqual(Array(count).fill('Not sent'));
  });

  test('Fixtures - Filter on subject', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    await page.locator('#subject_filter').fill('assemblée générale');
    await applyFilters(page);

    const rows = mailingRows(page);
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);

    for (let i = 0; i < count; i++) {
      await expect(rows.nth(i).locator('td').nth(4)).toContainText(/assemblée générale/i);
    }
  });

  test('Fixtures - Filter on subject with no result', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    await page.locator('#subject_filter').fill('no-such-mailing-subject');
    await applyFilters(page);

    await expect(page.locator('table.listing .emptylist')).toBeVisible();
    expect(await mailingRows(page).count()).toBe(0);
  });

  test('Fixtures - Clear filters restores full list', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const infoline = page.locator('.infoline .ui.label').first();
    const initial = await infoline.textContent();

    await page.locator('#subject_filter').fill('assemblée générale');
    await applyFilters(page);
    await expect(infoline).not.toHaveText(initial ?? '');

    await page.locator('form.filters button[name="clear_filter"]').click();
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    await expect(page.locator('#subject_filter')).toHaveValue('');
    await expect(infoline).toHaveText(initial ?? '');
  });

  test('Fixtures - Filter on date range', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const infoline = page.locator('.infoline .ui.label').first();
    const total = parseInt((await infoline.textContent() ?? '').replace(/\D/g, ''), 10);

    // fixtures spread mailings over the last two years
    const since = new Date();
    since.setMonth(since.getMonth() - 6);
    await page.locator('#start_date_filter').fill(since.toISOString().substring(0, 10));
    await applyFilters(page);

    const filtered = parseInt((await infoline.textContent() ?? '').replace(/\D/g, ''), 10);
    expect(filtered).toBeGreaterThan(0);
    expect(filtered).toBeLessThan(total);
  });

  test('Fixtures - Sort mailings on subject', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const subjects = async () => (await mailingRows(page).locator('td:nth-child(5)').allTextContents())
      .map((subject) => subject.trim());

    const sortLink = page.locator('table.listing thead a', { hasText: 'Subject' });

    await sortLink.click();
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    // subjects are alphabetically ordered, ascending or descending. Only initials are
    // checked: databases do not agree on how to sort accents and punctuation, and the
    // listing is ordered by the database.
    const initials = (await subjects()).map((subject) => collate(subject).charAt(0));
    expect(initials.length).toBeGreaterThan(1);

    const ascending = [...initials].sort();
    expect([ascending, [...ascending].reverse()]).toContainEqual(initials);

    // narrow down to a single page of results, so both directions hold the same entries
    await page.locator('#subject_filter').fill("Lettre d'information");
    await applyFilters(page);

    const first = await subjects();
    expect(first.length).toBeGreaterThan(1);

    // clicking the column header again reverses the order
    await sortLink.click();
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    expect(await subjects()).toEqual([...first].reverse());
  });

  test('Fixtures - Preview a seeded mailing', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const firstRow = mailingRows(page).first();
    const subject = (await firstRow.locator('td').nth(4).textContent() ?? '').trim();

    await firstRow.locator('a.showdetails').click();

    const modal = page.locator('.ui.modal.visible');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await expect(modal).toContainText(subject);
  });

  test('Fixtures - Each mailing offers preview, reuse and removal', async ({ loggedInPage: page }) => {
    await page.goto('/mailings');
    await page.locator('table.listing').waitFor({ state: 'visible', timeout: 10000 });

    const firstRow = mailingRows(page).first();

    await expect(firstRow.locator('a.showdetails')).toHaveCount(1);
    // reuse as a template for sent mailings, edition for the ones not sent yet
    await expect(firstRow.locator('a[href*="?from="]')).toHaveCount(1);
    await expect(firstRow.locator('a.delete[href*="/mailings/remove/"]')).toHaveCount(1);
  });

});
