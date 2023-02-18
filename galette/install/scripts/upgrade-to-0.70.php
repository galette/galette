<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette 0.70 upgrade script
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
 *
 * This file is part of Galette (http://galette.eu).
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
 * @category  Upgrades
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     Available since 0.8 - 2014-01-05
 */

namespace Galette\Updates;

use Galette\Updater\AbstractUpdater;

/**
 * Galette 0.70 upgrade script
 *
 * @category  Upgrades
 * @name      Install
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     Available since 0.8 - 2014-01-04
 */
class UpgradeTo070 extends AbstractUpdater
{
    protected $db_version = '0.700';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts('0.70');
    }

    /**
     * Update instructions
     *
     * @return boolean
     */
    protected function update()
    {
        $this->zdb->convertToUTF($this->installer->getTablesPrefix());
        return true;
    }
}

