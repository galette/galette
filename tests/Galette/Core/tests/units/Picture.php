<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Picture tests
 *
 * PHP version 5
 *
 * Copyright © 2017-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  IO
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2017-05-14
 */

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Picture tests class
 *
 * @category  Core
 * @name      Picture
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-05-14
 */
class Picture extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\Core\Picture $picture;
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
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->picture = new \Galette\Core\Picture();
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
    }

    /**
     * Test defaults after initialization
     *
     * @return void
     */
    public function testDefaults()
    {
        $picture = new \Galette\Core\Picture();
        $this->assertNull($picture->getDestDir());
        $this->assertNull($picture->getFileName());

        $expected_exts = ['jpeg', 'jpg', 'png', 'gif'];
        $this->assertSame(implode(', ', $expected_exts), $picture->getAllowedExts());

        $expected_mimes = [
            'jpg'    =>    'image/jpeg',
            'png'    =>    'image/png',
            'gif'    =>    'image/gif'
        ];
        $this->assertSame($expected_mimes, $picture->getAllowedMimeTypes());

        $this->assertSame(
            '`' . implode('`, `', $this->expected_badchars) . '`',
            $this->picture->getBadChars()
        );
    }

    /**
     * Test setters
     *
     * @return void
     */
    public function testSetters()
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
     *
     * @return void
     */
    public function testFileInfoMimeType()
    {
        $url = realpath(GALETTE_ROOT . '../tests/fake_image.jpg');
        $this->assertNotFalse($url);
        $this->assertSame('image/jpeg', $this->picture->getMimeType($url));

        $url = realpath(GALETTE_ROOT . '../galette/webroot/themes/default/images/galette.png');
        $this->assertNotFalse($url);
        $this->assertSame('image/png', $this->picture->getMimeType($url));

        $url = realpath(GALETTE_ROOT . '../tests/test.gif');
        $this->assertNotFalse($url);
        $this->assertSame('image/gif', $this->picture->getMimeType($url));

        $this->assertSame('text/x-php', $this->picture->getMimeType(__DIR__ . '/Picture.php'));
    }

    /**
     * Test mimetype guess
     * FileInfo not installed, back to mime_content_type call
     *
     * Does not actually works :/
     *
     * @return void
     */
    /*public function testMimeContentTypeMimeType()
    {
        $url = realpath(GALETTE_ROOT . '../tests/fake_image.jpg');
        $this->assertNotFalse($url);

        $this->assert('FileInfo extension missing')
            ->given($picture = new \Galette\Core\Picture())
            ->if($this->function->function_exists = false)
            ->then
                ->variable($picture->getMimeType($url))->isIdenticalTo('image/jpeg');
    }*/

    /**
     * Test storage
     *
     * @return void
     */
    public function testStore()
    {
        foreach ($this->expected_badchars as $badchar) {
            $expected = \Galette\Core\Picture::INVALID_FILENAME;
            if ($badchar == '.') {
                //will give an invalid extension
                $expected = \Galette\Core\Picture::INVALID_EXTENSION;
            }
            $file = [
                'name'      => 'file-with-' . $badchar . '-char.jpg',
                'tmp_name'  => 'none'
            ];
            $this->assertSame($expected, $this->picture->store($file));
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
            $file = [
                'name'      => $file,
                'tmp_name'  => 'none',
                'size'      => \Galette\Core\Picture::MAX_FILE_SIZE * 1024 * 100
            ];
            //Will fail on filesize, but this is OK, filenames and extensions have been checked :)
            $this->assertSame(\Galette\Core\Picture::FILE_TOO_BIG, $this->picture->store($file));
        }
    }

    /**
     * Test error messages
     *
     * @return void
     */
    public function testErrorMessages()
    {
        $this->assertSame(
            'File name is invalid, it should not contain any special character or space.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_FILENAME)
        );
        $this->assertSame(
            'File extension is not allowed, only jpeg, jpg, png, gif files are.',
            $this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_EXTENSION)
        );
        $this->assertSame(
            'File is too big. Maximum allowed size is 1024Ko',
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
