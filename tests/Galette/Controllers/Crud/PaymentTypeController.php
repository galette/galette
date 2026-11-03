<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Entity\PaymentType;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Payment types controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaymentTypeController extends GaletteRoutingTestCase
{
    protected int $seed = 20261031152130;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(PaymentType::TABLE);
        $delete->where->like('type_name', 'Test payment type%');
        $this->zdb->execute($delete);
        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Get test payment type id
     *
     * @param string $name Type name
     */
    private function getTypeId(string $name): ?int
    {
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->where(['type_name' => $name]);
        $row = $this->zdb->execute($select)->current();
        return $row ? (int)$row->{PaymentType::PK} : null;
    }

    /**
     * Payment types are managed by staff only
     */
    public function testAccess(): void
    {
        $request = $this->createRequest('paymentTypes');
        $this->expectLogin($this->app->handle($request));

        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $requests = [
            $request,
            $this->createRequest('editPaymentType', ['id' => (string)PaymentType::CASH]),
            $this->createRequest('removePaymentType', ['id' => (string)PaymentType::CASH]),
            $this->createRequest('storePaymentType', [], 'POST')
                ->withParsedBody(['name' => 'Test payment type by member']),
        ];
        foreach ($requests as $request) {
            $this->expectAuthMiddlewareRefused($this->app->handle($request));
        }
        $this->assertNull($this->getTypeId('Test payment type by member'));
    }

    /**
     * Payment types lifecycle
     */
    public function testCrud(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('paymentTypes'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Credit card', (string)$test_response->getBody());

        //add
        $request = $this->createRequest('storePaymentType', [], 'POST')
            ->withParsedBody(['name' => 'Test payment type']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('paymentTypes'),
            ['success_detected' => ['Payment type \'Test payment type\' has been successfully added.']]
        );
        $id = $this->getTypeId('Test payment type');
        $this->assertNotNull($id);

        //missing name
        $request = $this->createRequest('storePaymentType', [], 'POST')
            ->withParsedBody(['name' => '']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('paymentTypes'),
            [
                'error_detected' => [
                    'Missing required payment type\'s name!',
                    'Payment type \'\' has not been added!'
                ]
            ]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Name cannot be empty');

        //edit
        $test_response = $this->app->handle($this->createRequest('editPaymentType', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('value="Test payment type"', $body);
        //custom labels translations can be kept
        $this->assertStringContainsString('name="keep_translations"', $body);

        $request = $this->createRequest('doEditPaymentType', ['id' => (string)$id], 'POST')
            ->withParsedBody(['name' => 'Test payment type edited']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('paymentTypes'),
            ['success_detected' => ['Payment type \'Test payment type edited\' has been successfully modified.']]
        );
        $this->assertSame($id, $this->getTypeId('Test payment type edited'));

        $request = $this->createRequest('doEditPaymentType', ['id' => (string)$id], 'POST')
            ->withParsedBody(['name' => '']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('editPaymentType', ['id' => (string)$id]),
            [
                'error_detected' => [
                    'Missing required payment type\'s name!',
                    //empty value is not set
                    'Payment type \'Test payment type edited\' has not been modified!'
                ]
            ]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Name cannot be empty');

        //cancel
        $request = $this->createRequest('doEditPaymentType', ['id' => (string)$id], 'POST')
            ->withParsedBody(['cancel' => '1']);
        $test_response = $this->app->handle($request);
        $this->expectRedirect($test_response, $this->routeparser->urlFor('paymentTypes'));

        //remove
        $test_response = $this->app->handle($this->createRequest('removePaymentType', ['id' => (string)$id]));
        $this->expectOK($test_response);
        $this->assertStringContainsString(
            'Remove payment type Test payment type edited',
            (string)$test_response->getBody()
        );

        $request = $this->createRequest('doRemovePaymentType', ['id' => (string)$id], 'POST');
        $this->expectRedirect(
            $this->app->handle($request->withParsedBody([])),
            $this->routeparser->urlFor('paymentTypes'),
            ['error_detected' => ['Removal has not been confirmed!']]
        );
        $this->assertSame($id, $this->getTypeId('Test payment type edited'));

        $this->expectRedirect(
            $this->app->handle($request->withParsedBody(['confirm' => '1'])),
            $this->routeparser->urlFor('paymentTypes'),
            ['success_detected' => ['Successfully deleted!']]
        );
        $this->assertNull($this->getTypeId('Test payment type edited'));
    }

    /**
     * System payment types cannot be removed, their label is translated
     */
    public function testSystemType(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle(
            $this->createRequest('editPaymentType', ['id' => (string)PaymentType::CASH])
        );
        $this->expectOK($test_response);
        //core labels translations cannot be kept
        $this->assertStringNotContainsString('name="keep_translations"', (string)$test_response->getBody());

        $request = $this->createRequest('doRemovePaymentType', ['id' => (string)PaymentType::CASH], 'POST')
            ->withParsedBody(['confirm' => '1']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('paymentTypes'),
            ['error_detected' => ['An error occurred trying to delete :(']]
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'You cannot delete system payment types!');
        $this->assertSame(PaymentType::CASH, $this->getTypeId('Cash'));
    }
}
