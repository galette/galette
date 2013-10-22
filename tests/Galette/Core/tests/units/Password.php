<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n tests
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-22
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Password tests class
 *
 * @category  Core
 * @name      Password
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class Password extends atoum
{
    private $_pass = null;

    /**
     * Set up tests
     *
     * @param string $testMethod Method name
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->_pass = new \Galette\Core\Password(false);
    }

    /**
     * Test unique password generator
     *
     * @return void
     */
    public function testRandom()
    {
        $results = array();

        for ( $i = 0 ; $i < 200 ; $i++ ) {
            $random = $this->_pass->makeRandomPassword(15);
            $this->string($random)->hasLength(15);

            $exists = in_array($random, $results);
            $this->boolean($exists)->isFalse();

            $results[] = $random;
            $this->array($results)->hasSize($i + 1);
        }
    }
}
