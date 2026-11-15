<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteDbInstallPlugin;

use Galette\Core\Db;
use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\InstallInitInterface;

/**
 * Plugin class for fresh-install tests.
 * isInstalled() intentionally returns false to simulate a plugin whose
 * database has never been set up (DISABLED_NOT_INSTALLED state).
 *
 * Class name follows Galette convention: 'PluginGalette' + ucfirst(route).
 * Route is 'plugdbinstall' → class is 'PluginGalettePlugdbinstall'.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGalettePlugdbinstall extends GalettePlugin implements InstallInitInterface
{
    /** Error default data creation fails with */
    public static ?\Throwable $init_error = null;

    /**
     * Always returns false so the plugin stays in DISABLED_NOT_INSTALLED state
     * until the install command runs its SQL script.
     */
    public function isInstalled(): bool
    {
        return false;
    }

    /**
     * Create default data
     *
     * @param Db $zdb Database instance
     */
    public function installInit(Db $zdb): void
    {
        if (self::$init_error !== null) {
            throw self::$init_error;
        }
        $insert = $zdb->insert('plugin_db_install_test');
        $insert->values(['label' => _T('Default label', 'plugdbinstall')]);
        $zdb->execute($insert);
    }
}
