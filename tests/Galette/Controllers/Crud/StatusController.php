<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Members statuses controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StatusController extends GaletteRoutingTestCase
{
    protected int $seed = 20261031153342;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        $delete = $this->zdb->delete(Status::TABLE);
        $delete->where->like('libelle_statut', 'Test status%');
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Get test status id
     *
     * @param string $label Status label
     */
    private function getStatusId(string $label): ?int
    {
        $id = (new Status($this->zdb))->getIdByLabel($label);
        return $id === false ? null : $id;
    }

    /**
     * Statuses are managed by staff only
     */
    public function testAccess(): void
    {
        $request = $this->createRequest('status');
        $this->expectLogin($this->app->handle($request));

        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $requests = [
            $request,
            $this->createRequest('editStatus', ['id' => '1']),
            $this->createRequest('removeStatus', ['id' => '1']),
            $this->createRequest('doAddStatus', [], 'POST')
                ->withParsedBody(['libelle_statut' => 'Test status by member', 'priorite_statut' => '50']),
        ];
        foreach ($requests as $request) {
            $this->expectAuthMiddlewareRefused($this->app->handle($request));
        }
        $this->assertNull($this->getStatusId('Test status by member'));
    }

    /**
     * Statuses lifecycle
     */
    public function testCrud(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('status'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Non-member', (string)$test_response->getBody());

        //add
        $request = $this->createRequest('doAddStatus', [], 'POST')
            ->withParsedBody(['libelle_statut' => 'Test status', 'priorite_statut' => '50']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['success_detected' => ['Status has been successfully added!']]
        );
        $id = $this->getStatusId('Test status');
        $this->assertNotNull($id);

        //duplicate
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['error_detected' => ['Status has not been added :(']]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'A status with label `Test status` already exists');

        //missing label
        $request = $this->createRequest('doAddStatus', [], 'POST')
            ->withParsedBody(['libelle_statut' => ' ', 'priorite_statut' => '50']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['error_detected' => ['Missing required status name!', 'Status has not been added :(']]
        );

        //edit
        $test_response = $this->app->handle($this->createRequest('editStatus', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('value="Test status"', $body);
        $this->assertStringContainsString('name="keep_translations"', $body);

        $request = $this->createRequest('doEditStatus', ['id' => (string)$id], 'POST')
            ->withParsedBody(['libelle_statut' => 'Test status edited', 'priorite_statut' => '60']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['success_detected' => ['Status #' . $id . ' has been successfully updated!']]
        );
        $this->assertSame($id, $this->getStatusId('Test status edited'));
        $this->assertSame(60, (int)(new Status($this->zdb))->get($id)->priorite_statut);

        $request = $this->createRequest('doEditStatus', ['id' => (string)$id], 'POST')
            ->withParsedBody(['libelle_statut' => '', 'priorite_statut' => '60']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('editStatus', ['id' => (string)$id]),
            ['error_detected' => ['Missing required status name!', 'Status #' . $id . ' has not been updated']]
        );

        //cancel
        $request = $this->createRequest('doEditStatus', ['id' => (string)$id], 'POST')
            ->withParsedBody(['cancel' => '1']);
        $this->expectRedirect($this->app->handle($request), $this->routeparser->urlFor('status'));

        //remove
        $test_response = $this->app->handle($this->createRequest('removeStatus', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString(
            'Remove status \'Test status edited\'',
            html_entity_decode((string)$test_response->getBody(), ENT_QUOTES)
        );

        $request = $this->createRequest('doRemoveStatus', ['id' => (string)$id], 'POST');
        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([])),
            $this->routeparser->urlFor('status'),
            ['error_detected' => ['Removal has not been confirmed!']]
        );
        $this->assertSame($id, $this->getStatusId('Test status edited'));

        $this->expectRedirect(
            $this->app->handle($request->withParsedBody(['confirm' => '1'])),
            $this->routeparser->urlFor('status'),
            ['success_detected' => ['Successfully deleted!']]
        );
        $this->assertNull($this->getStatusId('Test status edited'));
    }

    /**
     * Statuses in use and default status cannot be removed
     */
    public function testRemoveRefused(): void
    {
        $this->logSuperAdmin();
        $status = new Status($this->zdb);
        $this->assertTrue($status->add('Test status in use', 50));
        $id = $this->getStatusId('Test status in use');

        $member = $this->getMemberOne();
        $update = $this->zdb->update(Adherent::TABLE);
        $update->set([Status::PK => $id])->where([Adherent::PK => $member->id]);
        $this->zdb->execute($update);

        $request = $this->createRequest('doRemoveStatus', ['id' => (string)$id], 'POST')
            ->withParsedBody(['confirm' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['error_detected' => ['Cannot delete this label: it\'s still used']]
        );
        $this->assertSame($id, $this->getStatusId('Test status in use'));

        $request = $this->createRequest('doRemoveStatus', ['id' => (string)Status::DEFAULT_STATUS], 'POST')
            ->withParsedBody(['confirm' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('status'),
            ['error_detected' => ['An error occurred trying to delete :(']]
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'You cannot delete default status!');
    }
}
