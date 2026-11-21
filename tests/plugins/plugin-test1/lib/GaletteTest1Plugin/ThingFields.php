<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin;

use Galette\Features\Dynamics;

/**
 * Dynamic fields of the test plugin objects, through a trait of the plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait ThingFields
{
    use Dynamics;
}
