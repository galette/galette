<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Galette\Util\Telemetry;

use function Safe\php_sapi_name;

/**
 * Grab system information
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SysInfos
{
    /**
     * Get data as RAW (to send by mail)
     *
     * @param Db          $zdb     Database instance
     * @param Preferences $prefs   Preferences instance
     * @param Plugins     $plugins Plugins
     */
    public function getRawData(Db $zdb, Preferences $prefs, Plugins $plugins): string
    {
        $telemetry = new Telemetry($zdb, $prefs, $plugins);

        $str = str_pad('Galette version:', 20, '.') . ' ' . Galette::gitVersion(true) . "\n";

        if (Galette::isDemo()) {
            $str .= $this->getPluginsInfo($plugins);
            return $str;
        }

        $infos = $telemetry->getTelemetryInfos();
        $db_infos = $infos['system']['db'];
        $db_version = TYPE_DB;
        $db_version .= sprintf(
            ' (%1$s / %2$s)',
            $db_infos['engine'] ?? 'not found',
            $db_infos['version'] ?? 'not found'
        );

        $php_infos = $infos['system']['php'];
        $php_conf = '';
        foreach ($php_infos['setup'] as $key => $value) {
            $php_conf .= str_pad("\n  $key:", 25, '.') . ' ' . $value;
        }

        $str .= str_pad('PHP version:', 20, '.') . ' ' . PHP_VERSION . " " . php_sapi_name() . "\n";
        $str .= 'PHP config:' . $php_conf . "\n";
        $str .= str_pad('Database:', 20, '.') . ' ' . $db_version . "\n";
        $str .= str_pad('OS:', 20, '.') . ' ' . php_uname() . "\n";
        $str .= str_pad('Browser:', 20, '.') . ' ' . $_SERVER['HTTP_USER_AGENT'] . "\n\n";

        $queue = new MailingQueue($zdb, $prefs);
        $qstats = $queue->getStats();
        $qusage = $queue->getUsage();
        $str .= 'Mailing queue:' . "\n";
        $str .= '  Pending: ' . $qstats['remaining'] . "\n";
        $str .= '  Failed: ' . $qstats['failed_total'] . "\n";
        $str .= '  Sent last hour: ' . $qusage['sent_last_hour']
            . ($qusage['hourly_limit'] > 0 ? ' / ' . $qusage['hourly_limit'] : '') . "\n";
        $str .= '  Sent last day: ' . $qusage['sent_last_day']
            . ($qusage['daily_limit'] > 0 ? ' / ' . $qusage['daily_limit'] : '') . "\n\n";

        $str .= 'Modules:' . "\n";
        $mods = new CheckModules();

        $str .= '  OK:' . "\n";
        foreach ($mods->getGoods() as $g) {
            $str .= '    ' . stripslashes($g) . "\n";
        }

        $str .= '  Should:' . "\n";
        foreach ($mods->getShoulds() as $s) {
            $str .= '    ' . stripslashes($s) . "\n";
        }

        $str .= '  Missing:' . "\n";
        foreach ($mods->getMissings() as $m) {
            $str .= '    ' . stripslashes($m) . "\n";
        }

        $str .= $this->getPluginsInfo($plugins);

        $str .= "\n" . 'PHP loaded modules:';
        $i = 0;
        foreach (get_loaded_extensions() as $e) {
            if ($i % 10 === 0) {
                $str .= "\n  ";
            }
            $str .= $e . ", ";
            ++$i;
        }

        return $str;
    }

    /**
     * Get plugins information
     *
     * @param Plugins $plugins Plugins
     */
    private function getPluginsInfo(Plugins $plugins): string
    {
        $str = "\n" . 'Plugins:' . "\n";
        foreach ($plugins->getModules() as $p) {
            $str .= '  ' . $p['name'] . ' ' . $p['version']
                . ' (' . $p['author'] . ")\n";
        }
        return $str;
    }
}
