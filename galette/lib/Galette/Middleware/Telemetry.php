<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim telemetry middleware
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Analog\Analog;

/**
 * Galette Slim telemetry middleware
 *
 * @category  Middleware
 * @name      Telemetry
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class Telemetry
{
    private $zdb;
    private $preferences;
    private $plugins;

    /**
     * Constructor
     *
     * @param Slim\Container $container Container instance
     */
    public function __construct(\Slim\Container $container)
    {
        $this->zdb = $container->get('zdb');
        $this->preferences = $container->get('preferences');
        $this->plugins = $container->get('plugins');
    }

    /**
     * Middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        if ($telemetry->isSent()) {
            try {
                $dformat = 'Y-m-d H:i:s';
                $mdate = \DateTime::createFromFormat(
                    $dformat,
                    $telemetry->getSentDate()
                );
                $expire = $mdate->add(
                    new \DateInterval('P7D')
                );
                $now = new \DateTime();
                $has_expired = $now > $expire;

                if ($has_expired) {
                    $cfile = GALETTE_CACHE_DIR . 'telemetry.cache';
                    if (file_exists($cfile)) {
                        $mdate = \DateTime::createFromFormat(
                            $dformat,
                            date(
                                $dformat,
                                filemtime($cfile)
                            )
                        );
                        $expire = $mdate->add(
                            new \DateInterval('P7D')
                        );
                        $now = new \DateTime();
                        $has_expired = $now > $expire;
                    }

                    if ($has_expired) {
                        //create/update cache file
                        $stream = fopen($cfile, 'w+');
                        fwrite(
                            $stream,
                            $telemetry->getSentDate()
                        );
                        fclose($stream);

                        //send telemetry data
                        try {
                            $result = $telemetry->send();
                        } catch (\Exception $e) {
                            Analog::log(
                                $e->getMessage(),
                                Analog::INFO
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                //empty catch
            }
        }
        return $next($request, $response);
    }
}
