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

    const INSTALL = 'i';
    const UPDATE = 'u';

    private $_step;
    private $_mode;
    private $_version;
    private $_installed_version;

    /**
     * Main constructor
     */
    public function __construct()
    {
        $this->_step = self::STEP_CHECK;
        $this->mode = null;
        $this->_version = str_replace('v', '', GALETTE_VERSION);
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
        }
        return $step_title;
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
}
