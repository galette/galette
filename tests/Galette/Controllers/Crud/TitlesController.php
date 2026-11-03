<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Entity\Title;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Titles controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TitlesController extends GaletteRoutingTestCase
{
    protected int $seed = 20261031151208;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(Title::TABLE);
        $delete->where->like('long_label', 'Test title%');
        $this->zdb->execute($delete);
        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Get test title id
     *
     * @param string $long Long label
     */
    private function getTitleId(string $long): ?int
    {
        $select = $this->zdb->select(Title::TABLE);
        $select->where(['long_label' => $long]);
        $row = $this->zdb->execute($select)->current();
        return $row ? (int)$row->{Title::PK} : null;
    }

    /**
     * Titles are managed by staff only
     */
    public function testAccess(): void
    {
        $request = $this->createRequest('titles');
        $this->expectLogin($this->app->handle($request));

        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $requests = [
            $request,
            $this->createRequest('editTitle', ['id' => '1']),
            $this->createRequest('removeTitle', ['id' => '3']),
            $this->createRequest('storeTitle', [], 'POST')
                ->withParsedBody(['short_label' => 'Tst.', 'long_label' => 'Test title by member']),
        ];
        foreach ($requests as $request) {
            $this->expectAuthMiddlewareRefused($this->app->handle($request));
        }
        $this->assertNull($this->getTitleId('Test title by member'));
    }

    /**
     * Titles lifecycle
     */
    public function testCrud(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('titles'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Mrs.', (string)$test_response->getBody());

        //add
        $request = $this->createRequest('storeTitle', [], 'POST')
            ->withParsedBody(['short_label' => 'Tst.', 'long_label' => 'Test title']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('titles'),
            ['success_detected' => ['Title \'Tst.\' has been successfully added.']]
        );
        $id = $this->getTitleId('Test title');
        $this->assertNotNull($id);

        //missing long label
        $request = $this->createRequest('storeTitle', [], 'POST')
            ->withParsedBody(['short_label' => 'Tst2.', 'long_label' => '']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('titles'),
            [
                'error_detected' => [
                    'Missing required title\'s short or long form!',
                    'Title \'Tst2.\' has not been added!'
                ]
            ]
        );

        //edit
        $test_response = $this->app->handle($this->createRequest('editTitle', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString('value="Test title"', (string)$test_response->getBody());

        $request = $this->createRequest('doEditTitle', ['id' => (string)$id], 'POST')
            ->withParsedBody(['short_label' => 'Tst.', 'long_label' => 'Test title edited']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('titles'),
            ['success_detected' => ['Title \'Tst.\' has been successfully modified.']]
        );
        $this->assertSame($id, $this->getTitleId('Test title edited'));

        $request = $this->createRequest('doEditTitle', ['id' => (string)$id], 'POST')
            ->withParsedBody(['short_label' => '', 'long_label' => 'Test title edited']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('editTitle', ['id' => (string)$id]),
            [
                'error_detected' => [
                    'Missing required title\'s short or long form!',
                    //empty value is not set
                    'Title \'Tst.\' has not been modified!'
                ]
            ]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Trying to set empty value for titleshort');

        //cancel
        $request = $this->createRequest('doEditTitle', ['id' => (string)$id], 'POST')
            ->withParsedBody(['cancel' => '1']);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('titles'));

        //remove
        $test_response = $this->app->handle($this->createRequest('removeTitle', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Remove title Tst.', (string)$test_response->getBody());

        $request = $this->createRequest('doRemoveTitle', ['id' => (string)$id], 'POST');
        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([])),
            $this->routeparser->urlFor('titles'),
            ['error_detected' => ['Removal has not been confirmed!']]
        );
        $this->assertSame($id, $this->getTitleId('Test title edited'));

        $this->expectRedirect(
            $this->app->handle($request->withParsedBody(['confirm' => '1'])),
            $this->routeparser->urlFor('titles'),
            ['success_detected' => ['Successfully deleted!']]
        );
        $this->assertNull($this->getTitleId('Test title edited'));
    }

    /**
     * Mr. and Mrs. titles cannot be removed
     */
    public function testRemoveProtected(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest('doRemoveTitle', ['id' => (string)Title::MR], 'POST')
            ->withParsedBody(['confirm' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('titles'),
            ['error_detected' => ['An error occurred trying to delete :(']]
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'You cannot delete Mr. or Mrs. titles!');
        $this->assertSame('Mr.', (new Title(Title::MR))->short);
    }
}
