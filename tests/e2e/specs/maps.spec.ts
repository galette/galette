/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Member position, from the member edition form
 *
 * The position is set in a modal: whatever happens there, the form being
 * filled must stay as it is.
 */

import { type Locator, type Page } from '@playwright/test';
import { test, expect } from '../fixtures/auth.fixture';
import { AclHelper } from '../helpers/acl';
import { expectNoViolation, format, listenViolations, violations } from '../fixtures/csp.fixture';

//his town is not a real one: no town is proposed when the map opens
const MEMBER_NAME = 'PENDRAGON Arthur';
const EDITED_NAME = 'Pendragon, not saved';

let memberId: number;

/**
 * Change test instance preferences
 */
async function testApi(page: Page, data: Record<string, unknown>): Promise<void> {
  const response = await page.request.post('/test/preferences', { data });
  expect(response.ok(), JSON.stringify(data)).toBeTruthy();
}

/**
 * Open the member edition form, with a field changed and not saved
 */
async function editMember(page: Page): Promise<void> {
  //background tiles are not what is tested: no network for them
  await page.route(/^https:\/\/[^/]*tile[^/]*\//, route => route.fulfill({ status: 204 }));
  await page.goto(`/member/edit/${memberId}`);
  await page.locator('#nom_adh').fill(EDITED_NAME);
}

/**
 * The form is still there, with what was typed in
 */
async function expectFormKept(page: Page): Promise<void> {
  await expect(page).toHaveURL(new RegExp(`/member/edit/${memberId}$`));
  await expect(page.locator('#nom_adh')).toHaveValue(EDITED_NAME);
}

/**
 * Modal displaying the member map, once its map is shown
 */
async function mapModal(page: Page): Promise<Locator> {
  const modal = page.locator('.maps-modal.visible');
  await expect(modal.locator('#map.leaflet-container')).toBeVisible();
  //would a town be proposed, the map is what is tested here
  const towns = page.locator('#possible_towns.visible');
  if (await towns.count() > 0) {
    await towns.locator('.cancel').click();
    await expect(towns).toHaveCount(0);
  }
  return modal;
}

/**
 * Close the message displayed after a change
 */
async function closeMessage(page: Page): Promise<void> {
  const message = page.locator('.ui.tiny.modal.visible');
  await expect(message).toBeVisible();
  await message.locator('.actions .button').click();
  await expect(message).toHaveCount(0);
}

test.describe.serial('Member position', () => {
  test.beforeAll(async ({ browser }) => {
    memberId = (await AclHelper.memberIds(browser, { member: MEMBER_NAME })).member;
  });

  test.beforeEach(async ({ page }) => {
    await testApi(page, { action: 'enable_maps' });
    await testApi(page, { action: 'remove_position', member: memberId });
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await testApi(page, { action: 'remove_position', member: memberId });
    await testApi(page, { action: 'disable_maps' });
    await page.close();
  });

  test('member form is kept when the map cannot be loaded', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await editMember(page);
    const localize = page.locator('.maps-member-none a.maps-localize');
    const failure = page.locator('.ui.tiny.modal.visible');
    const route = new RegExp(`/member/position/${memberId}$`);

    //service down
    await page.route(route, r => r.fulfill({ status: 503, body: 'Service Unavailable' }));
    await localize.click();
    await expect(failure.locator('.actions .primary')).toBeVisible();
    await expectFormKept(page);
    await failure.locator('.actions .button:not(.primary)').click();
    await expect(failure).toHaveCount(0);
    await page.unroute(route);

    //another page than the map, such as the login one of an expired session
    await page.route(route, r => r.fulfill({ status: 200, contentType: 'text/html', body: '<p>Login</p>' }));
    await localize.click();
    await expect(failure.locator('.actions .primary')).toBeVisible();
    await expectFormKept(page);
    await page.unroute(route);

    //retried once the map is available again
    await failure.locator('.actions .primary').click();
    await mapModal(page);
    await expectFormKept(page);
  });

  test('position is set and removed without leaving the member form', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await editMember(page);
    await expect(page.locator('.maps-member-none')).toBeVisible();
    await expect(page.locator('.maps-member-position')).toBeHidden();

    //set
    await page.locator('.maps-member-none a.maps-localize').click();
    let modal = await mapModal(page);
    await modal.locator('#map').click();
    await modal.locator('.leaflet-popup button').click();
    await expect(modal).toHaveCount(0);
    await closeMessage(page);
    await expect(page.locator('.maps-member-position .maps-minimap.leaflet-container')).toBeVisible();
    await expect(page.locator('.maps-member-none')).toBeHidden();
    await expectFormKept(page);

    //removed
    await page.locator('.maps-member-position a.maps-localize').click();
    modal = await mapModal(page);
    //the known position opens with its popup
    await modal.locator('.leaflet-popup button').click();
    await page.locator('.ui.modal.visible .confirm_remove').click();
    await expect(modal).toHaveCount(0);
    await closeMessage(page);
    await expect(page.locator('.maps-member-none')).toBeVisible();
    await expect(page.locator('.maps-member-position')).toBeHidden();
    await expectFormKept(page);

    //nothing left behind by the modals
    await expect(page.locator('.maps-modal')).toHaveCount(0);
  });

  test('a single modal opens on a double click', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await editMember(page);
    let requests = 0;
    await page.route(new RegExp(`/member/position/${memberId}$`), async route => {
      requests++;
      await route.continue();
    });

    await page.locator('.maps-member-none a.maps-localize').dblclick();
    await mapModal(page);
    await expect(page.locator('.maps-modal')).toHaveCount(1);
    expect(requests).toBe(1);
  });

  test('maps break no Content Security Policy', async ({ loggedInAs }) => {
    const page = await loggedInAs('admin');
    await listenViolations(page);
    //background tiles and address search are not what is tested: no network for them
    await page.route(/^https:\/\/[^/]*tile[^/]*\//, route => route.fulfill({ status: 204 }));
    await page.route(/^https:\/\/nominatim\.openstreetmap\.org\//, route => route.fulfill({ json: [] }));

    await expectNoViolation(page, ['/public/maps', '/maps/settings']);

    //address search, from browsers
    await page.goto('/public/maps');
    const search = page.locator('.leaflet-control-geocoder input');
    await search.fill('Rennes');
    await Promise.all([
      page.waitForRequest(/^https:\/\/nominatim\.openstreetmap\.org\//),
      search.press('Enter'),
    ]);
    let found = await violations(page);
    expect(found, format(found)).toEqual([]);

    //position modal of the member form
    await page.goto(`/member/edit/${memberId}`);
    await page.locator('.maps-member-none a.maps-localize').click();
    await mapModal(page);
    found = await violations(page);
    expect(found, format(found)).toEqual([]);
  });
});
