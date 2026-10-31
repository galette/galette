<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Events;

use Galette\Core\GaletteMail;
use Galette\Tests\Fixtures\RecordingMemberListener;
use Galette\Tests\GaletteTestCase;

/**
 * Member events listener tests
 *
 * Emails are recorded instead of being sent.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberListenerTest extends GaletteTestCase
{
    protected int $seed = 20261031101512;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Get listener, with emailing enabled
     */
    private function getListener(): RecordingMemberListener
    {
        $this->preferences->pref_mail_method = GaletteMail::METHOD_SMTP;
        $this->preferences->pref_email = 'sender@example.com';
        $this->preferences->pref_email_newadh = 'admin1@example.com,admin2@example.com';
        $this->preferences->pref_bool_mailadh = false;

        return new RecordingMemberListener(
            preferences: $this->preferences,
            routeparser: $this->routeparser,
            history: $this->history,
            flash: $this->flash,
            login: $this->login,
            zdb: $this->zdb
        );
    }

    /**
     * Get flash messages added during the test
     *
     * @return array<string, array<int, string>>
     */
    private function getFlashMessages(): array
    {
        return $this->flash_data['slimFlash'] ?? [];
    }

    /**
     * Get history entries actions
     *
     * @return array<int, string>
     */
    private function getHistoryActions(): array
    {
        $select = $this->zdb->select(\Galette\Core\History::TABLE);
        $select->columns(['action_log']);
        $actions = [];
        foreach ($this->zdb->execute($select) as $row) {
            $actions[] = $row->action_log;
        }
        return $actions;
    }

    /**
     * New member gets their account email
     */
    public function testMemberAddedSendsAccountEmail(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();

        $member->setSendmail();
        $listener->memberAdded($member);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertTrue($sent[0]['ok']);
        $this->assertSame([$member->getEmail()], $sent[0]['recipients']);
        $this->assertStringContainsString('Your identifiers', $sent[0]['subject']);
        //password link and username are set
        $this->assertStringContainsString($member->login, $sent[0]['body']);
        $this->assertStringNotContainsString('{CHG_PWD_URI}', $sent[0]['body']);
        $this->assertStringNotContainsString('{LINK_VALIDITY}', $sent[0]['body']);

        $expected = "New account email sent to '" . $member->sname . ' (' . $member->getEmail() . ")'.";
        $this->assertSame(
            ['success_detected' => [$expected]],
            $this->getFlashMessages()
        );
        $this->assertContains($expected, $this->getHistoryActions());
    }

    /**
     * Nothing is sent to the member when they have not asked for it
     */
    public function testMemberAddedWithoutSendmail(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();

        $listener->memberAdded($member);

        $this->assertCount(0, $listener->mails);
        $this->assertSame([], $this->getFlashMessages());
    }

    /**
     * Administrators are notified of new members
     */
    public function testMemberAddedNotifiesAdmins(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;

        $listener->memberAdded($member);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertTrue($sent[0]['ok']);
        $this->assertContains('admin1@example.com', $sent[0]['recipients']);
        $this->assertContains('admin2@example.com', $sent[0]['recipients']);
        $this->assertNotContains($member->getEmail(), $sent[0]['recipients']);
        $this->assertStringContainsString(
            'New registration from ' . $member->sname,
            $sent[0]['subject']
        );

        $expected = "New account email sent to admin for '" . $member->sname . ' (' . $member->getEmail() . ")'.";
        $this->assertSame(
            ['success_detected' => [$expected]],
            $this->getFlashMessages()
        );
        $this->assertContains($expected, $this->getHistoryActions());
    }

    /**
     * Member gets an email when their account has been edited
     */
    public function testMemberEditedSendsEmail(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;

        $member->setSendmail();
        $listener->memberEdited($member);

        //admins are not told about an edition made by someone else
        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame([$member->getEmail()], $sent[0]['recipients']);
        $this->assertStringContainsString('Your account has been modified', $sent[0]['subject']);

        $expected = "Account modification email sent to '" . $member->sname . ' (' . $member->getEmail() . ")'.";
        $this->assertSame(
            ['success_detected' => [$expected]],
            $this->getFlashMessages()
        );
    }

    /**
     * Administrators are notified when a member edits their own account
     */
    public function testOwnAccountEditionNotifiesAdmins(): void
    {
        $member = $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;

        $listener->memberEdited($member);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertContains('admin1@example.com', $sent[0]['recipients']);
        $this->assertContains('admin2@example.com', $sent[0]['recipients']);
        $this->assertStringContainsString(
            'Account ' . $member->sname . ' has been modified',
            $sent[0]['subject']
        );
        $this->assertSame(
            ['success_detected' => ['Account modification email sent to admin.']],
            $this->getFlashMessages()
        );
    }

    /**
     * Sending failures are reported
     */
    public function testSendingFailures(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();
        $listener->fail = true;
        $this->preferences->pref_bool_mailadh = true;

        $member->setSendmail();
        $listener->memberAdded($member);

        //both messages have been tried
        $sent = $listener->getSentMessages();
        $this->assertCount(2, $sent);
        $this->assertFalse($sent[0]['ok']);
        $this->assertFalse($sent[1]['ok']);

        $member_str = $member->sname . ' (' . $member->getEmail() . ')';
        $expected = [
            "A problem happened while sending account email to '" . $member_str . "'",
            "A problem happened while sending email to admin for account '" . $member_str . "'."
        ];
        $this->assertSame(
            ['warning_detected' => $expected],
            $this->getFlashMessages()
        );
        $actions = $this->getHistoryActions();
        foreach ($expected as $msg) {
            $this->assertContains($msg, $actions);
        }

        //edition failures
        $listener->mails = [];
        $this->flash_data['slimFlash'] = [];
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $listener->memberEdited($member);
        $this->assertCount(2, $listener->getSentMessages());
        $this->assertSame(
            [
                'warning_detected' => [
                    "A problem happened while sending account email to '" . $member_str . "'",
                    'A problem happened while sending account email to admin'
                ]
            ],
            $this->getFlashMessages()
        );
    }

    /**
     * Nothing is sent when emailing is disabled
     */
    public function testMailDisabled(): void
    {
        $member = $this->getMemberOne();
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_DISABLED;

        $member->setSendmail();
        $listener->memberAdded($member);

        $this->assertCount(0, $listener->mails);
        $this->assertSame(
            [
                'warning_detected' => [
                    'You asked Galette to send a confirmation email to the member, but email has been disabled in the preferences.'
                ]
            ],
            $this->getFlashMessages()
        );
    }
}
