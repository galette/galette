<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Logo tests
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
 * @since     2017-07-08
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Picture tests class
 *
 * @category  Core
 * @name      Logo
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-07-08
 */
class Logo extends atoum
{
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     *
     * @param string $method Method name
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        global $zdb;
        $this->zdb = new \Galette\Core\Db();
        $zdb = $this->zdb;
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
        global $zdb;
        $zdb = $this->zdb;
        $expected_path = realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette.png');

        $this->given($this->newTestedInstance)
            ->then
                ->variable($this->testedInstance->getDestDir())->isNull()
                ->variable($this->testedInstance->getFileName())->isNull()
                ->string($this->testedInstance->getPath())->isIdenticalTo($expected_path)
                ->string($this->testedInstance->getMime())->isIdenticalTo('image/png')
                ->string($this->testedInstance->getFormat())->isIdenticalTo('png')
                ->boolean($this->testedInstance->isCustom())->isFalse()
                ->integer($this->testedInstance->getOptimalWidth())->isIdenticalTo(129)
                ->integer($this->testedInstance->getOptimalHeight())->isIdenticalTo(60)
                ->integer($this->testedInstance->getWidth())->isIdenticalTo(129)
                ->integer($this->testedInstance->getHeight())->isIdenticalTo(60);
    }
}
