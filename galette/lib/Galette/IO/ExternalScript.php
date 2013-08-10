<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * External script call
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
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-06-11
 */

namespace Galette\IO;

use Analog\Analog as Analog;

/**
 * External script call
 *
 * @category  IO
 * @name      ExternalScript
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2010-06-11
 */
class ExternalScript
{
    private $_protocol;
    private $_method;
    private $_uri;
    private $_as_json = true; //TODO: parametize?
    private $_output;

    /**
     * Main constructor
     *
     * @param Preferences $pref Galette preferences
     */
    public function __construct($pref)
    {
        $uri = $pref->pref_new_contrib_script;
        list($protocol,) = explode('://', $uri);

        if ( $protocol == $uri ) {
            Analog::log(
                'An URI must be specified',
                Analog::ERROR
            );
        }

        switch ( $protocol ) {
        case 'galette':
            $this->_protocol = 'http';
            if ( isset($_SERVER['HTTPS']) ) {
                $this->_protocol = 'https';
            }
            $this->_method = 'galette';
            $selfs = explode('/', $_SERVER['PHP_SELF']);
            array_pop($selfs);
            $self = implode('/', $selfs);
            $uri = $protocol . '://' . $_SERVER['SERVER_NAME'] . $self .
                '/' . GALETTE_BASE_PATH . str_replace($protocol . '://', '', $uri);
            break;
        case 'file':
            $this->_protocol = $protocol;
            $this->_method = $protocol;
            break;
        case 'get':
        case 'post':
            $this->_method = $protocol;
            $this->_protocol = 'http';

            break;
        case 'gets':
        case 'posts':
            $this->_method = trim($protocol, 's');
            $this->_protocol = 'https';
            break;
        default:
            throw new \RuntimeException('Unknown protocol.');
            break;
        }

        Analog::log(
            __CLASS__ . ' instancied with method ' . $this->_method .
            ' and protocol ' . $this->_protocol,
            Analog::INFO
        );

        if ( $protocol !== 'file' ) {
            $this->_uri = str_replace(
                $protocol . '://',
                $this->_protocol . '://',
                $uri
            );
        } else {
            if ( file_exists($uri) ) {
                $this->_uri = str_replace(
                    $protocol . '://',
                    '',
                    $uri
                );
            } else {
                throw new \RuntimeException(
                    __METHOD__ . 'File ' . $uri . ' does not exits!'
                );
            }
        }

        Analog::log(
            __CLASS__ . ' URI set to ' . $this->_uri,
            Analog::INFO
        );
    }

    /**
     * Send data
     *
     * @param array $params Array of params to send
     *
     * @return boolean
     */
    public function send($params)
    {
        if ( !is_array($params) || count($params) == 0 ) {
            throw new \RuntimeException(__METHOD__ . ': parameters are mandatory.');
        }

        $uri = $this->_uri;
        $result = null;

        switch ( $this->_method ) {
        case 'get':
            $ch = curl_init();
            if ( $this->_as_json === true ) {
                $uri .= '?params=' . json_encode($params);
            } else {
                $url_params = http_build_query($params, 'galette_');
                $uri .= '?' . $url_params;
            }
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $this->_output = curl_exec($ch);
            if ( $this->_output !== false ) {
                $result = true;
            } else {
                $result = false;
            }
            curl_close($ch);
            break;
        case 'galette':
        case 'post':
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_uri);
            curl_setopt($ch, CURLOPT_POST, count($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if ( $this->_as_json === true ) {
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    array(
                        'params' => json_encode($params)
                    )
                );
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
            $this->_output = curl_exec($ch);
            if ( $this->_output !== false ) {
                $result = true;
            } else {
                $result = false;
            }
            curl_close($ch);
            break;
        case 'file':
            $this->_output = '';
            if ( $this->_as_json === true ) {
                $params = json_encode($params);
            } else {
                $imploded = '';
                foreach ( $params as $k=>$v ) {
                    $imploded .= ' ' . $k . '=' . $v;
                }
                $params = $imploded;
            }

            $descriptors = array(
                0   => array('pipe', 'r'),
                1   => array('pipe', 'w'),
                2   => array('pipe', 'w')
            );

            $process = proc_open(
                $uri,
                $descriptors,
                $pipes
            );
            fwrite($pipes[0], $params);
            fclose($pipes[0]);

            //get stdout, if any
            $output = stream_get_contents($pipes[1]);
            if ( trim($output) !== '' ) {
                $this->_output .= "\n\nStdOut:\n" . $output;
            }

            //get stderr, if any
            $errors = stream_get_contents($pipes[2]);
            if ( trim($errors) !== '' ) {
                $this->_output .="\n\nStdErr:\n" . $errors;
            }

            //closes pipes and process
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);

            if ( trim($this->_output) === '' ) {
                $result = true;
            } else {
                $result = false;
            }
            break;
        default:
            throw new \RuntimeException(
                __METHOD__ . ': unknown method ' . $this->_method
            );
        }

        Analog::log(
            __METHOD__ . ' result: ' . $result . "output:\n" . $this->_output,
            Analog::DEBUG
        );

        return $result;
    }

    /**
     * Get full output (only relevant is method is file)
     *
     * @return array
     */
    public function getOutput()
    {
        return $this->_output;
    }
}
