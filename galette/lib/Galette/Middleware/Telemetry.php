<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Core\Db;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Throwable;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Analog\Analog;
use DI\Container;

/**
 * Galette Slim telemetry middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Telemetry
{
    private readonly Db $zdb;
    private readonly Preferences $preferences;
    private readonly Plugins $plugins;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->zdb = $container->get('zdb');
        $this->preferences = $container->get('preferences');
        $this->plugins = $container->get('plugins');
    }

    /**
     * Middleware invokable class
     *
     * @param  Request        $request PSR7 request
     * @param  RequestHandler $handler Request response
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

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
                            $telemetry->send();
                        } catch (Throwable $e) {
                            Analog::log(
                                $e->getMessage(),
                                Analog::INFO
                            );
                        }
                    }
                }
            } catch (Throwable) {
                //empty catch
            }
        }
        return $response;
    }
}
