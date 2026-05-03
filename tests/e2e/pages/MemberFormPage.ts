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

import type { Page, Locator } from '@playwright/test';

export type MemberData = {
  lastName: string;
  firstName?: string;
  email?: string;
  address: string;
  zipcode: string;
  city: string;
  login: string;
  password: string;
};

/**
 * Page Object for the member creation/edit form (/member/add or /member/edit/{id}).
 */
export class MemberFormPage {
  readonly page: Page;
  readonly lastNameInput: Locator;
  readonly firstNameInput: Locator;
  readonly emailInput: Locator;
  readonly loginInput: Locator;
  readonly passwordInput: Locator;
  readonly passwordConfirmInput: Locator;
  readonly addressInput: Locator;
  readonly zipcodeInput: Locator;
  readonly cityInput: Locator;
  readonly submitButton: Locator;

  constructor(page: Page) {
    this.page          = page;
    this.lastNameInput = page.locator('#nom_adh');
    this.firstNameInput = page.locator('#prenom_adh');
    this.emailInput    = page.locator('#email_adh');
    this.loginInput    = page.locator('#login_adh');
    this.passwordInput = page.locator('#mdp_adh');
    this.passwordConfirmInput = page.locator('#mdp_adh2');
    this.addressInput  = page.locator('#adresse_adh');
    this.zipcodeInput  = page.locator('#cp_adh');
    this.cityInput  = page.locator('#ville_adh');
    this.submitButton  = page.locator('button[type="submit"][name="valid"]');
  }

  async goto(): Promise<void> {
    await this.page.goto('/member/add');
  }

  async fill(data: MemberData): Promise<void> {
    await this.lastNameInput.fill(data.lastName);
    if (data.firstName !== undefined) {
      await this.firstNameInput.fill(data.firstName);
    }
    if (data.email !== undefined) {
      await this.emailInput.fill(data.email);
    }
    if (data.address !== undefined) {
      await this.addressInput.fill(data.address);
    }
    if (data.zipcode !== undefined) {
      await this.zipcodeInput.fill(data.zipcode);
    }
    if (data.city !== undefined) {
      await this.cityInput.fill(data.city);
    }
    await this.loginInput.fill(data.login);
    await this.passwordInput.fill(data.password);
    await this.passwordConfirmInput.fill(data.password);
  }

  async submit(): Promise<void> {
    await this.submitButton.click();
  }
}
