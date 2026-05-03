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

import { type Page, type Locator, expect } from '@playwright/test';

/**
 * Helper functions for form interactions
 */
export class FormHelper {

  /**
   * Submit a form
   * @param page - Playwright Page object
   * @param formSelector - Optional custom form selector
   */
  static async submitForm(page: Page, formSelector: string = 'form.ui.form, form'): Promise<void> {
    const form = page.locator(formSelector);
    const submitButton = form.locator('button[type="submit"], input[type="submit"]').first();
    await submitButton.click();
  }

  /**
   * Assert a field has validation error
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the field
   */
  static async expectValidationError(page: Page, fieldName: string): Promise<void> {
    const field = page.locator(`[name="${fieldName}"]`).locator('..');
    await expect(field).toHaveClass(/error/);
  }

  /**
   * Fill a text field by name
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the field
   * @param value - Value to fill
   */
  static async fillTextField(page: Page, fieldName: string, value: string): Promise<void> {
    await page.locator(`[name="${fieldName}"]`).fill(value);
  }

  /**
   * Fill a textarea by name
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the textarea
   * @param value - Value to fill
   */
  static async fillTextarea(page: Page, fieldName: string, value: string): Promise<void> {
    await page.locator(`textarea[name="${fieldName}"]`).fill(value);
  }

  /**
   * Check a checkbox by name
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the checkbox
   */
  static async checkCheckbox(page: Page, fieldName: string): Promise<void> {
    const checkbox = page.locator(`input[type="checkbox"][name="${fieldName}"]`);
    if (!await checkbox.isChecked()) {
      await checkbox.check();
    }
  }

  /**
   * Uncheck a checkbox by name
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the checkbox
   */
  static async uncheckCheckbox(page: Page, fieldName: string): Promise<void> {
    const checkbox = page.locator(`input[type="checkbox"][name="${fieldName}"]`);
    if (await checkbox.isChecked()) {
      await checkbox.uncheck();
    }
  }

  /**
   * Select a radio button by value
   * @param page - Playwright Page object
   * @param fieldName - Name attribute of the radio group
   * @param value - Value to select
   */
  static async selectRadio(page: Page, fieldName: string, value: string): Promise<void> {
    await page.locator(`input[type="radio"][name="${fieldName}"][value="${value}"]`).check();
  }

  /**
   * Get form locator
   * @param page - Playwright Page object
   * @param formSelector - Optional custom form selector
   * @returns Locator for the form
   */
  static getForm(page: Page, formSelector: string = 'form.ui.form, form'): Locator {
    return page.locator(formSelector);
  }

  /**
   * Check if form has errors
   * @param page - Playwright Page object
   * @param formSelector - Optional custom form selector
   * @returns True if form has error class
   */
  static async hasErrors(page: Page, formSelector: string = 'form.ui.form, form'): Promise<boolean> {
    const form = this.getForm(page, formSelector);
    return await form.evaluate((el) => el.classList.contains('error'));
  }

  /**
   * Clear all form fields
   * @param page - Playwright Page object
   * @param formSelector - Optional custom form selector
   */
  static async clearForm(page: Page, formSelector: string = 'form.ui.form, form'): Promise<void> {
    const form = this.getForm(page, formSelector);
    const textInputs = form.locator('input[type="text"], input[type="email"], input[type="number"], textarea');
    const count = await textInputs.count();

    for (let i = 0; i < count; i++) {
      await textInputs.nth(i).clear();
    }
  }
}

