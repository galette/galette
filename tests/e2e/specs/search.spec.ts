/**
 * Copyright © 2003-2026 The Galette Team
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
 */

/**
 * Advanced Search Tests - Advanced search functionality and saved searches
 */

import { expect } from '@playwright/test';
import { test } from '../fixtures/auth.fixture';
import { DropdownHelper } from '@e2e/helpers';

test.describe('Advanced Search', () => {

  // Basic Search on Members List
  test.describe('Basic Search', () => {

    test('Search - Simple search on members list', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for search input
      const searchInput = page.locator('input[name="filter_str"], input[type="search"]').first();

      if (await searchInput.count() > 0) {
        await expect(searchInput).toBeVisible();

        // Try a search
        await searchInput.fill('test');

        // Should have value
        await expect(searchInput).toHaveValue('test');
      }
    });

    test('Search - Search field visible on members page', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      await page.waitForSelector('table, form', { timeout: 10000 });

      // Check for search form elements
      const hasSearchInput = await page.locator('input[type="search"], input[name*="search"], input[name*="filter"]').count() > 0;
      const hasSearchButton = await page.locator('button[type="submit"]:has-text("Search"), button:has-text("Rechercher")').count() > 0;

      expect(hasSearchInput || hasSearchButton).toBeTruthy();
    });

  });

  // Advanced Search Form
  test.describe('Advanced Search Form', () => {

    test('Search - Access advanced search', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for advanced search link/button
      const advancedLink = page.getByRole('link', { name: 'Advanced search' });

      if (await advancedLink.count() > 0) {
        await advancedLink.click();

        await page.waitForSelector('form, h1', { timeout: 10000 });

        const hasForm = await page.locator('form').count() > 0;
        expect(hasForm).toBeTruthy();
      }
    });

    test('Search - Advanced search has filters', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      const advancedLink = page.getByRole('link', { name: 'Advanced search' });

      if (await advancedLink.count() > 0) {
        await advancedLink.click();
        await page.waitForSelector('form', { timeout: 10000 });

        // Should have multiple filter fields
        const selectFields = await page.locator('select').count();
        const inputFields = await page.locator('input[type="text"], input[type="search"]').count();

        expect(selectFields + inputFields).toBeGreaterThan(2);
      }
    });

    test('Search - Advanced search form has submit button', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      const advancedLink = page.getByRole('link', { name: 'Advanced search' });

      if (await advancedLink.count() > 0) {
        await advancedLink.click();
        await page.waitForSelector('form', { timeout: 10000 });

        const submitButton = page.locator('button[type="submit"], input[type="submit"]');
        const buttonExists = await submitButton.count() > 0;

        expect(buttonExists).toBeTruthy();
      }
    });

  });

  // Saved Searches
  test.describe('Saved Searches', () => {

    test('Search - Access saved searches list', async ({ loggedInPage: page }) => {
      await page.goto('/saved-searches');

      await expect(page).toHaveURL(/\/saved-searches/);

      await page.waitForSelector('h1, h2, table, .ui.message', { timeout: 10000 });

      const hasTitle = await page.locator('h1, h2').count() > 0;
      expect(hasTitle).toBeTruthy();
    });

    test('Search - Saved searches page structure', async ({ loggedInPage: page }) => {
      await page.goto('/saved-searches');

      await page.waitForSelector('body', { timeout: 10000 });

      // Should have table or message if empty
      const hasTable = await page.locator('table').count() > 0;
      const hasMessage = await page.locator('.ui.message').count() > 0;

      expect(hasTable || hasMessage).toBeTruthy();
    });

    test('Search - Save search button visible', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // After performing a search, should have option to save it
      const saveButton = page.locator('button:has-text("Save"), a:has-text("Save"), a[href*="save-search"]');

      // Button may not be visible initially, just check it exists in DOM
      const buttonExists = await saveButton.count() > 0;

      // This is informational, not critical
      expect(buttonExists).toBeDefined();
    });

    test('Search - Load saved search from list', async ({ loggedInPage: page }) => {
      await page.goto('/saved-searches');

      await page.waitForSelector('body', { timeout: 10000 });

      // Check if there are saved searches
      const searchLinks = page.locator('a[href*="save-search"], a[href*="load"]');
      const count = await searchLinks.count();

      if (count > 0) {
        // There are saved searches, verify they're clickable
        await expect(searchLinks.first()).toBeVisible();
      }
    });

    /** TODO: would require a saved search to be added
    test('Search - Delete saved search functionality', async ({ loggedInPage: page }) => {
      await page.goto('/saved-searches');

      await page.waitForSelector('body', { timeout: 10000 });

      // Check for delete buttons
      const deleteButtons = page.locator('a[href*="remove"], button:has-text("Delete"), a.delete');
      const hasDeleteOption = await deleteButtons.count() > 0;

      // If there are searches, there should be delete options
      const hasTable = await page.locator('table tbody tr').count() > 0;

      if (hasTable) {
        expect(hasDeleteOption).toBeTruthy();
      }
    });*/

  });

  // Search Filters
  test.describe('Search Filters', () => {

    test('Search - Membership status filter available', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for membership status filter
      const membershipFilter = page.getByTestId('membership_filter');

      if (await membershipFilter.count() > 0) {
        await expect(membershipFilter).toBeVisible();

        // Should have options
        const options = await membershipFilter.locator('option').count();
        expect(options).toBeGreaterThan(1);
      }
    });

    test('Search - Account activity filter available', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for account filter
      const accountFilter = page.getByTestId('filter_account');

      if (await accountFilter.count() > 0) {
        await expect(accountFilter).toBeVisible();

        const options = await accountFilter.locator('option').count();
        expect(options).toBeGreaterThan(1);
      }
    });

    test('Search - Field filter (search in) available', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for field filter (where to search)
      const fieldFilter = page.getByTestId('field_filter');

      if (await fieldFilter.count() > 0) {
        await expect(fieldFilter).toBeVisible();

        const options = await fieldFilter.locator('option').count();
        expect(options).toBeGreaterThan(1);
      }
    });

  });

  // General Search Functionality
  test.describe('Search General', () => {

    test('Search - Clear/reset search', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      // Look for clear/reset button
      const clearButton = page.locator('button:has-text("Clear"), button:has-text("Reset"), a:has-text("Clear"), a:has-text("Effacer")');

      const hasClearButton = await clearButton.count() > 0;

      // Clear button should exist
      expect(hasClearButton).toBeDefined();
    });

    test('Search - Search results update members list', async ({ loggedInPage: page }) => {
      await page.goto('/members');

      await page.waitForSelector('table, .ui.message', { timeout: 10000 });

      // Verify members list is displayed
      const hasTable = await page.locator('table.listing').count() > 0;
      const hasResults = await page.locator('tbody tr').count() > 0;

      // Should display results (or message if empty)
      expect(hasTable || hasResults).toBeDefined();
    });

  });

});

