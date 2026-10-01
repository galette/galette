<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\GaletteMail;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Mailings controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingsController extends GaletteRoutingTestCase
{
    protected int $seed = 20260915103000;

    /**
     * Test the mailing form is still reachable once a mailing has been sent
     *
     * Sending a mailing used to leave the members filters at null in the
     * session; the key was still there, so the form took the null for a
     * filters instance.
     */
    public function testAddAfterSentMailing(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;

        $controller = new \Galette\Controllers\Crud\MailingsController($this->container);
        $filter_name = $controller->getFilterName($controller->getDefaultFilterName());

        $this->logSuperAdmin();
        $this->session->$filter_name = null;

        $request = $this->createRequest('mailing', query_params: ['mailing_new' => '1']);
        $test_response = $this->app->handle($request);

        $this->preferences->pref_mail_method = $mail_method;
        $this->expectOK($test_response);
        $this->assertStringContainsString('name="mailing_objet"', (string)$test_response->getBody());
    }

    /**
     * Test group managers can only reuse mailings they sent
     */
    public function testGroupManagerMailingsHistory(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;
        $this->preferences->pref_bool_groupsmanagers_mailings = true;

        $member_two = $this->getMemberTwo();
        $insert = $this->zdb->insert(\Galette\Core\MailingHistory::TABLE);
        $insert->values([
            'mailing_sender' => null,
            'mailing_subject' => 'Sent by an admin',
            'mailing_body' => 'Not for group managers',
            'mailing_date' => '2026-01-01 00:00:00',
            'mailing_recipients' => \Safe\json_encode([$member_two->id => $member_two->email]),
            'mailing_sent' => true
        ]);
        $this->zdb->execute($insert);
        $select = $this->zdb->select(\Galette\Core\MailingHistory::TABLE);
        $select->where(['mailing_subject' => 'Sent by an admin']);
        $mailing_id = (int)$this->zdb->execute($select)->current()->mailing_id;
        $this->assertGreaterThan(0, $mailing_id);

        $this->logGroupManager();
        $requests = [
            $this->createRequest('mailing', query_params: ['from' => (string)$mailing_id]),
            $this->createRequest('mailingPreview', ['id' => (string)$mailing_id]),
            $this->createRequest('previewAttachment', ['id' => (string)$mailing_id, 'pos' => '0']),
            $this->createRequest('mailingQueue', ['id' => (string)$mailing_id]),
        ];
        foreach ($requests as $request) {
            $test_response = $this->app->handle($request);
            $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
            $this->assertStringNotContainsString('Not for group managers', (string)$test_response->getBody());
            $this->expectFlashData(
                [
                    'error_detected' => [
                        'You do not have permission for requested URL.'
                    ]
                ]
            );
        }

        $request = $this->createRequest('mailingProcessQueue', [], 'POST');
        $request = $request->withParsedBody(['id' => (string)$mailing_id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(403, $test_response->getStatusCode());

        //mailings disabled for group managers
        $this->preferences->pref_bool_groupsmanagers_mailings = false;
        $request = $this->createRequest('mailing', query_params: ['mailing_new' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'You do not have permission for requested URL.'
                ]
            ]
        );

        $this->preferences->pref_mail_method = $mail_method;
        $this->login->logOut();
    }
}
