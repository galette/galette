<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\GaletteMail;
use Galette\Core\Mailing;
use Galette\Core\MailingHistory;
use Galette\Core\MailingQueue;
use Galette\Entity\Reminder;
use Galette\Filters\MembersList;
use Galette\Tests\Fixtures\RecordsMails;
use Galette\Tests\GaletteRoutingTestCase;

use function Safe\json_decode;

/**
 * Mailings controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingsController extends GaletteRoutingTestCase
{
    use RecordsMails;

    protected int $seed = 20260915103000;

    /** @var array<string, mixed> Preferences changed by sending tests */
    private array $mail_preferences = [];

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        foreach (
            [
                'pref_mail_method',
                'pref_mail_batch_size',
                'pref_mail_batch_delay',
                'pref_mail_hourly_limit',
                'pref_mail_daily_limit'
            ] as $pref
        ) {
            $this->mail_preferences[$pref] = $this->preferences->$pref;
        }
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->mail_preferences as $pref => $value) {
            $this->preferences->$pref = $value;
        }
        $this->zdb->execute($this->zdb->delete(MailingQueue::TABLE));
        $this->zdb->execute($this->zdb->delete(MailingHistory::TABLE));
        parent::tearDown();
    }

    /**
     * Prepare a mailing to members one and two, as a super admin, with mails recorded
     *
     * @param array<int, int> $fail_on Messages, 1-based, that must fail instead of leaving
     *
     * @return array<int, string> Recipients addresses
     */
    private function prepareMailing(array $fail_on = []): array
    {
        $this->recordMails($fail_on);
        $this->preferences->pref_mail_method = GaletteMail::METHOD_PHPMAIL;
        $this->preferences->pref_mail_batch_size = 0;
        $this->preferences->pref_mail_batch_delay = 0;
        $this->preferences->pref_mail_hourly_limit = 0;
        $this->preferences->pref_mail_daily_limit = 0;

        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->logSuperAdmin();

        $filters = new MembersList();
        $filters->selected = [$member_one->id, $member_two->id];
        $this->session->{$this->getMailingFilterName()} = $filters;
        $this->session->mailing = null;

        return [$member_one->getEmail(), $member_two->getEmail()];
    }

    /**
     * Get the session name of members selected for mailing
     */
    private function getMailingFilterName(): string
    {
        $controller = new \Galette\Controllers\Crud\MailingsController($this->container);
        return $controller->getFilterName($controller->getDefaultFilterName());
    }

    /**
     * Build a mailing form request
     *
     * @param string               $action Submit button name
     * @param array<string, mixed> $values Additional values
     */
    private function mailingRequest(string $action, array $values = []): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->createRequest('doMailing', [], 'POST')
            ->withParsedBody(
                $values + [
                    $action => '1',
                    'mailing_objet' => 'Test mailing subject',
                    'mailing_corps' => 'Test mailing body'
                ]
            );
    }

    /**
     * Get stored mailings
     *
     * @return array<int, \ArrayObject<string, mixed>>
     */
    private function getStoredMailings(): array
    {
        $mailings = [];
        foreach ($this->zdb->execute($this->zdb->select(MailingHistory::TABLE)) as $row) {
            $mailings[] = $row;
        }
        return $mailings;
    }

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

    /**
     * Compose a mailing: checks, preview, reset and save
     */
    public function testCompose(): void
    {
        $this->prepareMailing();

        $request = $this->mailingRequest('mailing_go', ['mailing_objet' => ' ', 'mailing_corps' => '']);
        $this->expectRedirect(
            $this->app->handle($request),
            $this->routeparser->urlFor('mailing'),
            ['error_detected' => ['Please type an object for the message.', 'Please enter a message.']]
        );
        $this->assertSame(Mailing::STEP_START, $this->session->mailing->current_step);

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_go')),
            $this->routeparser->urlFor('mailing')
        );
        $mailing = $this->session->mailing;
        $this->assertSame(Mailing::STEP_PREVIEW, $mailing->current_step);
        $this->assertSame('Test mailing subject', $mailing->subject);
        $this->assertCount(2, $mailing->recipients);

        $test_response = $this->app->handle($this->createRequest('mailing'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Test mailing subject', (string)$test_response->getBody());

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_reset')),
            $this->routeparser->urlFor('mailing')
        );
        $this->assertSame(Mailing::STEP_START, $this->session->mailing->current_step);

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_save')),
            $this->routeparser->urlFor('mailings'),
            ['success_detected' => ['Mailing has been successfully saved.']]
        );
        $this->assertNull($this->session->mailing);
        $stored = $this->getStoredMailings();
        $this->assertCount(1, $stored);
        $this->assertSame('Test mailing subject', $stored[0]->mailing_subject);
        $this->assertEmpty($stored[0]->mailing_sent);

        //nothing has been sent
        $this->assertSame([], $this->getRecordedMails());
    }

    /**
     * Mailing cannot start without recipients, nor with email disabled
     */
    public function testCannotStart(): void
    {
        $this->prepareMailing();

        $this->session->{$this->getMailingFilterName()} = new MembersList();
        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_go')),
            $this->routeparser->urlFor('members'),
            ['error_detected' => ['No member selected for mailing!']]
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'No member selected for mailing');

        $this->preferences->pref_mail_method = GaletteMail::METHOD_DISABLED;
        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_confirm')),
            $this->routeparser->urlFor('slash'),
            ['error_detected' => ['Trying to load mailing while email is disabled in preferences.']]
        );

        $this->assertSame([], $this->getRecordedMails());
        $this->assertSame([], $this->getStoredMailings());
    }

    /**
     * Send a mailing
     */
    public function testSend(): void
    {
        $recipients = $this->prepareMailing();
        $this->session->redirect_mailing = $this->routeparser->urlFor('mailings');

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_confirm', ['mailing_html' => '1'])),
            $this->routeparser->urlFor('mailings'),
            ['success_detected' => ['Mailing has been successfully sent!']]
        );

        $mails = $this->getRecordedMails();
        $this->assertCount(1, $mails);
        $mail = $mails[0];
        $this->assertTrue($mail['ok']);
        $this->assertSame('Test mailing subject', $mail['subject']);
        $this->assertStringContainsString('Test mailing body', $mail['body']);
        //members are hidden from each other, the sender is the main recipient
        $this->assertSame(1, $mail['to']);
        $this->assertSame(2, $mail['bcc']);
        $this->assertSame([$this->preferences->pref_email], array_slice($mail['recipients'], 0, 1));
        $this->assertEqualsCanonicalizing($recipients, array_slice($mail['recipients'], 1));

        $stored = $this->getStoredMailings();
        $this->assertCount(1, $stored);
        $this->assertNotEmpty($stored[0]->mailing_sent);
        $this->assertNull($this->session->mailing);
        $this->assertNull($this->session->redirect_mailing ?? null);
    }

    /**
     * A mailing that could not be sent can be sent again
     */
    public function testSendError(): void
    {
        $this->prepareMailing(fail_on: [1]);

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_confirm')),
            $this->routeparser->urlFor('mailing'),
            ['error_detected' => ['Recorded failure for message 1']]
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, '[Mailings] Message was not sent.');

        //what has been composed is kept, nothing is stored
        $this->assertSame(Mailing::STEP_START, $this->session->mailing->current_step);
        $this->assertSame('Test mailing subject', $this->session->mailing->subject);
        $this->assertSame([], $this->getStoredMailings());
        $this->assertCount(1, $this->getRecordedMails());
    }

    /**
     * A mailing that has partly left is stored, and not offered again
     */
    public function testSendPartial(): void
    {
        $recipients = $this->prepareMailing(fail_on: [2]);
        $this->preferences->pref_mail_batch_size = 1;

        $this->expectRedirect(
            $this->app->handle($this->mailingRequest('mailing_confirm')),
            $this->routeparser->urlFor('mailings'),
            [
                'error_detected' => [
                    'Recorded failure for message 2',
                    'The mailing has only been sent to 1 recipient(s) out of 2. It has been stored, unsent, so you can check what has been delivered before sending it again.'
                ]
            ]
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, '[Mailings] Message was only partly sent, to 1 of 2 recipient(s).');

        $mails = $this->getRecordedMails();
        $this->assertCount(2, $mails);
        $this->assertSame([true, false], array_column($mails, 'ok'));
        $this->assertSame(1, $mails[0]['bcc']);
        $this->assertSame(1, $mails[1]['bcc']);

        $stored = $this->getStoredMailings();
        $this->assertCount(1, $stored);
        $this->assertEmpty($stored[0]->mailing_sent);
        $this->assertNull($this->session->mailing);
        $this->assertCount(2, $recipients);
    }

    /**
     * With sending limits, mailing is queued then sent from the progress page
     */
    public function testQueued(): void
    {
        $recipients = $this->prepareMailing();
        $this->preferences->pref_mail_daily_limit = 10;

        $test_response = $this->app->handle($this->mailingRequest('mailing_confirm'));
        $stored = $this->getStoredMailings();
        $this->assertCount(1, $stored);
        $mailing_id = (int)$stored[0]->mailing_id;
        $this->expectRedirect(
            $test_response,
            $this->routeparser->urlFor('mailingQueue', ['id' => (string)$mailing_id])
        );
        $this->assertEmpty($stored[0]->mailing_sent);
        $this->assertNull($this->session->mailing);
        //nothing has been sent yet
        $this->assertSame([], $this->getRecordedMails());

        $test_response = $this->app->handle($this->createRequest('mailingQueue', ['id' => (string)$mailing_id]));
        $this->expectOK($test_response);

        $request = $this->createRequest('mailingProcessQueue', [], 'POST')
            ->withParsedBody(['id' => (string)$mailing_id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $progress = json_decode((string)$test_response->getBody(), associative: true);
        $this->assertSame(2, $progress['batch_sent']);
        $this->assertSame(0, $progress['batch_failed']);
        $this->assertTrue($progress['done']);

        $mails = $this->getRecordedMails();
        $this->assertCount(1, $mails);
        $this->assertSame('Test mailing subject', $mails[0]['subject']);
        $this->assertEqualsCanonicalizing($recipients, array_slice($mails[0]['recipients'], 1));
        $this->assertNotEmpty($this->getStoredMailings()[0]->mailing_sent);
    }

    /**
     * Queued mailing that fails to leave stays in queue
     */
    public function testQueuedError(): void
    {
        $this->prepareMailing(fail_on: [1]);
        $this->preferences->pref_mail_daily_limit = 10;

        $this->app->handle($this->mailingRequest('mailing_confirm'));
        $mailing_id = (int)$this->getStoredMailings()[0]->mailing_id;

        $request = $this->createRequest('mailingProcessQueue', [], 'POST')
            ->withParsedBody(['id' => (string)$mailing_id]);
        $progress = json_decode((string)$this->app->handle($request)->getBody(), associative: true);
        $this->assertSame(0, $progress['batch_sent']);
        $this->assertFalse($progress['done']);
        $this->assertSame(2, $progress['remaining']);

        $select = $this->zdb->select(MailingQueue::TABLE);
        $select->where(['mailing_id' => $mailing_id]);
        foreach ($this->zdb->execute($select) as $row) {
            $this->assertSame(MailingQueue::STATUS_PENDING, (int)$row->status);
            $this->assertSame(1, (int)$row->attempts);
            $this->assertSame('Recorded failure for message 1', $row->last_error);
        }
        $this->assertEmpty($this->getStoredMailings()[0]->mailing_sent);
    }

    /**
     * Done and cancel give up current mailing
     */
    public function testDoneCancel(): void
    {
        $this->prepareMailing();

        foreach (['mailing_done' => 'mailings', 'mailing_cancel' => 'members'] as $action => $route) {
            //where the mailing has been started from, members list by default
            if ($route === 'mailings') {
                $this->session->redirect_mailing = $this->routeparser->urlFor($route);
            }
            $this->app->handle($this->mailingRequest('mailing_go'));
            $this->assertNotNull($this->session->mailing);

            $this->expectRedirect(
                $this->app->handle($this->mailingRequest($action)),
                $this->routeparser->urlFor($route)
            );
            $this->assertNull($this->session->mailing ?? null);
            $this->assertNull($this->session->redirect_mailing ?? null);
            $this->assertNull($this->session->{$this->getMailingFilterName()} ?? null);

            //selection is gone, restore it
            $filters = new MembersList();
            $filters->selected = [$this->getMemberOne()->id];
            $this->session->{$this->getMailingFilterName()} = $filters;
        }
        $this->assertSame([], $this->getRecordedMails());
    }

    /**
     * Queued reminders are sent one by one from the progress page
     */
    public function testRemindersQueue(): void
    {
        $this->prepareMailing();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $reminders = [];
        foreach ([$member_one, $member_two] as $member) {
            $reminder = new Reminder();
            $reminder->type = Reminder::LATE;
            $reminder->dest = $member;
            $reminders[] = $reminder;
        }
        $queue = new MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(2, $queue->enqueueReminders($reminders));

        $test_response = $this->app->handle($this->createRequest('remindersQueue'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('Sending reminders', (string)$test_response->getBody());

        $request = $this->createRequest('remindersProcessQueue', [], 'POST');
        $progress = json_decode((string)$this->app->handle($request)->getBody(), associative: true);
        $this->assertSame(1, $progress['batch_sent']);
        $this->assertFalse($progress['done']);

        $progress = json_decode((string)$this->app->handle($request)->getBody(), associative: true);
        $this->assertSame(1, $progress['batch_sent']);
        $this->assertTrue($progress['done']);

        $mails = $this->getRecordedMails();
        $this->assertCount(2, $mails);
        $this->assertEqualsCanonicalizing(
            [[$member_one->getEmail()], [$member_two->getEmail()]],
            array_column($mails, 'recipients')
        );
        $this->assertSame([1, 1], array_column($mails, 'to'));
    }
}
