<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Exception;

use RuntimeException;
use Throwable;

/**
 * Missing built asset exception
 *
 * Assets are not versioned; they are built with `npm run build`, and shipped
 * already built in release archives. A missing one is a broken installation,
 * not something a user can act upon from the interface.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MissingAssetException extends RuntimeException
{
    /**
     * Construct the exception
     *
     * @param string         $path     Path of the missing file
     * @param int            $code     Exception code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        private readonly string $path,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf(
                'Missing Galette asset "%s"; assets may not have been built.',
                $path
            ),
            $code,
            $previous
        );
    }

    /**
     * Path of the missing file
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
