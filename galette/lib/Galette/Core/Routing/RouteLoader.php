<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Routing;

use Galette\Controllers\AjaxController;
use Galette\Controllers\Attributes\Route;
use Galette\Core\Galette;
use Galette\Core\Plugins;
use Galette\Middleware\Authenticate;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Routing\RouteCollectorProxy;

use function Safe\glob;

/**
 * Registers routes declared with Route attributes on controllers methods
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteLoader
{
    /**
     * Collected routes, per directory
     *
     * @var array<string, list<array{class: class-string, method: string, route: Route}>>
     */
    private static array $collected = [];

    /**
     * Controllers excluded from cron
     *
     * @var class-string[]
     */
    private const array CRON_EXCLUSIONS = [
        AjaxController::class
    ];

    /**
     * Register all routes
     *
     * @template TContainerInterface of (\Psr\Container\ContainerInterface|null)
     *
     * @param RouteCollectorProxyInterface<TContainerInterface> $app  Application (or any route collector proxy)
     * @param bool                                              $cron Load only routes cron tasks may need
     */
    public function load(RouteCollectorProxyInterface $app, bool $cron = false): void
    {
        $routes = $this->collect(GALETTE_ROOT . 'lib/Galette/Controllers', 'Galette\\Controllers');
        if ($cron) {
            $routes = array_filter(
                $routes,
                static fn(array $entry): bool => !in_array($entry['class'], self::CRON_EXCLUSIONS, strict: true)
            );
        }
        $fallbacks = array_filter($routes, static fn(array $entry): bool => $entry['route']->fallback);
        $this->register($app, array_diff_key($routes, $fallbacks));

        if (!$cron) {
            $this->loadPlugins($app);
        }

        //FastRoute rejects a static route declared after a variable one matching it
        $this->register($app, $fallbacks);
    }

    /**
     * Register plugins routes
     *
     * Resources and information pages routes are declared on PluginsController.
     *
     * @template TContainerInterface of (\Psr\Container\ContainerInterface|null)
     *
     * @param RouteCollectorProxyInterface<TContainerInterface> $app Application
     */
    protected function loadPlugins(RouteCollectorProxyInterface $app): void
    {
        $app->group(
            '/plugins',
            function (RouteCollectorProxy $app): void {
                /** @var \DI\Container $container */
                $container = $app->getContainer();
                $modules = $container->get(Plugins::class)->getActiveModules();

                //Declare routes for each plugin, relative to its own route
                foreach ($modules as $module_id => $module) {
                    $container->set('Plugin ' . $module['name'], ['module' => $module, 'module_id' => $module_id]);

                    $app->group(
                        '/' . $module['route'],
                        function (RouteCollectorProxy $app) use ($module, $module_id): void {
                            $this->register($app, $this->collectPlugin($module['root']));
                            $this->includePluginRoutesFile($app, $module, $module_id);
                        }
                    );
                }
            }
        );
    }

    /**
     * Collect routes declared on a plugin controllers
     *
     * Controllers are expected in lib/{Namespace}/Controllers/.
     *
     * @param string $root Plugin root directory
     *
     * @return list<array{class: class-string, method: string, route: Route}>
     */
    protected function collectPlugin(string $root): array
    {
        $routes = [];
        foreach (glob($root . '/lib/*/Controllers', GLOB_ONLYDIR) as $directory) {
            $namespace = basename(dirname($directory)) . '\\Controllers';
            array_push($routes, ...$this->collect($directory, $namespace));
        }
        return $routes;
    }

    /**
     * Include plugin routes file, if any
     *
     * For routes that cannot be declared with attributes. The file has access
     * to $app (the plugin routes group), $module (plugin information),
     * $module_id and $container; the two latter are kept for compatibility
     * with existing plugins.
     *
     * @param RouteCollectorProxy<\Psr\Container\ContainerInterface|null> $app       Plugin routes group
     * @param array<string, mixed>                                        $module    Plugin information
     * @param string                                                      $module_id Plugin identifier
     */
    protected function includePluginRoutesFile(RouteCollectorProxy $app, array $module, string $module_id): void
    {
        $file = $module['root'] . '/_routes.php';
        if (file_exists($file)) {
            (static function (
                RouteCollectorProxy $app,
                array $module,
                string $module_id,
                ?\Psr\Container\ContainerInterface $container
            ) use ($file): void {
                require $file;
            })($app, $module, $module_id, $app->getContainer());
        }
    }

    /**
     * Register collected routes
     *
     * @template TContainerInterface of (\Psr\Container\ContainerInterface|null)
     *
     * @param RouteCollectorProxyInterface<TContainerInterface>               $app    Route collector proxy
     * @param array<array{class: class-string, method: string, route: Route}> $routes Routes to register
     */
    public function register(RouteCollectorProxyInterface $app, array $routes): void
    {
        foreach ($routes as $entry) {
            $route = $entry['route'];
            if ($route->debugOnly && !Galette::isDebugEnabled()) {
                continue;
            }

            $slim_route = $app->map(
                $route->getMethods(),
                $route->pattern,
                [$entry['class'], $entry['method']]
            )->setName($route->name);

            foreach ($route->middleware as $middleware) {
                $slim_route->add($middleware);
            }
            if ($route->requiresAuth) {
                $slim_route->add(Authenticate::class);
            }
        }
    }

    /**
     * Collect routes declared on controllers from a directory
     *
     * @param string $directory Directory to scan
     * @param string $namespace Namespace matching directory (PSR-4)
     *
     * @return list<array{class: class-string, method: string, route: Route}>
     */
    public function collect(string $directory, string $namespace): array
    {
        $directory = rtrim($directory, '/');
        if (isset(self::$collected[$directory])) {
            return self::$collected[$directory];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        //registration order matters for overlapping patterns: keep it stable
        sort($files);

        $routes = [];
        $names = [];
        foreach ($files as $file) {
            $relative = substr($file, strlen($directory) + 1, -4);
            $class = $namespace . '\\' . str_replace('/', '\\', $relative);
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $declared = [];
            foreach ($reflection->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                if ($route->handler === null || !$reflection->hasMethod($route->handler)) {
                    throw new RuntimeException(sprintf(
                        'Route "%1$s" declared on class %2$s must name an existing method as handler',
                        $route->name,
                        $class
                    ));
                }
                $declared[] = [$route->handler, $route];
            }
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                //inherited methods routes are declared on the classes that use them
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    if ($route->handler !== null) {
                        throw new RuntimeException(sprintf(
                            'Route "%1$s" declared on method %2$s::%3$s() cannot name a handler',
                            $route->name,
                            $class,
                            $method->getName()
                        ));
                    }
                    $declared[] = [$method->getName(), $route];
                }
            }

            foreach ($declared as [$handler, $route]) {
                if (isset($names[$route->name])) {
                    throw new RuntimeException(sprintf(
                        'Route "%1$s" is declared twice: for %2$s and for %3$s::%4$s()',
                        $route->name,
                        $names[$route->name],
                        $class,
                        $handler
                    ));
                }
                $names[$route->name] = $class . '::' . $handler . '()';
                $routes[] = [
                    'class' => $class,
                    'method' => $handler,
                    'route' => $route
                ];
            }
        }

        self::$collected[$directory] = $routes;
        return $routes;
    }
}
