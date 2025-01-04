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

namespace Galette\Common;

use Analog\Analog;

/**
 * class XHProf
 *
 * Il you need to "profile" some part of code
 *
 * Install the pecl/xhprof extension
 *
 * Add XHPROF_PATH and XHPROF_URL in config/local_paths.inc.php (if needed)
 *
 * Before the code
 *    $prof = new XHProf("something useful");
 *
 * If the code contains an exit() or a redirect() you must also call (before)
 *    unset($prof);
 *
 * php-errors.log will give you the URL of the result.
 *
 * @author Kenny Katzgrau <katzgrau@gmail.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class XHProf
{
    // this can be overloaded in config/config_path.php
    public const XHPROF_PATH = '/usr/share/xhprof/xhprof_lib';
    public const XHPROF_URL  = '/xhprof';

    private static bool $run = false;

    /**
     * Default constructor
     *
     * @param string $msg Message(default '')
     */
    public function __construct(string $msg = '')
    {
        $this->start($msg);
    }


    /**
     * Destruct the object
     */
    public function __destruct()
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
    public function start(string $msg = ''): void
    {
        if (
            !self::$run
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
            self::$run = true;
        }
    }

    /**
     * Stops profiling
     *
     * @return void
     */
    public function stop(): void
    {
        if (self::$run && function_exists('xhprof_disable')) {
            $data = xhprof_disable();

            $incl = (defined('GALETTE_XHPROF_PATH') ? GALETTE_XHPROF_PATH : self::XHPROF_PATH);
            include_once $incl . '/utils/xhprof_lib.php'; // @phpstan-ignore-line
            include_once $incl . '/utils/xhprof_runs.php'; // @phpstan-ignore-line

            $runs = new \XHProfRuns_Default();
            // @phpstan-ignore-next-line
            $id   = $runs->save_run($data, 'galette-' . GALETTE_VERSION);

            $url  = (defined('XHPROF_URL') ? XHPROF_URL : self::XHPROF_URL);
            $host = (defined('XHPROF_HOST') ? XHPROF_HOST : $_SERVER['HTTP_HOST'] ?? 'localhost');
            $link = 'http://' . $host . $url . '/index.php?run=' .
                $id . '&source=galette-' . GALETTE_VERSION;
            Analog::log(
                'Stop profiling with XHProf, result URL: ' . $link,
                Analog::INFO
            );

            self::$run = false;
        }
    }
}
