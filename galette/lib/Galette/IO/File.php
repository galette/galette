<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

/**
 * Files
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class File
{
    use FileTrait;

    /**
     * Default constructor
     *
     * @param string                $dest       File destination directory
     * @param ?array<int,string>    $extensions Array of permitted extensions
     * @param ?array<string,string> $mimes      Array of permitted mime types
     * @param ?int                  $maxlength  Maximum length for each file
     */
    public function __construct(
        string $dest,
        ?array $extensions = null,
        ?array $mimes = null,
        ?int $maxlength = null
    ) {
        $this->init(
            $dest,
            $extensions,
            $mimes,
            $maxlength
        );
    }
}
