<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Console;

use Symfony\Component\Console\Application;

/**
 * Galette console application
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteApplication extends Application
{
    /**
     * Default constructor
     *
     * @param string $basepath Base path to Galette installation
     */
    public function __construct(private readonly string $basepath)
    {
        parent::__construct('Galette', GALETTE_VERSION);
    }

    /**
     * Initialize application
     */
    public function init(): void
    {
        $this->addCommands([
            new Command\Checks($this->basepath),
            new Command\Install($this->basepath),
            new Command\FeatureStatus($this->basepath),
            new Command\HeadersCheck($this->basepath)
        ]);
        if (!defined('GALETTE_INSTALLER')) {
            //cannot be added until Galette has been properly installed
            $this->addCommands([
                new Command\Plugins\PluginsList($this->basepath),
                new Command\Plugins\PluginEnable($this->basepath),
                new Command\Plugins\PluginDisable($this->basepath),
                new Command\Plugins\PluginInstallDb($this->basepath),
                new Command\SeedFixtures($this->basepath)
            ]);
        }
        $this->addCommand(new Command\MakeTwigCache($this->basepath));
    }
}
