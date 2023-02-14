<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Required modules checking
 *
 * PHP version 5
 *
 * Copyright © 2007-2023 The Galette Team
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
 * @copyright 2012-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
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
 * @copyright 2012-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-03-12
 */
class CheckModules
{
    private $good = array();
    private $should = array();
    private $missing = array();

    private $modules = [
        //name      => required
        'SimpleXML' => true,
        'gd'        => true,
        'pdo'       => true,
        'curl'      => false,
        'gettext'   => false,
        'mbstring'  => true,
        'openssl'   => false,
        'intl'      => true,
        'session'   => true
    ];


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
     * @param boolean $translated Use translations (default to true)
     *
     * @return void
     */
    public function doCheck($translated = true)
    {
        $string = ($translated ? _T("'%s' module") : "'%s' module");
        foreach ($this->modules as $name => $required) {
            if ($name == 'pdo') {
                //one of mysql or pgsql driver must be present
                $mstring = "either 'mysql' or 'pgsql' PDO driver";
                if ($translated) {
                    $mstring = _T("either 'mysql' or 'pgsql' PDO driver");
                }
                if (
                    !extension_loaded('pdo_mysql')
                    && !extension_loaded('pdo_pgsql')
                ) {
                    $this->missing[] = $mstring;
                } else {
                    $this->good[$name] = $mstring;
                }
            } else {
                $mstring = str_replace('%s', $name, $string);
                if (!extension_loaded($name)) {
                    if ($required) {
                        $this->missing[] = $mstring;
                    } else {
                        $this->should[] = $mstring;
                    }
                } else {
                    $this->good[$name] = str_replace('%s', $name, $string);
                }
            }
        }
    }

    /**
     * HTML formatted results for checks
     *
     * @param boolean $translated Use translations (default to true)
     *
     * @return string
     */
    public function toHtml($translated = true)
    {
        $html = null;

        if (count($this->missing) > 0) {
            $ko = ($translated ? _T('Ko') : 'Ko');
            foreach ($this->missing as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui red times icon"></i><span class="displaynone">' .
                    $ko . '</span></span></li>';
            }
        }

        if (count($this->good) > 0) {
            $ok = ($translated ? _T('Ok') : 'Ok');
            foreach ($this->good as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui green check icon"></i><span class="displaynone">' .
                    $ok . '</span></span></li>';
            }
        }

        if (count($this->should) > 0) {
            foreach ($this->should as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui yellow exclamation circle icon"></i></span></li>';
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
