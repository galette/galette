<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Galette\Core\Plugins\InstallInitInterface;
use RuntimeException;
use Throwable;

/**
 * Galette plugin installation
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginInstall extends Install
{
    /** Identifier of the plugin being installed or updated */
    private ?string $plugin_id = null;
    /** Namespace of the plugin being installed or updated */
    private ?string $plugin_namespace = null;

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
        //plugins create their default data from initPlugin()
        return false;
    }

    /**
     * Set the plugin to install or update
     *
     * Its translations are loaded, so update scripts and default data
     * can use them.
     *
     * @param Plugins $plugins Plugins manager
     * @param string  $id      Plugin ID
     * @param I18n    $i18n    Current language
     */
    public function setPlugin(Plugins $plugins, string $id, I18n $i18n): self
    {
        $this->plugin_id = $id;
        $this->plugin_namespace = $plugins->getNamespace($id);
        $plugins->loadModuleL10N($id, $i18n->getLongID());
        return $this;
    }

    /**
     * Get the class name of the update script for a version
     *
     * Plugins update classes live in their own namespace:
     * <PluginNamespace>\Updates\UpgradeTo<version without dots>.
     *
     * @param string $version Update script version
     */
    protected function getUpdaterClass(string $version): string
    {
        if ($this->plugin_namespace === null) {
            throw new RuntimeException('Plugin must be set to run its update classes.');
        }
        return '\\' . $this->plugin_namespace . '\\Updates\\UpgradeTo' . str_replace('.', '', $version);
    }

    /**
     * Store the database version an update script brought
     *
     * Plugin version is stored once all its scripts have run, see setPluginInstalled().
     *
     * @param Db     $zdb     Database instance
     * @param string $version Database version
     */
    public function updateDbVersion(Db $zdb, string $version): void
    {
        //Galette database version must not change
    }

    /**
     * Create plugin default data, on install only
     *
     * @param Plugins $plugins Plugins manager
     * @param Db      $zdb     Database instance
     */
    public function initPlugin(Plugins $plugins, Db $zdb): bool
    {
        if (!$this->isInstall() || $this->plugin_id === null) {
            return true;
        }

        $plugin = $plugins->getPlugin($this->plugin_id);
        if (!$plugin instanceof InstallInitInterface) {
            return true;
        }

        $res = true;
        try {
            $plugin->installInit($zdb);
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to create default data for plugin ' . $this->plugin_id, \Analog\Analog::ERROR);
            $res = $e;
        }
        $this->proceedReport(_T('Default data'), $res);
        return $res === true;
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
