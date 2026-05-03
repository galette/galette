<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
