<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;

/**
 * Slim application
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TContainerInterface of (ContainerInterface|null)
 */
class SlimApp
{
    /** @var App<TContainerInterface> */
    private readonly App $app;

    /**
     * Create a new Slim application
     */
    public function __construct(
        protected Plugins $plugins,
        protected string $mode = GALETTE_MODE
    ) {
        $builder = new ContainerBuilder();
        $builder->useAttributes(true);
        $builder->addDefinitions($this->getContainerDefinitions());
        $container = $builder->build();

        $this->app = Bridge::create($container);
        $this->loadDependencies();
    }

    /**
     * Get container definitions
     *
     * @return array{"galette": array{"mode": string}, "mode": string, "galette.mode": string}
     */
    protected function getContainerDefinitions(): array
    {
        return [
            'galette' => [
                'mode' => $this->mode
            ],
            'mode' => $this->mode,
            'galette.mode' => $this->mode
        ];
    }

    /**
     * Load application dependencies
     */
    public function loadDependencies(): void
    {
        $app = $this->app; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used from include
        $plugins = $this->plugins; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used from include
        require GALETTE_ROOT . '/includes/dependencies.php';
    }

    /**
     * Get Slim application
     *
     * @return App<TContainerInterface>
     */
    public function getApp(): App
    {
        return $this->app;
    }
}
