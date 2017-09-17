<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Error handler
 *
 * PHP version 5
 *
 * Copyright © 2012-2014 The Galette Team
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
 * @since     Available since 0.7.1dev - 2012-04-28
 */

namespace Galette\Core;

use Analog\Analog;

/**
 * Error handler
 *
 * @category  Core
 * @name      Error
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2014 The Galette Team
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
        $throw = false;

        switch ($errno) {
            case E_STRICT:
                $type = 'Strict standards';
                Analog::log(
                    str_replace(
                        $patterns,
                        array($type, $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::NOTICE
                );
                break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $type = 'Deprecated';
                Analog::log(
                    str_replace(
                        $patterns,
                        array($type, $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::NOTICE
                );
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                //do not log smarty's annonying 'undefined index' notices
                if (!preg_match('/^Undefined index/', $errstr)
                    && !preg_match('/\.tpl\.php$/', $errfile)
                ) {
                    $type = 'Notice';
                    Analog::log(
                        str_replace(
                            $patterns,
                            array($type, $errstr, $errfile, $errline),
                            $str
                        ),
                        Analog::NOTICE
                    );
                } else {
                    $throw = null;
                }
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $type = 'Warning';
                Analog::log(
                    str_replace(
                        $patterns,
                        array($type, $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::WARNING
                );
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $type = 'Fatal';
                Analog::log(
                    str_replace(
                        $patterns,
                        array($type, $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::ERROR
                );
                $throw = true;
                break;
            default:
                $type = 'Unknown';
                Analog::log(
                    str_replace(
                        $patterns,
                        array($type, $errstr, $errfile, $errline),
                        $str
                    ),
                    Analog::ERROR
                );
                $throw = true;
                break;
        }

        if ($throw === true || GALETTE_MODE == 'DEV' && $throw !== null) {
            throw new \ErrorException(
                $type . ': ' . $errstr,
                $errno,
                1,
                $errfile,
                $errline
            );
        }
    }
}
