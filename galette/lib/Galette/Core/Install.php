<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation
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
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-01-09
 */

namespace Galette\Core;

/**
 * Galette installation
 *
 * @category  Core
 * @name      Install
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-01-09
 */
class Install
{
    const STEP_CHECK = 0;
    const STEP_TYPE = 1;
    const STEP_DB = 2;
    const STEP_DB_CHECKS = 3;
    const STEP_VERSION = 4; //only for update
    const STEP_DB_INSTALL = 5;

    const INSTALL = 'i';
    const UPDATE = 'u';

    private $_step;
    private $_mode;
    private $_version;
    private $_installed_version;

    private $_db_type;
    private $_db_host;
    private $_db_port;
    private $_db_name;
    private $_db_user;
    private $_db_pass;

    private $_db_connected;

    /**
     * Main constructor
     */
    public function __construct()
    {
        $this->_step = self::STEP_CHECK;
        $this->mode = null;
        $this->_version = str_replace('v', '', GALETTE_VERSION);
        $this->_db_connected = false;
    }

    /**
     * Return current step title
     *
     * @return string
     */
    public function getStepTitle()
    {
        $step_title = null;
        switch ( $this->_step ) {
        case self::STEP_CHECK:
            $step_title = _T("Checks");
            break;
        case self::STEP_TYPE:
            $step_title = _T("Installation mode");
            break;
        case self::STEP_DB:
            $step_title = _T("Database");
            break;
        case self::STEP_DB_CHECKS:
            $step_title = _T("Database access and permissions");
            break;
        case self::STEP_VERSION:
            //TODO
            $step_title = 'TODO';
            break;
        case self::STEP_DB_INSTALL:
            $step_title = _T("Tables Creation/Update");
            break;
        }
        return $step_title;
    }

    /**
    * HTML validation image
    *
    * @param boolean $arg Argument
    *
    * @return html string
    */
    public function getValidationImage($arg)
    {
        $img_name = ($arg === true) ? 'valid' : '';
        $src = GALETTE_TPL_SUBDIR . 'images/icon-' . $img_name . '.png';
        $alt = ($arg === true) ? _T("Ok") : _T("Ko");
        $img = '<img src="' . $src  . '" alt="' . $alt  . '"/>';
        return $img;
    }

    /**
     * Get current mode
     *
     * @return char
     */
    public function getMode()
    {
        return $this->_mode;
    }

    /**
     * Are we installing?
     *
     * @return boolean
     */
    public function isInstall()
    {
        return $this->_mode === self::INSTALL;
    }

    /**
     * Are we upgrading?
     *
     * @return boolean
     */
    public function isUpgrade()
    {
        return $this->_mode === self::UPDATE;
    }

    /**
     * Set installation mode
     *
     * @param char $mode Requested mode
     *
     * @return void
     */
    public function setMode($mode)
    {
        if ( $mode === self::INSTALL || $mode === self::UPDATE ) {
            $this->_mode = $mode;
        } else {
            throw new \UnexpectedValueException('Unknown mode "' . $mode . '"');
        }
    }

    /**
     * Go back to previous step
     *
     * @return void
     */
    public function atPreviousStep()
    {
        if ( $this->_step > 0 ) {
            $this->_step = $this->_step -1;
        }
    }

    /**
     * Are we at check step?
     *
     * @return boolean
     */
    public function isCheckStep()
    {
        return $this->_step === self::STEP_CHECK;
    }

    /**
     * Set step to type of installation
     *
     * @return void
     */
    public function atTypeStep()
    {
        $this->_step = self::STEP_TYPE;
    }

    /**
     * Are we at type step?
     *
     * @return boolean
     */
    public function isTypeStep()
    {
        return $this->_step === self::STEP_TYPE;
    }

    /**
     * Set step to database informations
     *
     * @return void
     */
    public function atDbStep()
    {
        $this->_step = self::STEP_DB;
    }

    /**
     * Are we at database step?
     *
     * @return boolean
     */
    public function isDbStep()
    {
        return $this->_step === self::STEP_DB;
    }

