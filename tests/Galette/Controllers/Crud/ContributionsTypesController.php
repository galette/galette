<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Entity\ContributionsTypes;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Contributions types controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionsTypesController extends GaletteRoutingTestCase
{
    protected int $seed = 20261011103000;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(ContributionsTypes::TABLE);
        $delete->where->like('libelle_type_cotis', 'Test type %');
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test contributions types are managed by staff only
     */
    public function testAccess(): void
    {
        $create = $this->preferences->pref_bool_groupsmanagers_create_contributions;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;

        $this->logGroupManager();
        $requests = [
            $this->createRequest('contributionsTypes'),
            $this->createRequest('editContributionType', ['id' => '1']),
            $this->createRequest('doAddContributionType', [], 'POST')
                ->withParsedBody(['libelle_type_cotis' => 'Test type by manager', 'cotis_extension' => '0']),
            $this->createRequest('doEditContributionType', ['id' => '1'], 'POST')
                ->withParsedBody(['libelle_type_cotis' => 'Test type by manager', 'cotis_extension' => '0']),
        ];
        foreach ($requests as $request) {
            $this->expectAuthMiddlewareRefused($this->app->handle($request));
        }
        $this->login->logOut();
        $this->preferences->pref_bool_groupsmanagers_create_contributions = $create;

        $select = $this->zdb->select(ContributionsTypes::TABLE);
        $select->where(['libelle_type_cotis' => 'Test type by manager', 'cotis_extension' => '0']);
        $this->assertSame(0, $this->zdb->execute($select)->count());

        $this->logSuperAdmin();
        $request = $this->createRequest('doAddContributionType', [], 'POST')
            ->withParsedBody(['libelle_type_cotis' => 'Test type by admin', 'cotis_extension' => '0']);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('contributionsTypes')]], $test_response->getHeaders());

        $select = $this->zdb->select(ContributionsTypes::TABLE);
        $select->where(['libelle_type_cotis' => 'Test type by admin', 'cotis_extension' => '0']);
        $this->assertSame(1, $this->zdb->execute($select)->count());
    }

    /**
     * Test keeping translations when a label changes
     */
    public function testRenameKeepsTranslations(): void
    {
        $this->logSuperAdmin();
        $this->i18n->changeLanguage('en_US');
        $l10n = $this->container->get(\Galette\Core\L10n::class);

        $ctype = new ContributionsTypes($this->zdb);
        $this->assertTrue($ctype->add(label: 'Test type to rename', description: '', amount: null, extension: 0));
        $id = $ctype->getIdByLabel('Test type to rename');
        $this->assertTrue($l10n->updateDynamicTranslation('Test type to rename', 'fr_FR.utf8', 'Type de test à renommer'));

        //edit form proposes to keep translations
        $test_response = $this->app->handle($this->createRequest('editContributionType', ['id' => (string)$id]));
        $this->assertStringContainsString(
            'name="keep_translations" id="libelle_type_cotis-keep-translations" value="1" checked="checked"',
            (string)$test_response->getBody()
        );

        $request = $this->createRequest('doEditContributionType', ['id' => (string)$id], 'POST')
            ->withParsedBody([
                'libelle_type_cotis' => 'Test type renamed',
                'cotis_extension' => '0',
                'keep_translations' => '1'
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame('Type de test à renommer', $l10n->getDynamicTranslation('Test type renamed', 'fr_FR.utf8'));

        //unchecked box: translations are reset
        $request = $this->createRequest('doEditContributionType', ['id' => (string)$id], 'POST')
            ->withParsedBody([
                'libelle_type_cotis' => 'Test type renamed again',
                'cotis_extension' => '0'
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame('', $l10n->getDynamicTranslation('Test type renamed again', 'fr_FR.utf8'));

        //core labels have no checkbox
        $test_response = $this->app->handle($this->createRequest('editContributionType', ['id' => '1']));
        $body = (string)$test_response->getBody();
        $this->assertStringNotContainsString('name="keep_translations"', $body);
        $this->assertStringContainsString('libelle_type_cotis-translation-hint', $body);
    }
}
