<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Required modules checking
 *
 * PHP version 5
 *
 * Copyright © 2007-2014 The Galette Team
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
 * @copyright 2012-2014 The Galette Team
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
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-03-12
 */
class CheckModules
{
    private $good = array();
    private $may = array();
    private $should = array();
    private $missing = array();

    /**
     * Constructor
     *
     * @param boolean $do Whether to do checks, defaults to true
     */
    public function __construct($do = true)
    {
        if ($do === true) {
            $this->doCheck();
        }
    }

    /**
     * Check various modules and dispatch them beetween:
     * - good: module that are present,
     * - may: modules that may be present but are not,
     * - should: modules that should be present but are not,
     * - missing: required modules that are missing
     *
     * @return void
     */
    public function doCheck()
    {
        //simplexml module is mandatory
        if (!extension_loaded('SimpleXML')) {
            $this->missing[] = str_replace('%s', 'SimpleXML', _T("'%s' module"));
        } else {
            /*$this->good['SimpleXML'] = str_replace(
                '%s',
                 'SimpleXML',
                _T("'%s' module")
            );*/
        }

        //gd module is required
        if (!extension_loaded('gd')) {
            $this->missing[] = str_replace('%s', 'gd', _T("'%s' module"));
        } else {
            $this->good['gd'] = str_replace('%s', 'gd', _T("'%s' module"));
        }

        //one of mysql or pgsql driver must be present
        if (!extension_loaded('pdo_mysql')
            && !extension_loaded('pdo_pgsql')
        ) {
            $this->missing[] = _T("either 'mysql' or 'pgsql' PDO driver");
        } else {
            $this->good['pdo_driver'] = _T("either 'mysql' or 'pgsql' PDO driver");
        }

        //curl module is optionnal
        if (!extension_loaded('curl')) {
            $this->should[] = str_replace('%s', 'curl', _T("'%s' module"));
        } else {
            $this->good['curl'] = str_replace('%s', 'curl', _T("'%s' module"));
        }

        //tidy module is optionnal
        if (!extension_loaded('tidy')) {
            $this->may[] = str_replace('%s', 'tidy', _T("'%s' module"));
        } else {
            $this->good['tidy'] = str_replace('%s', 'tidy', _T("'%s' module"));
        }

        //gettext module is optionnal
        if (!extension_loaded('gettext')) {
            $this->may[] = str_replace('%s', 'gettext', _T("'%s' module"));
        } else {
            $this->good['gettext'] = str_replace(
                '%s',
                'gettext',
                _T("'%s' module")
            );
        }

        if (!extension_loaded('mbstring')) {
            $this->missing[] = str_replace('%s', 'mbstring', _T("'%s' module"));
        } else {
            $this->good['mbstring'] = str_replace(
                '%s',
                'mbstring',
                _T("'%s' module")
            );
        }

        //ssl support is optionnal
        if (!extension_loaded('openssl')) {
            $this->should[] = _T("'openssl' support");
        } else {
            $this->good['ssl'] = _T("'openssl' support");
        }

        if (!extension_loaded('fileinfo')) {
            $this->missing[] = str_replace('%s', 'fileinfo', _T("'%s' module"));
        } else {
            $this->good['fileinfo'] = str_replace(
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
        $html = null;
        $img_dir = null;
        if (defined('GALETTE_THEME_DIR')) {
            $img_dir = GALETTE_THEME_DIR . 'images/';
        } else {
            $img_dir = GALETTE_TPL_SUBDIR . 'images/';
        }

        if (count($this->missing) > 0) {
            foreach ($this->missing as $m) {
                $html .= '<li><span>' . $m  . '</span><span><img src="' .
                    $img_dir  . 'icon-invalid.png" alt="' .
                    _T("Ko") . '"/></span></li>';
            }
        }

        if (count($this->good) > 0) {
            foreach ($this->good as $m) {
                $html .= '<li><span>' . $m  . '</span><span><img src="' .
                    $img_dir  . 'icon-valid.png" alt="' .
                    _T("Ok") . '"/></span></li>';
            }
        }

        if (count($this->should) > 0) {
            foreach ($this->should as $m) {
                $html .= '<li><span>' . $m  . '</span><span><img src="' .
                    $img_dir  . 'icon-warning.png" alt=""' .
                    '/></span></li>';
            }
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
        return count($this->missing) === 0;
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
        return isset($this->good[$module]);
    }

    /**
     * Retrieve good modules
     *
     * @return array
     */
    public function getGoods()
    {
        return $this->good;
    }

    /**
     * Retrieve may modules
     *
     * @return array
     */
    public function getMays()
    {
        return $this->may;
    }

    /**
     * Retrieve should modules
     *
     * @return array
     */
    public function getShoulds()
    {
        return $this->should;
    }

    /**
     * Retrieve missing modules
     *
     * @return array
     */
    public function getMissings()
    {
        return $this->missing;
    }
}
