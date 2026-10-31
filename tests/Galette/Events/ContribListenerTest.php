<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Events;

use Galette\Core\GaletteMail;
use Galette\Core\Links;
use Galette\Entity\Contribution;
use Galette\Entity\Texts;
use Galette\Tests\Fixtures\RecordingContribListener;
use Galette\Tests\GaletteTestCase;

use function Safe\chmod;
use function Safe\file_put_contents;
use function Safe\unlink;

/**
 * Contribution events listener tests
 *
 * Emails are recorded instead of being sent.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContribListenerTest extends GaletteTestCase
{
    protected int $seed = 20261031114723;
    private ?string $script = null;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if ($this->script !== null && file_exists($this->script)) {
            unlink($this->script);
        }
        $this->cleanContributions();
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Get listener, with emailing enabled
     */
    private function getListener(): RecordingContribListener
    {
        $this->preferences->pref_mail_method = GaletteMail::METHOD_SMTP;
        $this->preferences->pref_email = 'sender@example.com';
        $this->preferences->pref_email_newadh = 'admin1@example.com,admin2@example.com';
        $this->preferences->pref_bool_mailadh = false;

        return new RecordingContribListener(
            preferences: $this->preferences,
            routeparser: $this->routeparser,
            history: $this->history,
            flash: $this->flash,
            login: $this->login,
            zdb: $this->zdb
        );
    }

    /**
     * Create a contribution for member one, emailing being still disabled
     *
     * @param int $type Contribution type
     */
    private function getContribution(int $type = 1): Contribution
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        $data = $this->getContribData();
        $data['id_type_cotis'] = $type;
        if ($type !== 1) {
            //donations do not extend membership
            unset($data['date_fin_cotis']);
        }
        $contrib = $this->createContrib($data);
        $this->login->logout();
        return $contrib;
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
     * Member gets a receipt for their contribution
     */
    public function testContributionAddedSendsReceipt(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();

        $contrib->setSendmail();
        $listener->contributionAdded($contrib);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertTrue($sent[0]['ok']);
        $this->assertSame([$this->adh->getEmail()], $sent[0]['recipients']);
        $this->assertStringContainsString('Your contribution', $sent[0]['subject']);
        $this->assertStringNotContainsString('{DEADLINE}', $sent[0]['body']);

        $this->assertSame([], $this->getFlashMessages());
        $this->assertContains(
            'Email sent to user ' . $this->adh->sname . ' (' . $this->adh->getEmail() . ')',
            $this->getHistoryActions()
        );
    }

    /**
     * Member gets a receipt for their donation
     */
    public function testDonationAddedSendsReceipt(): void
    {
        $contrib = $this->getContribution(5);
        $this->assertFalse($contrib->isFee());
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;

        $contrib->setSendmail();
        $listener->contributionAdded($contrib);

        $sent = $listener->getSentMessages();
        $this->assertCount(2, $sent);
        $this->assertSame([$this->adh->getEmail()], $sent[0]['recipients']);
        $this->assertStringContainsString('Your donation', $sent[0]['subject']);
        $this->assertContains('admin1@example.com', $sent[1]['recipients']);
        $this->assertStringContainsString('New donation for ' . $this->adh->sname, $sent[1]['subject']);
    }

    /**
     * Nothing is sent to the member when they have not asked for it
     */
    public function testContributionAddedWithoutSendmail(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();

        $listener->contributionAdded($contrib);

        $this->assertCount(0, $listener->mails);
        $this->assertSame([], $this->getFlashMessages());
    }

    /**
     * Administrators are notified of new contributions
     */
    public function testContributionAddedNotifiesAdmins(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;

        $listener->contributionAdded($contrib);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertTrue($sent[0]['ok']);
        $this->assertContains('admin1@example.com', $sent[0]['recipients']);
        $this->assertContains('admin2@example.com', $sent[0]['recipients']);
        $this->assertNotContains($this->adh->getEmail(), $sent[0]['recipients']);
        $this->assertStringContainsString('New contribution for ' . $this->adh->sname, $sent[0]['subject']);

        $this->assertContains(
            'Email sent to admin for user ' . $this->adh->sname . ' (' . $this->adh->getEmail() . ')',
            $this->getHistoryActions()
        );
    }

    /**
     * Member card and receipt links are generated when the text asks for them
     */
    public function testReceiptLinks(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();

        $texts = new Texts($this->preferences, $this->routeparser);
        $mtxt = $texts->getTexts('contrib', 'en_US');
        $this->assertTrue(
            $texts->setTexts(
                ref: 'contrib',
                lang: 'en_US',
                subject: $mtxt->tsubject,
                body: 'Card: {LINK_MEMBERCARD}{NEWLINE}Receipt: {LINK_CONTRIBPDF}'
            )
        );

        $contrib->setSendmail();
        $listener->contributionAdded($contrib);

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertStringNotContainsString('{LINK_', $sent[0]['body']);

        $select = $this->zdb->select(Links::TABLE);
        $links = [];
        foreach ($this->zdb->execute($select) as $row) {
            $links[(int)$row->target] = $row;
        }
        $this->assertArrayHasKey(Links::TARGET_MEMBERCARD, $links);
        //a membership fee gets an invoice
        $this->assertArrayHasKey(Links::TARGET_INVOICE, $links);
        $this->assertSame($this->adh->id, (int)$links[Links::TARGET_MEMBERCARD]->id);
        $this->assertSame($contrib->id, (int)$links[Links::TARGET_INVOICE]->id);

        foreach ($links as $link) {
            $this->assertStringContainsString(
                //links are given the encoded hash
                $this->routeparser->urlFor('directlink', ['hash' => base64_encode($link->hash)]),
                $sent[0]['body']
            );
        }
    }

    /**
     * Sending failures are reported
     */
    public function testSendingFailures(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $listener->fail = true;
        $this->preferences->pref_bool_mailadh = true;

        $contrib->setSendmail();
        $listener->contributionAdded($contrib);

        $sent = $listener->getSentMessages();
        $this->assertCount(2, $sent);
        $this->assertFalse($sent[0]['ok']);
        $this->assertFalse($sent[1]['ok']);

        $member_str = $this->adh->sname . ' (' . $this->adh->getEmail() . ')';
        $expected = [
            'A problem happened while sending contribution receipt to user ' . $member_str,
            'A problem happened while sending to admin notification for user ' . $member_str . ' contribution'
        ];
        $this->assertSame(
            ['warning_detected' => $expected],
            $this->getFlashMessages()
        );
        $actions = $this->getHistoryActions();
        foreach ($expected as $msg) {
            $this->assertContains($msg, $actions);
        }
    }

    /**
     * Nothing is sent when emailing is disabled
     */
    public function testMailDisabled(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $this->preferences->pref_bool_mailadh = true;
        $this->preferences->pref_mail_method = GaletteMail::METHOD_DISABLED;

        $contrib->setSendmail();
        $listener->contributionAdded($contrib);

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

    /**
     * Configure a post contribution script that fails
     */
    private function setFailingScript(): void
    {
        $this->script = GALETTE_CACHE_DIR . 'failing_script_' . $this->seed . '.sh';
        file_put_contents($this->script, "#!/bin/sh\necho 'Script has failed' >&2\n");
        chmod($this->script, 0o700);
        $this->preferences->pref_new_contrib_script = 'file://' . $this->script;
    }

    /**
     * Administrators are told when the post contribution script fails
     */
    public function testPostContributionScriptFailure(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $this->setFailingScript();

        $listener->contributionAdded($contrib);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred calling post contribution script');

        $sent = $listener->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertTrue($sent[0]['ok']);
        $this->assertContains('admin1@example.com', $sent[0]['recipients']);
        $this->assertSame('Post contribution script failed', $sent[0]['subject']);
        $this->assertStringContainsString('Script has failed', $sent[0]['body']);
        $this->assertSame([], $this->getFlashMessages());
    }

    /**
     * Post contribution script failure is reported when admins cannot be told
     */
    public function testPostContributionScriptFailureNotSent(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $listener->fail = true;
        $this->setFailingScript();

        $listener->contributionAdded($contrib);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred calling post contribution script');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Email to admin has not been sent');

        $this->assertCount(1, $listener->getSentMessages());
        $this->assertSame(
            ['warning_detected' => ['Post contribution script has failed.']],
            $this->getFlashMessages()
        );
        $this->assertContains('Post contribution script has failed.', $this->getHistoryActions());
    }

    /**
     * Post contribution script failure is logged when emailing is disabled
     */
    public function testPostContributionScriptFailureMailDisabled(): void
    {
        $contrib = $this->getContribution();
        $listener = $this->getListener();
        $this->preferences->pref_mail_method = GaletteMail::METHOD_DISABLED;
        $this->setFailingScript();

        $listener->contributionAdded($contrib);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred calling post contribution script');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Post contribution script has failed. Here was the data');

        $this->assertCount(0, $listener->mails);
    }
}
