<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
        //TODO: plugins should be able to add objects to initialize
        return false;
    }

    /**
     * Mark a plugin as installed
     *
     * @param Db      $zdb     Database instance
     * @param Plugins $plugins Plugins manager
     * @param string  $id      Plugin ID
     */
    public function setPluginInstalled(Db $zdb, Plugins $plugins, string $id): self
    {
        $module = $plugins->getModule($id);

        if (isset($module['dbversion'])) {
            $disabledCause = null;
            if ($plugins->isDisabled($id)) {
                $disabledCause = $plugins->getDisabledCause($id);
            }
            switch ($disabledCause) {
                case $plugins::DISABLED_NOT_INSTALLED:
                    if ($this->pluginRowExists($zdb, $plugins, $id)) {
                        //the row already exists (e.g. plugin tables were dropped externally
                        //but the galette_plugins entry remained); update instead of inserting
                        //to avoid a primary key violation.
                        $update = $zdb->update($plugins::TABLE);
                        $update->set(['version' => $module['dbversion']]);
                        $update->where(['plugin_id' => $id]);
                        $zdb->execute($update);
                    } else {
                        //add plugin in db
                        $insert = $zdb->insert($plugins::TABLE);
                        $insert->values(
                            [
                                'plugin_id' => $id,
                                'version'   => $module['dbversion']
                            ]
                        );
                        $zdb->execute($insert);
                    }
                    break;
                case $plugins::DISABLED_NOT_UP2DATE:
                case null:
                    if (!$this->pluginRowExists($zdb, $plugins, $id)) {
                        //the galette_plugins row is missing (e.g. a prior auto-migration
                        //silently swallowed a missing-table error); insert it so the
                        //tracking version isn't silently lost.
                        $insert = $zdb->insert($plugins::TABLE);
                        $insert->values(
                            [
                                'plugin_id' => $id,
                                'version'   => $module['dbversion']
                            ]
                        );
                        $zdb->execute($insert);
                    } else {
                        //update plugin in db
                        //set database version
                        $update = $zdb->update($plugins::TABLE);
                        $update->set(['version' => $module['dbversion']]);
                        $update->where(['plugin_id' => $id]);
                        $zdb->execute($update);
                    }
                    break;
                default:
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot install plugin %s, wrong disabled cause %s.',
                            $id,
                            $disabledCause
                        )
                    );
            }
        }
        return $this;
    }

    /**
     * Check whether a row already exists for the given plugin in the
     * plugins tracking table.
     */
    private function pluginRowExists(Db $zdb, Plugins $plugins, string $id): bool
    {
        $select = $zdb->select($plugins::TABLE);
        $select->columns([$plugins::PK]);
        $select->where([$plugins::PK => $id]);
        $results = $zdb->execute($select);
        return $results->count() > 0;
    }
}
