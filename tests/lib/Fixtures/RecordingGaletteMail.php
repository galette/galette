<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Core\GaletteMail;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * GaletteMail using the recording PHPMailer double.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RecordingGaletteMail extends GaletteMail
{
    public RecordingPHPMailer $recorder;

    /**
     * Return the recording mailer.
     */
    protected function createMailer(): PHPMailer
    {
        $this->recorder = new RecordingPHPMailer();
        return $this->recorder;
    }
}
