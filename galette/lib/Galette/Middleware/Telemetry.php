<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use DateInterval;
use Galette\Core\Db;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Throwable;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Analog\Analog;
use Safe\DateTime;

use function Safe\filemtime;
use function Safe\fclose;
use function Safe\fopen;
use function Safe\fwrite;

/**
 * Galette Slim telemetry middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Telemetry
{
    /**
     * @param Db          $zdb         DB instance
     * @param Preferences $preferences Preferences instance
     * @param Plugins     $plugins     Plugins instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins,
    ) {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler Request response
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
                $mdate = DateTime::createFromFormat(
                    $dformat,
                    $telemetry->getSentDate()
                );
                $expire = $mdate->add(
                    new DateInterval('P7D')
                );
                $now = new DateTime();
                $has_expired = $now > $expire;

                if ($has_expired) {
                    $cfile = GALETTE_CACHE_DIR . 'telemetry.cache';
                    if (file_exists($cfile)) {
                        $mdate = DateTime::createFromFormat(
                            $dformat,
                            date(
                                $dformat,
                                filemtime($cfile)
                            )
                        );
                        $expire = $mdate->add(
                            new DateInterval('P7D')
                        );
                        $now = new DateTime();
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
