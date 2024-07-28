<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Exception;

use LogicException;
use Throwable;

/**
 * Missing plugin exception
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MissingPluginException extends LogicException
{
    /**
     * Construct the exception
     */
    public function __construct(string $id, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Module "%s" does not exist!',
                $id
            ),
            $code,
            $previous
        );
    }
}
