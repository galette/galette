<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * System informations
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @since     Available since 0.7.1dev - 2012-06-26
 */

namespace Galette\Core;

/**
 * Grab system informations
 *
 * @category  Core
 * @name      SysInfos
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-03-12
 */
class SysInfos
{
    private $_sysinfos = array();
    private $_php_version = '';
    private $_php_modules = array();
    private $_galette_version = '';
    private $_galette_plugins = array();
    private $_database = '';
    private $_os = '';

    /**
     * Grab various system informations
     *
     * @return void
     */
    public function grab()
    {
        //PHP version
        $this->_php_version = PHP_VERSION;

        //Galette version
        $this->_galette_version = GALETTE_VERSION;

        //Database type
        $this->_database = TYPE_DB;
    }

    /**
     * Get data as RAW (to send by mail)
     *
     * @return string
     */
    public function getRawData()
    {
        global $plugins;

        $str =  'Galette version: ' . $this->_galette_version . "\n";
        $str .= 'PHP version:     ' . $this->_php_version . "\n";
        $str .= 'PHP/Web:         ' . php_sapi_name() . "\n";
        $str .= 'Database:        ' . $this->_database . "\n";
        $str .= 'OS:              ' . php_uname() . "\n";
        $str .= 'Browser:         ' . $_SERVER['HTTP_USER_AGENT'] . "\n\n";

        $str .= 'Modules:' . "\n";
        $mods = new CheckModules();

        $str .= '  OK:' . "\n";
        foreach ( $mods->getGoods() as $g ) {
            $str .= '    ' . stripslashes($g) . "\n";
        }

        $str .= '  May:' . "\n";
        foreach ( $mods->getMays() as $m ) {
            $str .= '    ' . stripslashes($m) . "\n";
        }

        $str .= '  Should:' . "\n";
        foreach ( $mods->getShoulds() as $s ) {
            $str .= '    ' . stripslashes($s) . "\n";
        }

        $str .= '  Missing:' . "\n";
        foreach ( $mods->getMissings() as $m ) {
            $str .= '    ' . stripslashes($m) . "\n";
        }

        $str .= "\n" . 'Plugins:' . "\n";
        foreach ( $plugins->getModules() as $p ) {
            $str .= '  ' . $p['name'] .  ' ' . $p['version'] .
                ' (' . $p['author'] . ")\n";
        }

        $str .= "\n" . 'PHP loaded modules:' . "\n";
        foreach ( get_loaded_extensions() as $e ) {
            $str .= '  ' . $e . "\n";
        }

        return $str;
    }
}