    /**
     * Is DB step passed?
     *
     * @return boolean
     */
    public function postCheckDb()
    {
        return $this->_step > self::STEP_DB_CHECKS;
    }

    /**
     * Set database type
     *
     * @param string $type  Database type
     * @param array  &$errs Errors array
     *
     * @return boolean
     */
    public function setDbType($type, &$errs)
    {
        switch ( $type ) {
        case Db::MYSQL:
        case Db::PGSQL:
        case Db::SQLITE:
            $this->_db_type = $type;
            break;
        default:
            $errs[] = _T("Database type unknown");
        }
    }

    /**
     * Get database type
     *
     * @return string
     */
    public function getDbType()
    {
        return $this->_db_type;
    }

    /**
     * Set connection informations
     *
     * @param string $host Database host
     * @param string $port Database port
     * @param string $name Database name
     * @param string $user Database user name
     * @param string $pass Database user's password
     *
     * @return void
     */
    public function setDsn($host, $port, $name, $user, $pass)
    {
        $this->_db_host = $host;
        $this->_db_port = $port;
        $this->_db_name = $name;
        $this->_db_user = $user;
        $this->_db_pass = $pass;
    }

    /**
     * Set tables prefix
     *
     * @param string $prefix Prefix
     *
     * @return void
     */
    public function setTablesPrefix($prefix)
    {
        $this->_db_prefix = $prefix;
    }

    /**
     * Retrieve database host
     *
     * @return string
     */
    public function getDbHost()
    {
        return $this->_db_host;
    }

    /**
     * Retrieve database port
     *
     * @return string
     */
    public function getDbPort()
    {
        return $this->_db_port;
    }

    /**
     * Retrieve database name
     *
     * @return string
     */
    public function getDbName()
    {
        return $this->_db_name;
    }

    /**
     * Retrieve database user
     *
     * @return string
     */
    public function getDbUser()
    {
        return $this->_db_user;
    }

    /**
     * Retrieve database password
     *
     * @return string
     */
    public function getDbPass()
    {
        return $this->_db_pass;
    }

    /**
     * Retrieve tables prefix
     *
     * @return string
     */
    public function getTablesPrefix()
    {
        return $this->_db_prefix;
    }

    /**
     * Set step to database checks
     *
     * @return void
     */
    public function atDbCheckStep()
    {
        $this->_step = self::STEP_DB_CHECKS;
    }

    /**
     * Are we at database check step?
     *
     * @return boolean
     */
    public function isDbCheckStep()
    {
        return $this->_step === self::STEP_DB_CHECKS;
    }

    /**
     * Test database connection
     *
     * @return true|array true if connection was successfull, an array with some infos otherwise
     */
    public function testDbConnexion()
    {
        if ( $this->_db_type === Db::SQLITE ) {
            return Db::testConnectivity(
                $this->_db_type
            );
        } else {
            return Db::testConnectivity(
                $this->_db_type,
                $this->_db_user,
                $this->_db_pass,
                $this->_db_host,
                $this->_db_port,
                $this->_db_name
            );
        }
    }

    /**
     * Is database connexion ok?
     *
     * @return boolean
     */
    public function isDbConnected()
    {
        return $this->_db_connected;
    }

    /**
     * Set step to version selection
     *
     * @return void
     */
    public function atVersionSelection()
    {
        $this->_step = self::STEP_VERSION;
    }

    /**
     * Are we at version selection step?
     *
     * @return boolean
     */
    public function isVersionSelectionStep()
    {
        return $this->_step === self::STEP_VERSION;
    }

    /**
     * Set step to database installation
     *
     * @return void
     */
    public function atDbInstallStep()
    {
        $this->_step = self::STEP_DB_INSTALL;
    }

    /**
     * Are we at db installation step?
     *
     * @return boolean
     */
    public function isDbinstallStep()
    {
        return $this->_step === self::STEP_DB_INSTALL;
    }

}
