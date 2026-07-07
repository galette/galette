<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * A PHPMailer double that records what would have been sent, without
 * ever opening a connection or delivering anything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RecordingPHPMailer extends PHPMailer
{
    /** @var array<int, array{bcc: int, to: int, ok: bool}> Recipients count per message */
    public array $sent = [];
    /** @var array<int, int> Messages, 1-based, that must fail instead of leaving */
    public array $fail_on = [];
    /** @var int Number of times the SMTP connection was closed */
    public int $close_count = 0;
    /** @var bool Whether keep-alive was enabled on at least one send */
    public bool $keepalive_seen = false;

    /**
     * Record the message instead of sending it.
     */
    public function send(): bool
    {
        $this->keepalive_seen = $this->keepalive_seen || (bool)$this->SMTPKeepAlive;
        $ok = !in_array(count($this->sent) + 1, $this->fail_on, strict: true);
        $this->sent[] = [
            'bcc' => count($this->getBccAddresses()),
            'to'  => count($this->getToAddresses()),
            'ok'  => $ok
        ];
        if (!$ok) {
            $this->ErrorInfo = 'Recorded failure for message ' . count($this->sent);
        }
        return $ok;
    }

    /**
     * Record connection closing.
     */
    public function smtpClose(): void
    {
        $this->close_count++;
    }
}
