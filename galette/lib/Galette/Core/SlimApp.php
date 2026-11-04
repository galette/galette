<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use Galette\Debug\DebugBar;
use League\Event\EventDispatcher;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteParser;

use function DI\factory;

/**
 * Slim application
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TContainerInterface of (ContainerInterface|null)
 */
class SlimApp
{
    /** Database must be updated, only core routes are available */
    public const string MODE_NEED_UPDATE = 'NEED_UPDATE';
    /** Installation is running */
    public const string MODE_INSTALL = 'INSTALL';
    /** Running from a cron task */
    public const string MODE_CRON = 'CRON';

    /** @var App<TContainerInterface> */
    private readonly App $app;
    private readonly Container $container;

    /**
     * Create a new Slim application
     *
     * @param Plugins $plugins Plugins instance
     * @param string  $mode    Application mode: one of Galette::MODE_* or self::MODE_*
     */
    public function __construct(
        protected Plugins $plugins,
        protected string $mode = GALETTE_MODE
    ) {
        $builder = new ContainerBuilder();
        $builder->useAttributes(bool: true);
        $builder->addDefinitions(require GALETTE_ROOT . 'includes/dependencies.php');
        $builder->addDefinitions($this->getContainerDefinitions());
        $this->container = $builder->build();
        DebugBar::setContainer($this->container);

        $this->app = Bridge::create($this->container);
        $this->exposeLegacyGlobals();
    }

    /**
     * Container definitions depending on runtime values
     *
     * @return array<string, mixed>
     */
    protected function getContainerDefinitions(): array
    {
        return [
            'galette.mode' => $this->mode,
            Plugins::class => factory(function (ContainerInterface $c): Plugins {
                if ($this->isFullyBooted()) {
                    $this->plugins
                        ->setContainer($c)
                        ->loadModules(
                            $c->get(Preferences::class),
                            GALETTE_PLUGINS_PATH,
                            $c->get(I18n::class)->getLongID()
                        );
                }
                return $this->plugins;
            })
        ];
    }

    /**
     * Is application fully booted?
     *
     * Not while installing, nor when database needs an update: preferences,
     * login and plugins cannot be relied on then.
     */
    public function isFullyBooted(): bool
    {
        return $this->mode !== self::MODE_INSTALL
            && $this->mode !== self::MODE_NEED_UPDATE
            && !defined('GALETTE_INSTALLER');
    }

    /**
     * Set legacy globals
     *
     * Code that is not built from the container still reads those.
     */
    protected function exposeLegacyGlobals(): void
    {
        //phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- globals \o/
        global $translator, $i18n;
        if ($this->isFullyBooted()) {
            global $zdb, $preferences, $login, $hist, $l10n, $emitter, $routeparser;
            $zdb = $this->container->get(Db::class);
            $preferences = $this->container->get(Preferences::class);
            $login = $this->container->get(Login::class);
            $hist = $this->container->get(History::class);
            $l10n = $this->container->get(L10n::class);
            $emitter = $this->container->get(EventDispatcher::class);
            $routeparser = $this->container->get(RouteParser::class);
        }
        $i18n = $this->container->get(I18n::class);
        $translator = $this->container->get(Translator::class);
        //phpcs:enable
        require_once GALETTE_ROOT . 'includes/i18n.inc.php';
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

    /**
     * Get container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}
