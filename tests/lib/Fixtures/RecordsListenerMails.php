<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Core\GaletteMail;
use Galette\Core\Preferences;

/**
 * Event listeners helper: hand out recording mails, and keep them for checks.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait RecordsListenerMails
{
    /** @var array<int, RecordingGaletteMail> Mails created by the listener */
    public array $mails = [];
    /** @var bool Whether created mails must fail to leave */
    public bool $fail = false;

    private Preferences $mail_preferences;

    /**
     * Create a recording mail.
     */
    protected function createMail(): GaletteMail
    {
        $mail = new RecordingGaletteMail($this->mail_preferences);
        if ($this->fail) {
            $mail->fail_on = [1];
        }
        $this->mails[] = $mail;
        return $mail;
    }

    /**
     * Get messages handed to the mailer by all created mails
     *
     * @return array<int, array{bcc: int, to: int, ok: bool, recipients: string[], subject: string, body: string}>
     */
    public function getSentMessages(): array
    {
        $messages = [];
        foreach ($this->mails as $mail) {
            if (isset($mail->recorder)) {
                $messages = array_merge($messages, $mail->recorder->sent);
            }
        }
        return $messages;
    }
}
