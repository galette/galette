<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

use Galette\Core\Db;

/**
 * Plugin that creates default data when it is installed
 *
 * Data is created in the language of the user installing the plugin; plugin
 * translations are loaded beforehand.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface InstallInitInterface
{
    /**
     * Create default data, once plugin tables are created
     *
     * @param Db $zdb Database instance
     *
     * @throws \Throwable when data cannot be created
     */
    public function installInit(Db $zdb): void;
}
