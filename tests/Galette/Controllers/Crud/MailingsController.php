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

    /**
     * Test group managers only remind late members of groups they manage
     */
    public function testGroupManagerReminder(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;
        $this->preferences->pref_bool_groupsmanagers_mailings = true;

        $member_two = $this->getMemberTwo();
        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update
            ->set(['date_echeance' => '2020-01-01'])
            ->where([\Galette\Entity\Adherent::PK => $member_two->id]);
        $this->zdb->execute($update);

        $group = $this->logGroupManager();
        $reminded = function () use ($member_two): bool {
            $request = $this->createRequest('mailing', query_params: ['reminder' => '1']);
            $this->expectOK($this->app->handle($request));
            $mailing = $this->session->mailing;
            $ids = array_map(
                fn($member) => $member->id,
                array_merge($mailing->recipients, $mailing->unreachables)
            );
            return in_array($member_two->id, $ids, strict: true);
        };

        //late member is not part of a managed group
        $this->assertFalse($reminded());

        $this->assertTrue($group->setMembers([$member_two]));
        $this->assertTrue($reminded());

        $this->preferences->pref_mail_method = $mail_method;
        $this->login->logOut();
    }

    /**
     * Test recipients selection: dropdown, added rows and stored recipients
     */
    public function testRecipientsSelection(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;

        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        //member two cannot be reached by email
        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update
            ->set(['email_adh' => ''])
            ->where([\Galette\Entity\Adherent::PK => $member_two->id]);
        $this->zdb->execute($update);

        $this->logSuperAdmin();

        //no mailing in progress
        $this->session->mailing = null;
        $request = $this->createRequest('mailingRecipients', [], 'POST')
            ->withParsedBody(['recipients' => [(string)$member_one->id]]);
        $test_response = $this->app->handle($request);
        $this->assertSame(400, $test_response->getStatusCode());

        $this->session->mailing = new \Galette\Core\Mailing($this->preferences, [$member_one]);
        $request = $this->createRequest('mailing');
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('id="mailing_recipients_modal"', $body);
        $this->assertStringContainsString('id="recipient_add"', $body);
        $this->assertStringContainsString('id="recipient_row_' . $member_one->id . '"', $body);
        $this->assertStringNotContainsString('id="recipient_row_' . $member_two->id . '"', $body);

        $request = $this->createRequest('mailingRecipientRow', [], 'POST')
            ->withParsedBody(['id_adh' => (string)$member_two->id]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('id="recipient_row_' . $member_two->id . '"', $body);
        $this->assertStringContainsString('data-linked="0"', $body);
        $this->assertStringContainsString('dont red icon', $body);

        $request = $this->createRequest('mailingRecipientRow', [], 'POST')
            ->withParsedBody(['id_adh' => '0']);
        $test_response = $this->app->handle($request);
        $this->assertSame(404, $test_response->getStatusCode());

        $request = $this->createRequest('mailingRecipients', [], 'POST')
            ->withParsedBody(['recipients' => [(string)$member_one->id, (string)$member_two->id]]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $mailing = $this->session->mailing;
        $this->assertSame([$member_one->id], array_map(fn($member) => $member->id, $mailing->recipients));
        $this->assertSame([$member_two->id], array_map(fn($member) => $member->id, $mailing->unreachables));
        $this->assertSame([$member_two->id], array_map(fn($member) => $member->id, $this->session->labels));

        //every recipient removed
        $request = $this->createRequest('mailingRecipients', [], 'POST')
            ->withParsedBody([]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->assertStringContainsString('No member selected (yet).', (string)$test_response->getBody());
        $this->assertCount(0, $this->session->mailing->recipients);
        $this->assertCount(0, $this->session->mailing->unreachables);

        $this->session->mailing = null;
        $this->preferences->pref_mail_method = $mail_method;
        $this->login->logOut();
    }

    /**
     * Test group managers can only add members of groups they manage as recipients
     */
    public function testGroupManagerRecipientsSelection(): void
    {
        $mail_method = $this->preferences->pref_mail_method;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;
        $this->preferences->pref_bool_groupsmanagers_mailings = true;

        $member_two = $this->getMemberTwo();
        $group = $this->logGroupManager();
        $this->session->mailing = new \Galette\Core\Mailing($this->preferences, []);

        //member two is not part of a managed group
        $request = $this->createRequest('mailingRecipientRow', [], 'POST')
            ->withParsedBody(['id_adh' => (string)$member_two->id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(404, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'requested 1 member(s), only 0 are accessible or exist.');

        $request = $this->createRequest('mailingRecipients', [], 'POST')
            ->withParsedBody(['recipients' => [(string)$member_two->id]]);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'requested 1 member(s), only 0 are accessible or exist.');
        $this->assertCount(0, $this->session->mailing->recipients);

        $this->assertTrue($group->setMembers([$member_two]));
        $request = $this->createRequest('mailingRecipientRow', [], 'POST')
            ->withParsedBody(['id_adh' => (string)$member_two->id]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->assertStringContainsString(
            'id="recipient_row_' . $member_two->id . '"',
            (string)$test_response->getBody()
        );

        //mailings disabled for group managers
        $this->preferences->pref_bool_groupsmanagers_mailings = false;
        $test_response = $this->app->handle($request);
        $this->assertSame(403, $test_response->getStatusCode());

        $this->session->mailing = null;
        $this->preferences->pref_mail_method = $mail_method;
        $this->login->logOut();
    }
}
