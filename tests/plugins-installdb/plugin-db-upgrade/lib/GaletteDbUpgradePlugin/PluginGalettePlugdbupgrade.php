<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteDbUpgradePlugin;

use Galette\Core\GalettePlugin;

/**
 * Plugin class for upgrade tests.
 * isInstalled() returns true; the DISABLED_NOT_UP2DATE state is achieved by
 * pre-seeding an older version (0.1) in galette_plugins while the plugin
 * declares dbver 0.2.
 *
 * Class name follows Galette convention: 'PluginGalette' + ucfirst(route).
 * Route is 'plugdbupgrade' → class is 'PluginGalettePlugdbupgrade'.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGalettePlugdbupgrade extends GalettePlugin
{
    public function isInstalled(): bool
    {
        return true;
    }
}
