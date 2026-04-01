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

import { type Page, type Locator } from '@playwright/test';
import { DropdownHelper } from '../helpers/dropdown';

/**
 * Page Object for Contribution Form Page (Add/Edit)
 */
export class ContributionFormPage {
  readonly page: Page;
  readonly contributionTypeDropdown: Locator;
  readonly amountInput: Locator;
  readonly dateInput: Locator;
  readonly saveButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.contributionTypeDropdown = page.locator('#id_type_cotis');
    this.amountInput = page.locator('#montant_cotis');
    this.dateInput = page.locator('input[name="date_enreg"]');
    this.saveButton = page.locator('button[type="submit"][name="valid"]');
    this.cancelButton = page.locator('a:has-text("Cancel"), a:has-text("Annuler")').first();
  }

  /**
   * Navigate to add contribution page for a specific type
   * @param type - 'fee' or 'donation'
   */
  async gotoAdd(type: 'fee' | 'donation' = 'fee'): Promise<void> {
    await this.page.goto(`/contribution/${type}/add`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to edit contribution page
   * @param contributionId - The contribution ID
   * @param type - 'fee' or 'donation'
   */
  async gotoEdit(contributionId: number, type: 'fee' | 'donation' = 'fee'): Promise<void> {
    await this.page.goto(`/contribution/${type}/edit/${contributionId}`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Select contribution type using dropdown helper
   * @param type - The contribution type name or use 'first' for first item
   */
  async selectContributionType(type: string): Promise<void> {
    if (type === 'first') {
      await DropdownHelper.selectFirst(this.page, 'id_type_cotis');
    } else {
      await DropdownHelper.selectByText(this.page, 'id_type_cotis', type);
    }
  }

  /**
   * Set the contribution amount
   * @param amount - The amount as string (e.g., '30', '50.50')
   */
  async setAmount(amount: string): Promise<void> {
    await this.amountInput.clear();
    await this.amountInput.fill(amount);
  }

  /**
   * Set the contribution date
   * @param date - The date as string (format: YYYY-MM-DD or DD/MM/YYYY depending on locale)
   */
  async setDate(date: string): Promise<void> {
    await this.dateInput.clear();
    await this.dateInput.fill(date);
  }

  /**
   * Get the current amount value
   */
  async getAmount(): Promise<string> {
    return await this.amountInput.inputValue();
  }

  /**
   * Get the currently selected contribution type
   */
  async getSelectedContributionType(): Promise<string> {
    return await DropdownHelper.getSelectedText(this.page, 'id_type_cotis');
  }

  /**
   * Fill the contribution form with provided data
   * @param data - Object containing form field values
   */
  async fill(data: {
    type?: string;
    amount?: string;
    date?: string;
  }): Promise<void> {
    if (data.type) {
      await this.selectContributionType(data.type);
    }

    if (data.amount) {
      await this.setAmount(data.amount);
    }

    if (data.date) {
      await this.setDate(data.date);
    }
  }

  /**
   * Submit the form
   */
  async submit(): Promise<void> {
    await this.saveButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Cancel and go back
   */
  async cancel(): Promise<void> {
    await this.cancelButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Check if the form is visible
   */
  async isFormVisible(): Promise<boolean> {
    return await this.page.locator('form.ui.form').isVisible();
  }

  /**
   * Check if the amount field is visible
   */
  async isAmountFieldVisible(): Promise<boolean> {
    return await this.amountInput.isVisible();
  }
}

