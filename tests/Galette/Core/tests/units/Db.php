<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dadatabse tests
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
 * @since     2013-02-05
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Database tests class
 *
 * @category  Core
 * @name      Db
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-02-05
 */
class Db extends atoum
{
    private $_db;

    /**
     * Set up tests
     *
     * @param stgring $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        //$this->_db = new \Galette\Core\Db();
    }


    /**
     * Tests plugins load
     *
     * @return void
     */
    public function testGetUpgradeScripts()
    {
        $update_scripts = \Galette\Core\Db::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.6'
        );

        $knowns = array(
            '0.60' => 'upgrade-to-0.60-pgsql.sql',
            '0.61' => 'upgrade-to-0.61-pgsql.sql',
            '0.62' => 'upgrade-to-0.62-pgsql.sql',
            '0.63' => 'upgrade-to-0.63-pgsql.sql',
            '0.70' => 'upgrade-to-0.70-pgsql.sql',
            '0.71' => 'upgrade-to-0.71-pgsql.sql',
            '0.74' => 'upgrade-to-0.74-pgsql.sql',
            '0.75' => 'upgrade-to-0.75-pgsql.sql',
            '0.76' => 'upgrade-to-0.76-pgsql.sql'
        );

        //as of 0.7.6, we got 9 update scripts total
        $this->array($update_scripts)
            ->hasSize(9)
            ->isIdenticalTo($knowns);

        $update_scripts = \Galette\Core\Db::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.7'
        );

        //if we're from 0.7.0, there are only 5 update scripts left
        $this->array($update_scripts)
            ->hasSize(5);
    }
}
