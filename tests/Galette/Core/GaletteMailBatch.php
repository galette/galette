<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\Fixtures\RecordingGaletteMail;
use Galette\Tests\GaletteTestCase;

/**
 * Mailing batch/keep-alive tests, without any real sending.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteMailBatch extends GaletteTestCase
{
    protected int $seed = 20240131082140;

    /**
     * Build a recipients map (email => name)
     *
     * @param int $count Number of recipients
     *
     * @return array<string, string>
     */
    private function makeRecipients(int $count): array
    {
        $recipients = [];
        for ($i = 1; $i <= $count; $i++) {
            $recipients['user' . $i . '@example.com'] = 'User ' . $i;
        }
        return $recipients;
    }

    /**
     * Test that a mailing is split into several BCC messages
     */
    public function testBatchedSendChunksRecipients(): void
    {
        //keep-alive only applies to SMTP/GMAIL methods
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_SMTP;
        $this->preferences->pref_mail_smtp_keepalive = true;
        $this->preferences->pref_mail_batch_size = 2;
        $this->preferences->pref_mail_batch_delay = 0;

        $mail = new RecordingGaletteMail($this->preferences);
        $mail->setSubject('Batch subject');
        $mail->setMessage('Batch body');
        $mail->setRecipients($this->makeRecipients(5));

        $res = $mail->send();
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $res);

        //5 recipients / batch of 2 => 3 messages (2, 2, 1)
        $this->assertSame([2, 2, 1], array_column($mail->recorder->sent, 'bcc'));
        //main recipient (To) is always the sender, one per message
        $this->assertSame([1, 1, 1], array_column($mail->recorder->sent, 'to'));
        //connection kept alive and explicitly closed once
        $this->assertTrue($mail->recorder->keepalive_seen);
        $this->assertSame(1, $mail->recorder->close_count);

        $this->preferences->pref_mail_batch_size = 0;
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_DISABLED;
    }

    /**
     * Test that with batching disabled a single BCC message is sent
     */
    public function testSingleMessageWhenBatchDisabled(): void
    {
        $this->preferences->pref_mail_batch_size = 0;

        $mail = new RecordingGaletteMail($this->preferences);
        $mail->setSubject('Single subject');
        $mail->setMessage('Single body');
        $mail->setRecipients($this->makeRecipients(5));

        $res = $mail->send();
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $res);

        //one message only, everyone in BCC, To = sender
        $this->assertCount(1, $mail->recorder->sent);
        $this->assertSame(5, $mail->recorder->sent[0]['bcc']);
        $this->assertSame(1, $mail->recorder->sent[0]['to']);
        //single path does not use the explicit keep-alive close
        $this->assertSame(0, $mail->recorder->close_count);
    }

    /**
     * Test that batching is not used when recipients fit in a single batch
     */
    public function testBatchNotUsedWhenUnderBatchSize(): void
    {
        $this->preferences->pref_mail_batch_size = 10;

        $mail = new RecordingGaletteMail($this->preferences);
        $mail->setSubject('Subject');
        $mail->setMessage('Body');
        $mail->setRecipients($this->makeRecipients(3));

        $res = $mail->send();
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $res);

        //3 <= 10 => single message, no chunking, no explicit close
        $this->assertCount(1, $mail->recorder->sent);
        $this->assertSame(3, $mail->recorder->sent[0]['bcc']);
        $this->assertSame(0, $mail->recorder->close_count);

        $this->preferences->pref_mail_batch_size = 0;
    }
}
