<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console;

use DI\Container;
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
    public function init(Container $container, App $app, RouteCollectorInterface $routeCollector): void
    {
        Galette::loadRoutes($app);

        $pluginRoutes = array_column(
            $container->get(\Galette\Core\Plugins::class)->getModules(),
            'route'
        );

        $this->addCommands([
            $this->make($container, Command\Checks::class),
            $this->make($container, Command\Install::class),
            $this->make($container, Command\FeatureStatus::class),
            $this->make($container, Command\HeadersCheck::class),
            $this->make(
                $container,
                Command\CheckRoutes::class,
                ['routeCollector' => $routeCollector, 'pluginRoutes' => $pluginRoutes]
            )
        ]);
        if (!defined('GALETTE_INSTALLER')) {
            //cannot be added until Galette has been properly installed
            $this->addCommands([
                $this->make($container, Command\Plugins\PluginsList::class),
                $this->make($container, Command\Plugins\PluginEnable::class),
                $this->make($container, Command\Plugins\PluginDisable::class),
                $this->make($container, Command\Plugins\PluginInstallDb::class),
                $this->make($container, Command\SeedFixtures::class),
                $this->make($container, Command\SuperAdminPassword::class),
                $this->make($container, Command\TwoFactorReset::class),
                $this->make($container, Command\FeatureEnable::class),
                $this->make($container, Command\FeatureDisable::class),
                $this->make($container, Command\ProcessMailingQueue::class)
            ]);
        }
        $this->addCommand($this->make($container, Command\MakeTwigCache::class));
        $this->addCommand($this->make($container, Command\TwigPotReferences::class));
        $this->addCommand($this->make($container, Command\CompileLocales::class));
    }

    /**
     * Build a command from the container, so its dependencies are injected
     *
     * @template T of Command\AbstractCommand
     *
     * @param Container            $container  Container instance
     * @param class-string<T>      $class      Command class
     * @param array<string, mixed> $parameters Extra constructor parameters
     *
     * @return T
     */
    private function make(Container $container, string $class, array $parameters = []): Command\AbstractCommand
    {
        return $container->make($class, ['basepath' => $this->basepath] + $parameters);
    }
}
