<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;
use Safe\DateTime;

/**
* Galette ajax controller tests
*
* @author Johan Cwiklinski <johan@x-tnd.be>
*/
class AjaxControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20250802103040;

    /**
     * Test news route
     */
    public function testNews(): void
    {
        $request = $this->createRequest('ajaxNews');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //superadmin gets Galette news, from the test feed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Galette news', $body);
        $this->assertStringContainsString('Galette 1.0.0rc1', $body);

        //the fragment is not a full page
        $this->assertStringNotContainsString('<body', $body);
    }

    /**
     * Test contribution dates do not disclose due date of members out of reach
     */
    public function testContributionDates(): void
    {
        $member_two = $this->getMemberTwo();
        //due date is computed from contributions
        $insert = $this->zdb->insert(\Galette\Entity\Contribution::TABLE);
        $insert->values([
            \Galette\Entity\Adherent::PK => $member_two->id,
            \Galette\Entity\ContributionsTypes::PK => 1,
            'montant_cotis' => 10,
            'type_paiement_cotis' => \Galette\Entity\PaymentType::CASH,
            'info_cotis' => '',
            'date_enreg' => date('Y-m-d'),
            'date_debut_cotis' => date('Y-m-d'),
            'date_fin_cotis' => (new DateTime('+2 years'))->format('Y-m-d')
        ]);
        $this->zdb->execute($insert);

        $dates = function () use ($member_two): string {
            $request = $this->createRequest('contributionDates', [], 'POST')
                ->withParsedBody(['fee_id' => '1', 'member_id' => (string)$member_two->id]);
            $test_response = $this->app->handle($request);
            $this->assertSame(200, $test_response->getStatusCode());
            return (string)$test_response->getBody();
        };

        $this->logSuperAdmin();
        $expected = $dates();
        $this->login->logOut();

        $group = $this->logGroupManager();
        $this->assertNotSame($expected, $dates());

        $this->assertTrue($group->setMembers([$member_two]));
        $this->assertSame($expected, $dates());
        $this->login->logOut();
    }
}
