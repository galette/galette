<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Error handler
 *
 * PHP version 5
 *
 * Copyright © 2012 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-04-28
 */

namespace Galette\Core;

/**
 * Error handler
 *
 * @category  Main
 * @name      Error
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-04-28
 */
class Error
{

    /**
     * Php error handler for Galette
     *
     * @param int    $errno   Error number
     * @param string $errstr  Error message
     * @param string $errfile File where error appears
     * @param string $errline Line of that file
     *
     * @return void
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        global $log;
        $str = 'PHP %type: %str in %file on line %line';
        $patterns = array('%type', '%str', '%file', '%line');

        switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
        case E_STRICT:
            $log->log(
                str_replace(
                    $patterns,
                    array('Notice', $errstr, $errfile, $errline),
                    $str
                ),
                PEAR_LOG_INFO
            );
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $log->log(
                str_replace(
                    $patterns,
                    array('Warning', $errstr, $errfile, $errline),
                    $str
                ),
                PEAR_LOG_WARNING
            );
            break;
        case E_ERROR:
        case E_USER_ERROR:
            $log->log(
                str_replace(
                    $patterns,
                    array('Fatal', $errstr, $errfile, $errline),
                    $str
                ),
                PEAR_LOG_ERR
            );
            exit("FATAL error $errstr at $errfile:$errline");
        default:
            $log->log(
                str_replace(
                    $patterns,
                    array('Unknown', $errstr, $errfile, $errline),
                    $str
                ),
                PEAR_LOG_ERR
            );
            exit("Unknown error at $errfile:$errline");
        }
    }
}
?>
