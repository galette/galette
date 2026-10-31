<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\FlashMessages;
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

    /**
     * Handle request and get JSON response
     *
     * @param \Slim\Psr7\Request $request Request
     *
     * @return array<string, mixed>
     */
    private function getJson(\Slim\Psr7\Request $request): array
    {
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['application/json'], $test_response->getHeader('Content-Type'));
        return json_decode((string)$test_response->getBody(), true);
    }

    /**
     * Messages from previous request are sent as JSON, then consumed
     */
    public function testMessages(): void
    {
        //messages set on previous request
        $storage = [
            'slimFlash' => [
                'loginfault' => ['Login fault'],
                'error_detected' => ['An error'],
                'error' => ['Another error'],
                'warning_detected' => ['A warning'],
                'warning' => ['Another warning'],
                'info_detected' => ['An information'],
                'info' => ['Another information'],
                'success_detected' => ['A success'],
                'succes' => ['Another success']
            ]
        ];
        $this->container->set(\Slim\Flash\Messages::class, new FlashMessages($storage));

        $this->assertSame(
            [
                'error' => [
                    'title' => '- ERROR -',
                    'icon' => 'exclamation circle',
                    'messages' => ['Login fault', 'An error', 'Another error']
                ],
                'warning' => [
                    'title' => '- WARNING -',
                    'icon' => 'exclamation triangle',
                    'messages' => ['A warning', 'Another warning']
                ],
                'info' => [
                    'title' => '',
                    'icon' => 'info',
                    'messages' => ['An information', 'Another information']
                ],
                'success' => [
                    'title' => '',
                    'icon' => 'check circle outline',
                    'messages' => ['A success', 'Another success']
                ]
            ],
            $this->getJson($this->createRequest('ajaxMessages'))
        );
        //read messages are not displayed again
        $this->assertSame([], $storage['slimFlash']);
    }

    /**
     * Towns, countries and regions are suggested from members
     */
    public function testSuggestions(): void
    {
        $this->getMemberOne();

        $suggestions = [
            'suggestTown' => ['artel', [['title' => 'Martel']]],
            'suggestCountry' => ['ntarct', [['title' => 'Antarctique']]],
            'suggestRegion' => ['aribb', [['title' => 'Caribbean']]],
        ];
        foreach ($suggestions as $route => [$term, $expected]) {
            $this->assertSame(
                ['success' => true, 'results' => $expected],
                $this->getJson($this->createRequest($route, ['term' => $term], 'POST')),
                $route
            );
            $this->assertSame(
                ['success' => true, 'results' => []],
                $this->getJson($this->createRequest($route, ['term' => 'nothing to suggest'], 'POST')),
                $route
            );
        }

        //towns are also suggested from birth places
        $this->assertCount(
            1,
            $this->getJson($this->createRequest('suggestTown', ['term' => 'Meunier'], 'POST'))['results']
        );
    }

    /**
     * Telemetry information preview and registration
     */
    public function testTelemetry(): void
    {
        $requests = [
            $this->createRequest('telemetryInfos'),
            $this->createRequest('setRegistered'),
        ];
        foreach ($requests as $request) {
            $this->expectLogin($this->app->handle($request));
        }

        $this->logSuperAdmin();
        $test_response = $this->app->handle($requests[0]);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringStartsWith('<pre>', $body);
        $infos = json_decode(substr($body, 5, -6), true);
        $this->assertArrayHasKey('galette', $infos);

        $this->assertSame(
            ['message' => 'Thank you for registering!'],
            $this->getJson($requests[1])
        );
        $select = $this->zdb->select(\Galette\Core\Preferences::TABLE);
        $select->where(['nom_pref' => 'pref_registration_date']);
        $this->assertStringStartsWith(date('Y-m-d'), (string)$this->zdb->execute($select)->current()->val_pref);
    }

    /**
     * Members proposed for a contribution
     */
    public function testContributionMembers(): void
    {
        $member = $this->getMemberOne();

        $request = $this->createRequest('contributionMembers', [], 'POST')
            ->withParsedBody(['search' => 'Durand']);
        $this->expectLogin($this->app->handle($request));

        $this->logSuperAdmin();
        $results = $this->getJson($request)['results'];
        $this->assertContains($member->id, array_column($results, 'value'));

        //search on identifier
        $results = $this->getJson(
            $this->createRequest('contributionMembers', [], 'POST')
                ->withParsedBody(['search' => (string)$member->id, 'page' => '1'])
        )['results'];
        $this->assertSame([$member->id], array_column($results, 'value'));

        //search from route
        $results = $this->getJson(
            $this->createRequest('contributionMembers', ['page' => '1', 'search' => 'Durand'], 'POST')
                ->withParsedBody([])
        )['results'];
        $this->assertContains($member->id, array_column($results, 'value'));
    }

    /**
     * Password strength is checked against preferences, possibly not yet saved
     */
    public function testPasswordStrength(): void
    {
        $request = $this->createRequest('checkPassword', [], 'POST');

        $result = $this->getJson($request->withParsedBody([
            'value' => 'short',
            'pref_password_length' => '12',
            'pref_password_strength' => (string)\Galette\Enums\PasswordStrength::Strong->value,
            'pref_password_blacklist' => '0'
        ]));
        $this->assertFalse($result['valid']);
        $this->assertSame(\Galette\Enums\PasswordStrength::Strong->value, $result['required']);
        $this->assertNotEmpty($result['errors']);
        $this->assertNull($result['warnings']);
        $this->assertNotEmpty($result['rules']);

        $result = $this->getJson($request->withParsedBody([
            'value' => 'Th1s-1s/A_Str0ng*Passw0rd',
            'pref_password_length' => '12',
            'pref_password_strength' => (string)\Galette\Enums\PasswordStrength::Strong->value
        ]));
        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertGreaterThanOrEqual(\Galette\Enums\PasswordStrength::Strong->value, $result['score']);
    }
}
