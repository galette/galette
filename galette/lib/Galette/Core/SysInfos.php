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

namespace Galette\Core;

use Galette\Util\Telemetry;

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
     *
     * @return string
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
     *
     * @return string
     */
    private function getPluginsInfo(Plugins $plugins): string
    {
        $str = "\n" . 'Plugins:' . "\n";
        foreach ($plugins->getModules() as $p) {
            $str .= '  ' . $p['name'] . ' ' . $p['version'] .
                ' (' . $p['author'] . ")\n";
        }
        return $str;
    }
}
