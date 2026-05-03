<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteDbPlugin;

use Galette\Core\GalettePlugin;

/**
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGalettePlugdb extends GalettePlugin
{
    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return true;
    }
}
