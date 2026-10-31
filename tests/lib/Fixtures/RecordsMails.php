<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Core\GaletteMail;

/**
 * Record every mail instead of sending it, wherever it is built.
 *
 * The factory is reset by the base test case tear down.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait RecordsMails
{
    /** @var array<int, RecordingPHPMailer> Created mailers */
    private array $recording_mailers = [];

    /**
     * Record mails from now on
     *
     * @param array<int, int> $fail_on Messages, 1-based across all mailers, that must fail instead of leaving
     */
    protected function recordMails(array $fail_on = []): void
    {
        $this->recording_mailers = [];
        GaletteMail::setMailerFactory(function () use ($fail_on): RecordingPHPMailer {
            //mailers are used one after the other: messages already recorded are final
            $offset = count($this->getRecordedMails());
            $mailer = new RecordingPHPMailer();
            $mailer->fail_on = array_values(array_filter(
                array_map(fn(int $position): int => $position - $offset, $fail_on),
                fn(int $position): bool => $position > 0
            ));
            $this->recording_mailers[] = $mailer;
            return $mailer;
        });
    }

    /**
     * Get messages handed to all mailers, in order
     *
     * @return array<int, array{bcc: int, to: int, ok: bool, recipients: string[], subject: string, body: string}>
     */
    protected function getRecordedMails(): array
    {
        $messages = [];
        foreach ($this->recording_mailers as $mailer) {
            $messages = array_merge($messages, $mailer->sent);
        }
        return $messages;
    }
}
