<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Required modules checking
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-03-12
 */

namespace Galette\Core;

/**
 * Required modules checking
 *
 * @category  Core
 * @name      CheckModules
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-03-12
 */
class CheckModules
{
    private $_good = array();
    private $_may = array();
    private $_should = array();
    private $_missing = array();

    /**
     * Check various modules and dispatch them beetween:
     * - good: module that are present,
     * - may: modules that may be present but are not,
     * - should: modules that should be present but are not,
     * - missing: required modules that are missing
     */
    public function __construct()
    {
        //simplexml module is mandatory
        if ( !extension_loaded('SimpleXML') ) {
            $this->_missing[] = str_replace('%s', 'SimpleXML', _T("'%s' module"));
        } else {
            /*$this->_good['SimpleXML'] = str_replace(
                '%s',
                 'SimpleXML',
                _T("'%s' module")
            );*/
        }

        //gd module is required
        if (!extension_loaded('gd')) {
            $this->_missing[] = str_replace('%s', 'gd', _T("'%s' module"));
        } else {
            $this->_good['gd'] = str_replace('%s', 'gd', _T("'%s' module"));
        }

        //mcrypt module is required
        if (!extension_loaded('mcrypt')) {
            $this->_missing[] = str_replace('%s', 'mcrypt', _T("'%s' module"));
        } else {
            $this->_good['mcrypt'] = str_replace('%s', 'mcrypt', _T("'%s' module"));
        }

        //one of mysql or pgsql driver must be present
        if ( !extension_loaded('pdo_mysql')
            && !extension_loaded('pdo_pgsql')
            && !extension_loaded('pdo_sqlite')
        ) {
            $this->_missing[] = _T("either 'mysql', 'pgsql' or 'sqlite' PDO driver");
        } else {
            $this->_good['pdo_driver'] = _T("either 'mysql', 'pgsql' or 'sqlite' PDO driver");
        }

        //curl module is optionnal
        if ( !extension_loaded('curl') ) {
            $this->_should[] = str_replace('%s', 'curl', _T("'%s' module"));
        } else {
            $this->_good['curl'] = str_replace('%s', 'curl', _T("'%s' module"));
        }

        //tidy module is optionnal
        if ( !extension_loaded('tidy') ) {
            $this->_may[] = str_replace('%s', 'tidy', _T("'%s' module"));
        } else {
            $this->_good['tidy'] = str_replace('%s', 'tidy', _T("'%s' module"));
        }

        //gettext module is optionnal
        if ( !extension_loaded('gettext') ) {
            $this->_may[] = str_replace('%s', 'gettext', _T("'%s' module"));
        } else {
            $this->_good['gettext'] = str_replace(
                '%s',
                'gettext',
                _T("'%s' module")
            );
        }

        if ( !extension_loaded('mbstring') ) {
            $this->_may[] = str_replace('%s', 'mbstring', _T("'%s' module"));
        } else {
            $this->_good['mbstring'] = str_replace(
                '%s',
                'mbstring',
                _T("'%s' module")
            );
        }

        //ssl support is optionnal
        if ( !extension_loaded('openssl') ) {
            $this->_should[] = _T("'openssl' support");
        } else {
            $this->_good['ssl'] = _T("'openssl' support");
        }

        if ( !extension_loaded('fileinfo') ) {
            $this->_missing[] = str_replace('%s', 'fileinfo', _T("'%s' module"));
        } else {
            $this->_good['fileinfo'] = str_replace(
                '%s',
                'fileinfo',
                _T("'%s' module")
            );
        }

    }

    /**
     * HTML formatted results for checks
     *
     * @return string
     */
    public function toHtml()
    {
        $html = '';
        if ( count($this->_missing) > 0 ) {
            $html .= '<h3 >' . _T("Missing required modules")  . '</h3>';
            $html .= '<ul class="list">';
            foreach ( $this->_missing as $m ) {
                $html .= '<li class="install-bad">' . $m  . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h3>' . _T("Active used modules")  . '</h3>';
        if ( count($this->_good) === 0 ) {
            $html .= "<p>" .  _T("Any required module loaded yet!") . "</p>";
        } else {
            $html .= '<ul class="list">';
            foreach ( $this->_good as $m ) {
                $html .= '<li class="install-ok">' . $m  . '</li>';
            }
            $html .= '</ul>';
        }

        if ( count($this->_should) > 0 ) {
            $html .= '<h3 >' . _T("Modules that may be required")  . '</h3>';
            $html .= '<ul class="list">';
            foreach ( $this->_should as $m ) {
                $html .= '<li class="install-may">' . $m  . '</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Check if it is ok to use Galette with current modules
     *
     * @return boolean
     */
    public function isValid()
    {
        return count($this->_missing) === 0;
    }

    /**
     * Check if a specific module is OK for that instance
     *
     * @param string $module Module name to check
     *
     * @return boolean
     */
    public function isGood($module)
    {
        return isset($this->_good[$module]);
    }

    /**
     * Retrieve good modules
     *
     * @return array
     */
    public function getGoods()
    {
        return $this->_good;
    }

    /**
     * Retrieve may modules
     *
     * @return array
     */
    public function getMays()
    {
        return $this->_may;
    }

    /**
     * Retrieve should modules
     *
     * @return array
     */
    public function getShoulds()
    {
        return $this->_should;
    }

    /**
     * Retrieve missing modules
     *
     * @return array
     */
    public function getMissings()
    {
        return $this->_missing;
    }

}
