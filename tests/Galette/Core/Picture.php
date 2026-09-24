<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\Fixtures\ExposedPicture;
use Galette\Tests\GaletteTestCase;

use function Safe\file_put_contents;
use function Safe\getimagesize;
use function Safe\glob;
use function Safe\realpath;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * Picture tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture extends GaletteTestCase
{
    private \Galette\Core\Picture $picture;
    private string $tmp_dir;
    /** @var string[] */
    private array $expected_badchars = [
        '.',
        '\\',
        "'",
        ' ',
        '/',
        ':',
        '*',
        '?',
        '"',
        '<',
        '>',
        '|'
    ];

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->picture = new \Galette\Core\Picture();
        $this->tmp_dir = sys_get_temp_dir() . '/galette-picture-' . uniqid() . '/';
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach (['sub/dir/', 'sub/', ''] as $dir) {
            $path = $this->tmp_dir . $dir;
            if (is_dir($path)) {
                foreach (glob($path . '*') as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($path);
            }
        }
        parent::tearDown();
    }

    /**
     * Test storage directory creation
     */
    public function testEnsureStorePath(): void
    {
        $path = $this->tmp_dir . 'sub/dir/';
        $picture = new ExposedPicture($path);

        $this->assertFalse(is_dir($path));
        $this->assertTrue($picture->publicEnsureStorePath());
        $this->assertTrue(is_dir($path));
        $this->expectLogEntry(\Analog\Analog::INFO, 'Pictures directory `' . $path . '` has been created');

        //already existing
        $this->assertTrue($picture->publicEnsureStorePath());

        //a file is in the way
        $file = $this->tmp_dir . 'sub/dir/afile';
        file_put_contents($file, 'content');
        $picture = new ExposedPicture($file);
        $this->assertFalse($picture->publicEnsureStorePath());
        $this->expectLogEntry(\Analog\Analog::ERROR, '`' . $file . '` is not a directory.');
    }

    /**
     * Test resizing to another path, with custom sizes
     */
    public function testResizeImage(): void
    {
        $picture = new ExposedPicture($this->tmp_dir);
        $this->assertTrue($picture->publicEnsureStorePath());
        $this->expectLogEntry(\Analog\Analog::INFO, 'has been created');

        $sources = [
            //landscape, 800x400
            'jpg' => [GALETTE_ROOT . '../tests/fake_image.jpg', 100, 50],
            //portrait, 350x450
            'png' => [GALETTE_ROOT . '../galette/webroot/themes/default/images/default.png', 78, 100],
            //landscape, 2208x1024
            'webp' => [GALETTE_ROOT . '../galette/webroot/themes/default/images/galette.webp', 100, 46],
        ];

        foreach ($sources as $ext => [$source, $expected_width, $expected_height]) {
            $source_size = getimagesize($source);
            $dest = $this->tmp_dir . 'resized.' . $ext;
            $this->assertTrue($picture->publicResizeImage(
                source: $source,
                ext: $ext,
                dest: $dest,
                max_width: 100,
                max_height: 100
            ));

            [$width, $height] = getimagesize($dest);
            $this->assertSame($expected_width, $width, $ext);
            $this->assertSame($expected_height, $height, $ext);

            //source is kept as is
            $this->assertSame($source_size, getimagesize($source));
        }

        //picture's own sizes are used by default
        $dest = $this->tmp_dir . 'default.jpg';
        $this->assertTrue($picture->publicResizeImage($sources['jpg'][0], 'jpg', $dest));
        [$width, $height] = getimagesize($dest);
        $this->assertSame(200, $width);
        $this->assertSame(100, $height);

        $this->assertFalse($picture->publicResizeImage(
            source: $sources['jpg'][0],
            ext: 'bmp',
            dest: $dest,
            max_width: 100,
            max_height: 100
        ));
    }

    /**
     * Test defaults after initialization
     */
    public function testDefaults(): void
    {
        $picture = new \Galette\Core\Picture();
        $this->assertNull($picture->getDestDir());
        $this->assertNull($picture->getFileName());

        $expected_exts = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        $this->assertSame(implode(', ', $expected_exts), $picture->getAllowedExts());

        $expected_mimes = [
            'jpg'    =>    'image/jpeg',
            'png'    =>    'image/png',
            'gif'    =>    'image/gif',
            'webp'   =>    'image/webp'
        ];
        $this->assertSame($expected_mimes, $picture->getAllowedMimeTypes());

        $this->assertSame(
            '`' . implode('`, `', $this->expected_badchars) . '`',
            $this->picture->getBadChars()
        );
    }

    /**
     * Test setters
     */
    public function testSetters(): void
    {
        $this->assertNull($this->picture->getDestDir());
        $this->assertNull($this->picture->getFileName());

        $this->picture->setDestDir(__DIR__);
        $this->assertSame(__DIR__, $this->picture->getDestDir());

        $this->picture->setFileName('myfile.png');
        $this->assertSame('myfile.png', $this->picture->getFileName());
    }

    /**
     * Test mimetype guess
     * FileInfo installed.
     */
    public function testFileInfoMimeType(): void
    {
        $url = realpath(GALETTE_ROOT . '../tests/fake_image.jpg');
        $this->assertNotFalse($url);
        $this->assertSame('image/jpeg', $this->picture->getMimeType($url));

        $url = realpath(GALETTE_ROOT . '../galette/webroot/themes/default/images/default.png');
        $this->assertNotFalse($url);
        $this->assertSame('image/png', $this->picture->getMimeType($url));

        $url = realpath(GALETTE_ROOT . '../galette/webroot/themes/default/images/galette.webp');
        $this->assertNotFalse($url);
        $this->assertSame('image/webp', $this->picture->getMimeType($url));

        $url = realpath(GALETTE_ROOT . '../tests/test.gif');
        $this->assertNotFalse($url);
        $this->assertSame('image/gif', $this->picture->getMimeType($url));

        $this->assertSame('text/x-php', $this->picture->getMimeType(__DIR__ . '/Picture.php'));
    }

    /**
     * Test storage
     */
    public function testStore(): void
    {
        foreach ($this->expected_badchars as $badchar) {
            $expected = \Galette\Core\Picture::INVALID_FILENAME;
            if ($badchar == '.') {
                //will give an invalid extension
                $expected = \Galette\Core\Picture::INVALID_EXTENSION;
            }
            $uploaded_file = new \Slim\Psr7\UploadedFile(
                'none',
                'file-with-' . $badchar . '-char.jpg'
            );
            $this->assertSame($expected, $this->picture->storeFile($uploaded_file));
            if ($badchar == '.') {
                // `.` badchar will fail on extension check
                $this->expectLogEntry(\Analog\Analog::ERROR, 'Invalid extension for file file-with-.-char.jpg');
            } else {
                $this->expectLogEntry(\Analog\Analog::ERROR, sprintf('Invalid filename `file-with-%s-char.jpg`', $badchar));
            }
        }

        $files = [
            'myfile.png',
            'another-file.jpg',
            'accentued-éè-file.gif',
            'a3.jpg',
            'a.jpg',
            '3.jpg'
        ];

        foreach ($files as $file) {
            $uploaded_file = new \Slim\Psr7\UploadedFile(
                fileNameOrStream: 'none',
                name: $file,
                type: 'image/jpeg',
                size: \Galette\Core\Picture::MAX_FILE_SIZE * 1024 * 100,
                error: UPLOAD_ERR_OK
            );
            //Will fail on filesize, but this is OK, filenames and extensions have been checked :)
            $this->assertSame(\Galette\Core\Picture::FILE_TOO_BIG, $this->picture->storeFile($uploaded_file));
            $this->expectLogEntry(\Analog\Analog::ERROR, 'File is too big ');
        }
    }

    /**
     * Test error messages
     */
    public function testErrorMessages(): void
    {
        $this->assertSame(
            'File name is invalid, it should not contain any special character or space.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_FILENAME)
        );
        $this->assertSame(
            'File extension is not allowed, only jpeg, jpg, png, gif, webp files are.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_EXTENSION)
        );
        $this->assertSame(
            'File is too big. Maximum allowed size is 2 Mo',
            $this->picture->getErrorMessage(\Galette\Core\Picture::FILE_TOO_BIG)
        );
        $this->assertSame(
            'Mime-Type not allowed',
            $this->picture->getErrorMessage(\Galette\Core\Picture::MIME_NOT_ALLOWED)
        );
        $this->assertSame(
            'File does not comply with requirements.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_FILE)
        );
        $this->assertSame(
            'Unable to write file or temporary file',
            $this->picture->getErrorMessage(\Galette\Core\Picture::CANT_WRITE)
        );
        $this->assertSame(
            'An SQL error has occurred.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::SQL_ERROR)
        );
        $this->assertSame(
            'An SQL error has occurred.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::SQL_BLOB_ERROR)
        );
    }
}
