<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    public const string XHPROF_PATH = '/usr/share/xhprof/xhprof_lib';
    public const string XHPROF_URL  = '/xhprof';

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
     */
    public function stop(): void
    {
        if (self::$run && function_exists('xhprof_disable')) {
            $data = xhprof_disable();

            $incl = (defined('GALETTE_XHPROF_PATH') ? GALETTE_XHPROF_PATH : self::XHPROF_PATH);
            include_once $incl . '/utils/xhprof_lib.php'; // @phpstan-ignore-line
            include_once $incl . '/utils/xhprof_runs.php'; // @phpstan-ignore-line

            $runs = new \XHProfRuns_Default();
            //@phpstan-ignore class.notFound
            $id   = $runs->save_run($data, 'galette-' . GALETTE_VERSION);

            $url  = (defined('XHPROF_URL') ? XHPROF_URL : self::XHPROF_URL);
            $host = (defined('XHPROF_HOST') ? XHPROF_HOST : $_SERVER['HTTP_HOST'] ?? 'localhost');
            $link = 'http://' . $host . $url . '/index.php?run='
                . $id . '&source=galette-' . GALETTE_VERSION;
            Analog::log(
                'Stop profiling with XHProf, result URL: ' . $link,
                Analog::INFO
            );

            self::$run = false;
        }
    }
}
