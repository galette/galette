<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Handlers;

use Analog\Analog;
use Galette\Core\Logs;
use Slim\Exception\HttpException;

/**
 * Error handler, logs uncaught exceptions the same way as the rest of Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ErrorHandler extends \Slim\Handlers\ErrorHandler
{
    /**
     * Write to the error log
     */
    protected function writeToErrorLog(): void
    {
        $request = $this->request->getMethod() . ' ' . $this->request->getUri()->getPath();

        //client errors (not found, forbidden, ...) are not application errors: no trace
        if ($this->exception instanceof HttpException && $this->statusCode < 500) {
            Analog::log(
                $this->exception->getTitle() . ' | ' . $request,
                in_array($this->statusCode, [404, 405], strict: true) ? Analog::INFO : Analog::WARNING
            );
            return;
        }

        Logs::exception($this->exception, 'Uncaught exception on ' . $request);
    }
}
