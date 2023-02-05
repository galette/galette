<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette plugin installation
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2017-01-08
 */

namespace Galette\Core;

use Analog\Analog;
use Laminas\Db\Adapter\Adapter;

/**
 * Galette plugin installation
 *
 * @category  Core
 * @name      Install
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2017-01-08
 */
class PluginInstall extends Install
{
    private $versions_mapper = [];

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->atTypeStep();
    }

    /**
     * Test database connection
     *
     * @return true|array true if connection was successfull,
     * an array with some infos otherwise
     */
    public function testDbConnexion()
    {
        //installing plugin, DB connection is already ok
        return true;
    }

    /**
     * Go back to previous step
     *
     * @return void
     */
    public function atPreviousStep()
    {
        if ($this->_step > 0) {
            if (
                $this->_step - 1 !== self::STEP_DB_INSTALL
                && $this->_step !== self::STEP_END
            ) {
                if ($this->_step === self::STEP_DB_INSTALL) {
                    $this->_step = self::STEP_DB_CHECKS;
                } else {
                    if ($this->_step === self::STEP_DB_UPGRADE) {
                        $this->setInstalledVersion(null);
                    }
                    $this->_step = $this->_step - 1;
                }
            } else {
                $msg = null;
                if ($this->_step === self::STEP_END) {
                    $msg = 'Ok man, install is finished already!';
                } else {
                    $msg = 'It is forbidden to rerun database install!';
                }
                Analog::log($msg, Analog::WARNING);
            }
        }
    }

    /**
     * Initialize Galette relevant objects
     *
     * @param I18n  $i18n  I18n
     * @param Db    $zdb   Database instance
     * @param Login $login Loged in instance
     *
     * @return boolean
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login)
    {
        return false;
    }
}
