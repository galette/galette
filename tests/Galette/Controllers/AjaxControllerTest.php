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

use function Safe\json_decode;

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
     * Test contribution dates route is granted from any contribution permission
     */
    public function testContributionDates(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        //member two speaks catalan, messages are checked in english
        $this->assertTrue($member_two->check(['pref_lang' => 'en_US'], [], []));
        $this->assertTrue($member_two->store());

        $group = new \Galette\Entity\Group();
        $group->setName('Group 1');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setManagers([$member_two]));
        $this->assertTrue($group->setMembers([$member_one, $member_two]));

        $request = $this->createRequest('contributionDates', method: 'POST')
            ->withParsedBody(['fee_id' => '1', 'member_id' => (string)$member_one->id]);

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($group->getId()));

        //groups manager cannot create contributions with default preferences
        $create_contributions = $this->preferences->pref_bool_groupsmanagers_create_contributions;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->expectAuthMiddlewareRefused($this->app->handle($request));

        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $test_response = $this->app->handle($request);
        $this->preferences->pref_bool_groupsmanagers_create_contributions = $create_contributions; //reset

        $this->assertSame(200, $test_response->getStatusCode());
        $dates = json_decode((string)$test_response->getBody(), associative: true);
        $this->assertArrayHasKey('date_debut_cotis', $dates);
        $this->assertArrayHasKey('date_fin_cotis', $dates);
    }

    /**
     * Test contribution dates do not disclose due date of members out of reach
     */
    public function testContributionDatesMemberScope(): void
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

        $create_contributions = $this->preferences->pref_bool_groupsmanagers_create_contributions;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $group = $this->logGroupManager();
        $this->assertNotSame($expected, $dates());

        $this->assertTrue($group->setMembers([$member_two]));
        $this->assertSame($expected, $dates());
        $this->preferences->pref_bool_groupsmanagers_create_contributions = $create_contributions;
        $this->login->logOut();
    }
}
