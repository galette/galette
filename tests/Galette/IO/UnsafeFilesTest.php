<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\IO\UnsafeFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Safe\file_put_contents;
use function Safe\tempnam;
use function Safe\unlink;

/**
 * Unsafe files tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UnsafeFilesTest extends TestCase
{
    /**
     * File names
     *
     * @return array<string,array{string,bool}>
     */
    public static function filenamesProvider(): array
    {
        return [
            'php' => ['shell.php', true],
            'uppercase php' => ['shell.PHP', true],
            'php version' => ['shell.php5', true],
            'phtml' => ['shell.phtml', true],
            'phar' => ['shell.phar', true],
            'cgi' => ['shell.cgi', true],
            'htaccess' => ['.htaccess', true],
            'double extension' => ['shell.php.jpg', true],
            'pdf' => ['status.pdf', false],
            'zip' => ['archive.zip', false],
            'no extension' => ['README', false],
            'php in name only' => ['php.txt', false],
            'phpinfo extension' => ['notes.phpinfo', false],
        ];
    }

    /**
     * Test file names
     *
     * @param string $filename File name
     * @param bool   $unsafe   Expected result
     */
    #[DataProvider('filenamesProvider')]
    public function testHasUnsafeExtension(string $filename, bool $unsafe): void
    {
        $this->assertSame($unsafe, UnsafeFiles::hasUnsafeExtension($filename));
    }

    /**
     * Test MIME types and contents
     */
    public function testUnsafeContent(): void
    {
        $this->assertTrue(UnsafeFiles::isUnsafeMimeType('text/x-php'));
        $this->assertTrue(UnsafeFiles::isUnsafeMimeType('Application/X-HTTPD-PHP'));
        $this->assertFalse(UnsafeFiles::isUnsafeMimeType('application/pdf'));
        $this->assertFalse(UnsafeFiles::isUnsafeMimeType('text/plain'));

        $file = tempnam(sys_get_temp_dir(), 'galette_unsafe');
        try {
            file_put_contents($file, '<?php echo "pwned";');
            $this->assertTrue(UnsafeFiles::hasUnsafeContent($file));

            file_put_contents($file, "Some minutes\n");
            $this->assertFalse(UnsafeFiles::hasUnsafeContent($file));

            $this->assertFalse(UnsafeFiles::hasUnsafeContent(GALETTE_TESTS_PATH . '/fixtures/status.pdf'));
        } finally {
            unlink($file);
        }
    }
}
