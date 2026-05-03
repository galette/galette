<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Core\PluginControllerTrait;

/**
 * Galette abstract controller for plugins
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class AbstractPluginController extends AbstractController
{
    use PluginControllerTrait;
}
