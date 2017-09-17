<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PasswordImage tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-22
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * PasswordImage tests class
 *
 * @category  Core
 * @name      PasswordImage
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class PasswordImage extends atoum
{
    private $pass = null;

    /**
     * Set up tests
     *
     * @param string $testMethod Method name
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->pass = new \Galette\Core\PasswordImage(false);
    }

    /**
     * Test new PasswordImage generation
     *
     * @return void
     */
    public function testGenerateNewPassword()
    {
        $pass = $this->pass;
        $res = $pass->generateNewPassword();
        $this->boolean($res)->isTrue();
        $new_pass = $pass->getNewPassword();
        $this->string($new_pass)
            ->hasLength($pass::DEFAULT_SIZE);
        $hash = $pass->getHash();
        $this->string($hash)->hasLength(60);

        $this->string($pass->getImageName())
            ->isIdenticalTo('pw_' . md5($hash) . '.png');
    }

    /**
     * Test new PasswordImage image generation
     *
     * @return void
     */
    public function testNewImage()
    {
        $pass = new \mock\Galette\Core\PasswordImage(false);
        $password = 'azerty12';
        $this->calling($pass)->makeRandomPassword = $password;

        $pass->newImage();

        $pw_checked = password_verify('azerty12', $pass->getHash());
        $this->boolean($pw_checked)->isTrue();

        $this->string($pass->getImageName())
            ->isIdenticalTo('pw_' . md5($pass->getHash()) . '.png');

        $exists = is_file(
            GALETTE_TEMPIMAGES_PATH . '/' . $pass->getImageName()
        );
        $this->boolean($exists)->isTrue();
    }

    /**
     * Test getImage
     *
     * @return void
     */
    public function testGetImage()
    {
        $this->assert('Image generated without exif')
            ->given($pass = new \Galette\Core\PasswordImage(false))
            ->if($this->function->function_exists = false)
            ->then
                ->string($pass->newImage())
                    ->hasLength(60)
                ->string($pass->getImage())
                    ->hasLengthGreaterThan(200)
                    ->startWith('data:image/png;base64,');

        if (function_exists('exif_imagetype')) {
            $this->assert('Image generated without exif')
                ->given($pass = new \Galette\Core\PasswordImage(false))
                ->if($this->function->function_exists = true)
                ->then
                    ->string($pass->newImage())
                        ->hasLength(60)
                    ->string($pass->getImage())
                        ->hasLengthGreaterThan(200)
                        ->startWith('data:image/png;base64,');
        }
    }

    /**
     * Test cleanExpired
     *
     * @return void
     */
    public function testCleanExpired()
    {
        $files = scandir(GALETTE_TEMPIMAGES_PATH);

        if (count($files) == 2) {
            $files = [
                'pw_one.png',
                'pw_two.png',
                'pw_three.png'
            ];
        }

        foreach ($files as $f) {
            if ($f != '.' && $f != '..') {
                touch(GALETTE_TEMPIMAGES_PATH . '/' . $f, time() - 3600);
            }
        }

        $dirfiles = scandir(GALETTE_TEMPIMAGES_PATH);
        $this->integer(count($dirfiles))->isGreaterThan(2);

        //default constructor call clean
        $pass = new \Galette\Core\PasswordImage();
        $dirfiles = scandir(GALETTE_TEMPIMAGES_PATH);
        $this->integer(count($dirfiles))->isEqualTo(2);
    }
}
