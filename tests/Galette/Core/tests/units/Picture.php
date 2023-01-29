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

use atoum;

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
class Picture extends atoum
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
     * @param string $method Method name
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->picture = new \Galette\Core\Picture();
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
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
        $this->variable($picture->getDestDir())->isNull();
        $this->variable($picture->getFileName())->isNull();

        $expected_exts = ['jpeg', 'jpg', 'png', 'gif'];
        $this->string($picture->getAllowedExts())->isIdenticalTo(implode(', ', $expected_exts));

        $expected_mimes = [
            'jpg'    =>    'image/jpeg',
            'png'    =>    'image/png',
            'gif'    =>    'image/gif'
        ];
        $this->array($picture->getAllowedMimeTypes())->isIdenticalTo($expected_mimes);

        $this->string($this->picture->getBadChars())
            ->isIdenticalTo('`' . implode('`, `', $this->expected_badchars) . '`');
    }

    /**
     * Test setters
     *
     * @return void
     */
    public function testSetters()
    {
        $this->variable($this->picture->getDestDir())->isNull();
        $this->variable($this->picture->getFileName())->isNull();

        $this->picture->setDestDir(__DIR__);
        $this->string($this->picture->getDestDir())->isIdenticalTo(__DIR__);

        $this->picture->setFileName('myfile.png');
        $this->string($this->picture->getFileName())->isIdenticalTo('myfile.png');
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
        $this->variable($url)->isNotFalse();
        $this->variable($this->picture->getMimeType($url))->isIdenticalTo('image/jpeg');

        $url = realpath(GALETTE_ROOT . '../galette/webroot/themes/default/images/galette.png');
        $this->variable($url)->isNotFalse();
        $this->variable($this->picture->getMimeType($url))->isIdenticalTo('image/png');

        $url = realpath(GALETTE_ROOT . '../tests/test.gif');
        $this->variable($url)->isNotFalse();
        $this->variable($this->picture->getMimeType($url))->isIdenticalTo('image/gif');

        $this->variable($this->picture->getMimeType(__DIR__ . '/Picture.php'))->isIdenticalTo('text/x-php');
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
        $this->variable($url)->isNotFalse();

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
            $this->integer($this->picture->store($file))->isIdenticalTo($expected);
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
            $this->integer($this->picture->store($file))->isIdenticalTo(\Galette\Core\Picture::FILE_TOO_BIG);
        }
    }

    /**
     * Test error messages
     *
     * @return void
     */
    public function testErrorMessages()
    {
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_FILENAME))
            ->isIdenticalTo('File name is invalid, it should not contain any special character or space.');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_EXTENSION))
            ->isIdenticalTo('File extension is not allowed, only jpeg, jpg, png, gif files are.');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::FILE_TOO_BIG))
            ->isIdenticalTo('File is too big. Maximum allowed size is 1024Ko');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::MIME_NOT_ALLOWED))
            ->isIdenticalTo('Mime-Type not allowed');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::INVALID_FILE))
            ->isIdenticalTo('File does not comply with requirements.');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::CANT_WRITE))
            ->isIdenticalTo('Unable to write file or temporary file');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::SQL_ERROR))
            ->isIdenticalTo('An SQL error has occurred.');
        $this->string($this->picture->getErrorMessage(\Galette\Core\Picture::SQL_BLOB_ERROR))
            ->isIdenticalTo('An SQL error has occurred.');
    }
}
