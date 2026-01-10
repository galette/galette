<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

/**
 * Galette plugin installation
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginInstall extends Install
{
    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->atTypeStep();
    }

    /**
     * Test database connection
     */
    public function testDbConnexion(): bool
    {
        //installing plugin, DB connection is already ok
        return true;
    }

    /**
     * Initialize Galette relevant objects
     *
     * @param I18n  $i18n  I18n
     * @param Db    $zdb   Database instance
     * @param Login $login Logged in instance
     */
    public function initObjects(I18n $i18n, Db $zdb, Login $login): bool
    {
        return false;
    }

    /**
     * Mark a plugin as installed
     *
     * @param Db      $zdb     Database instance
     * @param Plugins $plugins Plugins manager
     * @param string  $id      Plugin ID
     *
     * @return self
     */
    public function setPluginInstalled(Db $zdb, Plugins $plugins, string $id): self
    {
        $module = $plugins->getModule($id, true);
        if (isset($module['dbversion'])) {
            switch ($plugins->getDisabledModule($id)['cause']) {
                case $plugins::DISABLED_NOT_INSTALLED:
                    //add plugin in db
                    $insert = $zdb->insert($plugins::TABLE);
                    $insert->values(
                        [
                            'plugin_id' => $id,
                            'version'   => $module['dbversion']
                        ]
                    );
                    $zdb->execute($insert);
                    break;
                case $plugins::DISABLED_NOT_UP2DATE:
                    //update plugin in db
                    //set database version
                    $update = $zdb->update($plugins::TABLE);
                    $update->set(
                        ['version' => $module['dbversion']]
                    );
                    $update->where(['plugin_id' => $id]);
                    $zdb->execute($update);
                    break;
                default:
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot install plugin "' . $id . '", wrong disabled cause %s.',
                            $id,
                            $plugins->getDisabledModule($id)['cause']
                        )
                    );
            }
        }
        return $this;
    }
}
