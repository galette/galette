<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use function Safe\finfo_open;
use function Safe\preg_match;

/**
 * Files a web server could run, refused on every upload
 *
 * Uploaded files are stored out of the web root, behind a deny rule; this is
 * what is left when neither holds, and no setting can lift it.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UnsafeFiles
{
    private const string EXTENSIONS = '/^(php\d*|phtml?|pht|phps|phar|pgif|shtml?|cgi|pl|py|sh|asp|aspx|jsp|jspx|htaccess|htpasswd)$/i';

    /** @var array<int,string> */
    private const array MIME_TYPES = [
        'text/x-php',
        'application/x-php',
        'application/x-httpd-php',
        'application/x-httpd-php-source',
    ];

    /**
     * Does a file name carry an extension a web server could run?
     *
     * Every extension is checked, not only the last one: some servers run
     * `file.php.jpg` as PHP.
     *
     * @param string $filename File name, as sent by the client
     */
    public static function hasUnsafeExtension(string $filename): bool
    {
        $parts = explode('.', basename($filename));
        array_shift($parts);
        foreach ($parts as $extension) {
            if (preg_match(self::EXTENSIONS, $extension) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is a MIME type one of a script a web server could run?
     *
     * @param string $mime MIME type
     */
    public static function isUnsafeMimeType(string $mime): bool
    {
        return in_array(strtolower($mime), self::MIME_TYPES, strict: true);
    }

    /**
     * Is a file, whatever its name, a script a web server could run?
     *
     * @param string $path Path to the file
     */
    public static function hasUnsafeContent(string $path): bool
    {
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        return $mime !== false && self::isUnsafeMimeType($mime);
    }
}
