<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console;

use Galette\Core\Galette;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorInterface;
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
     *
     * @param App<ContainerInterface> $app
     */
    public function init(ContainerInterface $container, App $app, RouteCollectorInterface $routeCollector): void
    {
        Galette::loadRoutes($app);

        $pluginRoutes = array_column(
            $container->get(\Galette\Core\Plugins::class)->getModules(),
            'route'
        );

        $this->addCommands([
            new Command\Checks($this->basepath),
            new Command\Install($this->basepath),
            new Command\FeatureStatus($this->basepath),
            new Command\HeadersCheck($this->basepath),
            new Command\CheckRoutes($routeCollector, $this->basepath, $pluginRoutes)
        ]);
        if (!defined('GALETTE_INSTALLER')) {
            //cannot be added until Galette has been properly installed
            $this->addCommands([
                new Command\Plugins\PluginsList($this->basepath),
                new Command\Plugins\PluginEnable($this->basepath),
                new Command\Plugins\PluginDisable($this->basepath),
                new Command\Plugins\PluginInstallDb($this->basepath),
                new Command\SeedFixtures($this->basepath),
                new Command\ProcessMailingQueue($this->basepath)
            ]);
        }
        $this->addCommand(new Command\MakeTwigCache($this->basepath));
    }
}
