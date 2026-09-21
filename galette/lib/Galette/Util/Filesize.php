<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

/**
 * File sizes, written the way a reader takes them in
 *
 * One formatter for the whole application: the size an import file shows, the
 * one an upload error names, and the one a form announces before the upload.
 * A limit is stored in kilobytes and a file is measured in bytes, so both are
 * offered rather than left to each caller to convert - and to get wrong.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Filesize
{
    /** Bytes in a kilobyte */
    public const int KILOBYTE = 1024;

    /**
     * Human readable size, from a number of bytes
     *
     * @param int|float $bytes Size in bytes
     */
    public static function fromBytes(int|float $bytes): string
    {
        return match (true) {
            $bytes >= self::KILOBYTE ** 3 => round($bytes / self::KILOBYTE ** 3, 2) . ' Go',
            $bytes >= self::KILOBYTE ** 2 => round($bytes / self::KILOBYTE ** 2, 2) . ' Mo',
            $bytes >= self::KILOBYTE => round($bytes / self::KILOBYTE, 2) . ' Ko',
            default => (int)$bytes . ' octets',
        };
    }

    /**
     * Human readable size, from a number of kilobytes
     *
     * That is the unit the upload size preferences are expressed in.
     *
     * @param int|float $kilobytes Size in kilobytes
     */
    public static function fromKilobytes(int|float $kilobytes): string
    {
        return self::fromBytes($kilobytes * self::KILOBYTE);
    }
}
