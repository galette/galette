<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Error handler
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
 * @since     Available since 0.7.1dev - 2012-04-28
 */

namespace Galette\Core;

use Analog\Analog as Analog;

/**
 * Error handler
 *
 * @category  Core
 * @name      Error
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
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
        $str = 'PHP %type: %str in %file on line %line';
        $patterns = array('%type', '%str', '%file', '%line');

        switch ($errno) {
        case E_STRICT:
            Analog::log(
                str_replace(
                    $patterns,
                    array('Strict standards', $errstr, $errfile, $errline),
                    $str
                ),
                Analog::NOTICE
            );
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            Analog::log(
                str_replace(
                    $patterns,
                    array('Deprecated', $errstr, $errfile, $errline),
                    $str
                ),
                Analog::NOTICE
            );
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            //do not log smarty's annonying 'undefined index' notices
            if ( !preg_match('/^Undefined index/', $errstr)
                && !preg_match('/\.tpl\.php$/', $errfile)
            ) {
                Analog::log(
                    str_replace(
                        $patterns,
                        array('Notice', $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::NOTICE
                );
            }
            break;
        case E_WARNING:
        case E_USER_WARNING:
            Analog::log(
                str_replace(
                    $patterns,
                    array('Warning', $errstr, $errfile, $errline),
                    $str
                ),
                Analog::WARNING
            );
            break;
        case E_ERROR:
        case E_USER_ERROR:
            Analog::log(
                str_replace(
                    $patterns,
                    array('Fatal', $errstr, $errfile, $errline),
                    $str
                ),
                Analog::ERROR
            );
            throw new ErrorException(
                'Fatal error: ' . $errstr,
                $errno,
                $errfile,
                $errline
            );

            exit("FATAL error $errstr at $errfile:$errline");
        default:
            Analog::log(
                str_replace(
                    $patterns,
                    array('Unknown', $errstr, $errfile, $errline),
                    $str
                ),
                Analog::ERROR
            );
            throw new ErrorException(
                'Unknown error: ' . $errstr,
                $errno,
                $errfile,
                $errline
            );
        }
    }
}
