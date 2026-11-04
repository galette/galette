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
use Galette\Handlers\ErrorHandler;
use Galette\Middleware\Csrf;
use Galette\Middleware\Language;
use Galette\Middleware\RouteGlobals;
use Galette\Middleware\Telemetry;
use Galette\Middleware\UpdateAndMaintenance;
use Galette\Renderers\Html;
use League\Event\EventDispatcher;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RKA\SessionMiddleware;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use function DI\factory;
use function Safe\parse_url;

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
     * Configure web application: base path, middlewares, routes, error handling
     *
     * Slim runs middlewares last in, first out: the first added ones are the
     * closest to the routes.
     *
     * @param SessionMiddleware $session Session middleware
     * @param LoggerInterface   $logger  Logger for uncaught errors
     */
    public function configure(SessionMiddleware $session, LoggerInterface $logger): void
    {
        $this->app->setBasePath(self::detectBasePath());

        $this->app->add($session);
        $this->app->add(Csrf::class);

        if ($this->mode === self::MODE_NEED_UPDATE) {
            //plugins are not loaded; the update page is all that can be shown
            Galette::loadRoutes($this->app);
            $this->app->add($this->container->make(
                UpdateAndMaintenance::class,
                ['callback' => UpdateAndMaintenance::NEED_UPDATE]
            ));
            return;
        }

        if (Galette::isUnderMaintenance() && !$this->container->get(Login::class)->isSuperAdmin()) {
            $this->app->add($this->container->make(
                UpdateAndMaintenance::class,
                ['callback' => UpdateAndMaintenance::MAINTENANCE]
            ));
        }

        $this->app->add(Language::class);
        $this->app->add(Telemetry::class);

        Galette::loadRoutes($this->app);

        $this->app->add(RouteGlobals::class);
        $this->app->addRoutingMiddleware();
        //last, so it handles errors from all middlewares added before
        $this->addErrorHandling($logger);
        //after error handling, so it can be used to render errors
        $this->app->add(TwigMiddleware::createFromContainer($this->app, Twig::class));
    }

    /**
     * Add error middleware, rendering Galette error pages
     *
     * @param ?LoggerInterface $logger Logger; when null, errors are not logged
     */
    public function addErrorHandling(?LoggerInterface $logger): ErrorMiddleware
    {
        $log = $logger !== null;
        $errorMiddleware = $this->app->addErrorMiddleware(
            displayErrorDetails: Galette::isDebugEnabled(),
            logErrors: $log,
            logErrorDetails: $log,
            logger: $logger
        );

        if ($log) {
            $errorHandler = new ErrorHandler(
                $this->app->getCallableResolver(),
                $this->app->getResponseFactory(),
                $logger
            );
            $errorMiddleware->setDefaultErrorHandler($errorHandler);
        } else {
            /** @var \Slim\Handlers\ErrorHandler $errorHandler */
            $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        }
        $errorHandler->registerErrorRenderer('text/html', Html::class);
        //also use Galette error pages for clients that do not negotiate content type (Accept: */*)
        $errorHandler->setDefaultErrorRenderer('text/html', Html::class);

        return $errorMiddleware;
    }

    /**
     * Detect application base path from current request
     */
    public static function detectBasePath(): string
    {
        $uri = (string)parse_url('http://a' . ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (stripos($uri, (string)$_SERVER['SCRIPT_NAME']) === 0) {
            return dirname((string)$_SERVER['SCRIPT_NAME']);
        }

        $scriptDir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME']));
        if ($scriptDir !== '/' && stripos($uri, $scriptDir) === 0) {
            return $scriptDir;
        }

        return '';
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
