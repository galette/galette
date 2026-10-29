<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteDbInstallPlugin;

use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\PluginDefinition;

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
#[PluginDefinition(
    name: 'Galette Db Install Plugin',
    desc: 'Test db install plugin',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'plugdbinstall',
    date: '2026-01-01',
    acls: [],
    dbver: 0.1
)]
class PluginGalettePlugdbinstall extends GalettePlugin
{
    /**
     * Always returns false so the plugin stays in DISABLED_NOT_INSTALLED state
     * until the install command runs its SQL script.
     */
    public function isInstalled(): bool
    {
        return false;
    }
}
