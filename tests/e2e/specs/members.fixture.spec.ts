/**
 * E2E Tests for Galette Member Fixtures
 * Tests the galette:seed-fixtures command by verifying fixture data
 */

import { test } from '../fixtures/auth.fixture';
import { expect } from '@playwright/test';

test.describe('Member Fixtures', () => {

  test('Fixtures - Display members list', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    await expect(page).toHaveURL(/\/members/);

    await expect(page.locator('h1')).toContainText(/Adhérents|Members/i);
    await expect(page.locator('table.listing')).toBeVisible({ timeout: 10000 });
  });

  test('Fixtures - Find members from multiple franchises', async ({ loggedInPage: page }) => {
    await page.goto('/members');
    const searchInput = page.getByRole('textbox', { name: 'Search:' });
    const searchBtn = page.locator('button[type="submit"]').first();

    const testMembers = [
      { name: 'luke', expected: 'SKYWALKER Luke' },
      { name: 'hermione', expected: 'GRANGER Hermione' },
      { name: 'arthur', expected: 'PENDRAGON Arthur' },
    ];

    for (const { name, expected } of testMembers) {
      await searchInput.clear();
      await searchInput.fill(name);
      await searchBtn.click();
      await page.waitForTimeout(300);

      await expect(page.getByRole('link', { name: expected, exact: true })).toBeVisible({ timeout: 10000 });
    }
  });

  test('Fixtures - Display member details', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const searchInput = page.getByRole('textbox', { name: 'Search:' });
    await searchInput.fill('luke');
    await page.locator('button[type="submit"]').first().click({ timeout: 5000 });
    await page.waitForTimeout(500);

    const memberLink = page.locator('a:has-text("Skywalker")').first();
    await memberLink.click({ timeout: 5000 });

    await expect(page).toHaveURL(/\/member/);
    await expect(page.locator('#member_card')).toContainText('SKYWALKER Luke');
  });

  test('Fixtures - Verify search functionality', async ({ loggedInPage: page }) => {
    await page.goto('/members');

    const searchInput = page.getByRole('textbox', { name: 'Search:' });
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    await searchInput.fill('test_search_value');
    await expect(searchInput).toHaveValue('test_search_value');
  });

});
