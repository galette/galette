<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteBothPlugin;

use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\PluginDefinition;

/**
 * Plugin declared with both an attribute and a _define.php file
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[PluginDefinition(
    name: 'Galette Both Plugin',
    desc: 'Test plugin declared twice',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'both'
)]
class PluginGaletteBoth extends GalettePlugin
{
}
