<?php
/**
 * XHProf Profiling for Galette
 *
 * PHP VERSION 5
 *
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 *
 * @category Libraries
 * @package  XHProf
 * @author   Johan Cwiklinski <johan@x-tnd.be>
 * @license  MIT http://en.wikipedia.org/wiki/MIT_License
 * @version  $SVN:Id: xhprof.class.php 18564 2012-05-29 15:21:28Z moyo $
 * @link     http://codefury.net - http://galette.tuxfamily.org
 * @since    0.7.2dev 2012-1008
 */

namespace Galette\Common;

use Analog\Analog as Analog;

/**
 * class XHProf
 *
 * Il you need to "profile" some part of code
 *
 * Install the pecl/xhprof extension
 *
 * Add XHPROF_PATH and XHPROF_URL in config/config_path.php (if needed)
 *
 * Before the code
 *    $prof = new XHProf("something useful");
 *
 * If the code contains an exit() or a redirect() you must also call (before)
 *    unset($prof);
 *
 * php-errors.log will give you the URL of the result.
 *
 * Class documentation
 *
 * @category Libraries
 * @package  XHProf
 * @author   Kenny Katzgrau <katzgrau@gmail.com>
 * @author   Johan Cwiklinski <johan@x-tnd.be>
 * @license  MIT http://en.wikipedia.org/wiki/MIT_License
 * @version  Release: 0.1
 * @link     http://codefury.net - http://galette.tuxfamily.org
 * @since    0.7.2dev 2012-10-08
 */
class XHProf
{

    // this can be overloaded in config/config_path.php
    const XHPROF_PATH = '/usr/share/xhprof/xhprof_lib';
    const XHPROF_URL  = '/xhprof';

    static private $_run = false;

    /**
     * Default constructor
     *
     * @param string $msg Message(default '')
     */
    function __construct($msg='')
    {
        $this->start($msg);
    }


    /**
     * Destrcut the object
     */
    function __destruct()
    {
        $this->stop();
    }


    /**
     * Start profiling
     *
     * @param string $msg Message (default '')
     *
     * @return void
     */
    public function start($msg='')
    {
        if (!self::$_run
            && function_exists('xhprof_enable')
        ) {
            xhprof_enable(
                XHPROF_FLAGS_NO_BUILTINS
                | XHPROF_FLAGS_CPU
                | XHPROF_FLAGS_MEMORY
            );
            Analog::log(
                'Start profiling with XHProf ' . $msg,
                Analog::INFO
            );
            self::$_run = true;
        }
    }

    /**
     * Stops profiling
     *
     * @return void
     */
    public function stop()
    {
        if (self::$_run) {
            $data = xhprof_disable();

            $incl = (defined('XHPROF_PATH') ? XHPROF_PATH : self::XHPROF_PATH);
            include_once $incl.'/utils/xhprof_lib.php';
            include_once $incl.'/utils/xhprof_runs.php';

            $runs = new \XHProfRuns_Default();
            $id   = $runs->save_run($data, 'galette-' . GALETTE_VERSION);

            $url  = (defined('XHPROF_URL') ? XHPROF_URL : self::XHPROF_URL);
            $host = (defined('XHPROF_HOST') ? XHPROF_HOST : $_SERVER['HTTP_HOST']);
            $link = 'http://' . $host .$url . '/index.php?run=' .
                $id . '&source=galette-' . GALETTE_VERSION;
            Analog::log(
                'Stop profiling with XHProf, result URL: ' . $link,
                Analog::INFO
            );

            self::$_run = false;
        }
    }
}
