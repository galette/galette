<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\Attributes\Route;
use Galette\Middleware\Authenticate;
use Galette\Tests\GaletteRoutingTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

use function Safe\file_get_contents;
use function Safe\preg_match;

/**
 * Validate that Route attributes match declared routes
 *
 * This test ensures consistency between Route attributes in controllers
 * and actual route declarations in routes/*.routes.php files
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteAttributeValidationTest extends GaletteRoutingTestCase
{
    /**
     * Collect all declared routes from the application
     *
     * @return array<string, array{pattern: string, methods: string[], callable: mixed}>
     */
    private function getDeclaredRoutes(): array
    {
        // Get all routes from the Slim app
        $routeCollector = $this->app->getRouteCollector();
        $routes = $routeCollector->getRoutes();

        $declaredRoutes = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $declaredRoutes[$name] = [
                    'pattern' => $route->getPattern(),
                    'methods' => $route->getMethods(),
                    'callable' => $route->getCallable()
                ];
            }
        }

        return $declaredRoutes;
    }

    /**
     * Get middlewares of named routes, including those of their groups
     *
     * Slim does not expose them, they are read by reflection.
     *
     * @return array<string, string[]>
     */
    private function getRoutesMiddlewares(): array
    {
        $middlewares = [];
        foreach ($this->app->getRouteCollector()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            $names = [];
            //route middlewares are stacked in its dispatcher, down to the route itself
            $dispatcher = (new \ReflectionProperty($route, 'middlewareDispatcher'))->getValue($route);
            $node = (new \ReflectionProperty($dispatcher, 'tip'))->getValue($dispatcher);
            while ($node !== $route && is_object($node)) {
                $reflection = new \ReflectionObject($node);
                $middleware = null;
                foreach (['middleware', 'callable'] as $property) {
                    if ($reflection->hasProperty($property)) {
                        $middleware = $reflection->getProperty($property)->getValue($node);
                        break;
                    }
                }
                if ($middleware === null) {
                    break;
                }
                $names[] = is_object($middleware) ? $middleware::class : (string)$middleware;
                $node = $reflection->hasProperty('next') ? $reflection->getProperty('next')->getValue($node) : null;
            }

            $groups = $route instanceof \Slim\Routing\Route ? $route->getGroups() : [];
            foreach ($groups as $group) {
                foreach ((new \ReflectionProperty($group, 'middleware'))->getValue($group) as $middleware) {
                    $names[] = is_object($middleware) ? $middleware::class : (string)$middleware;
                }
            }

            $middlewares[$name] = $names;
        }

        return $middlewares;
    }

    /**
     * Get all controller classes from the codebase
     *
     * @return string[]
     */
    private function getControllerClasses(): array
    {
        // GALETTE_ROOT points to galette/includes/../ so we need to adjust
        $controllersDir = GALETTE_ROOT . 'lib/Galette/Controllers';
        $classes = [];

        if (!is_dir($controllersDir)) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator($controllersDir);
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            $filePath = $file[0];
            $content = file_get_contents($filePath);

            // Extract namespace
            if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
                $namespace = trim($namespaceMatch[1]);

                // Only consider core Galette controllers — plugin controllers
                // are out of scope of this validation (tracked separately).
                if (!str_starts_with($namespace, 'Galette\\Controllers')) {
                    continue;
                }

                // Extract class name
                if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                    $className = $namespace . '\\' . $classMatch[1];

                    if (class_exists($className)) {
                        $classes[] = $className;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * Collect all Route attributes from controllers
     *
     * @return array<string, array{class: string, method: string, route: Route, on_class: bool}>
     */
    private function getRouteAttributesFromControllers(): array
    {
        $classes = $this->getControllerClasses();
        $routeAttributes = [];

        foreach ($classes as $className) {
            $reflection = new \ReflectionClass($className);

            //routes calling an inherited method are declared on the class
            foreach ($reflection->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                if ($route->debugOnly && !\Galette\Core\Galette::isDebugEnabled()) {
                    continue;
                }
                $method = $route->handler ?? '';
                $routeAttributes[$route->name . '::' . $className . '::' . $method] = [
                    'class' => $className,
                    'method' => $method,
                    'route' => $route,
                    'on_class' => true
                ];
            }

            foreach ($reflection->getMethods() as $method) {
                // Skip inherited methods to avoid duplicates
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $attributes = $method->getAttributes(Route::class);

                foreach ($attributes as $attribute) {
                    /** @var Route $route */
                    $route = $attribute->newInstance();
                    //tests do not run in debug mode, debug routes are not declared
                    if ($route->debugOnly && !\Galette\Core\Galette::isDebugEnabled()) {
                        continue;
                    }

                    $key = $route->name . '::' . $className . '::' . $method->getName();
                    $routeAttributes[$key] = [
                        'class' => $className,
                        'method' => $method->getName(),
                        'route' => $route,
                        'on_class' => false
                    ];
                }
            }
        }

        return $routeAttributes;
    }

    /**
     * Test that all Route attributes reference existing routes
     */
    public function testAllRouteAttributesExist(): void
    {
        $declaredRoutes = $this->getDeclaredRoutes();
        $routeAttributes = $this->getRouteAttributesFromControllers();

        $errors = [];

        foreach ($routeAttributes as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            if (!isset($declaredRoutes[$route->name])) {
                $errors[] = sprintf(
                    "Route '%s' documented in %s::%s() is not declared in routes files",
                    $route->name,
                    $className,
                    $methodName
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Found Route attributes referencing non-existent routes:\n" . implode("\n", $errors)
        );
    }

    /**
     * Test that Route attributes match declared route patterns
     */
    public function testRouteAttributePatternsMatch(): void
    {
        $declaredRoutes = $this->getDeclaredRoutes();
        $routeAttributes = $this->getRouteAttributesFromControllers();

        $errors = [];

        foreach ($routeAttributes as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            if (isset($declaredRoutes[$route->name])) {
                $declared = $declaredRoutes[$route->name];

                if ($declared['pattern'] !== $route->pattern) {
                    $errors[] = sprintf(
                        "Route '%s' pattern mismatch in %s::%s():\n  Attribute: '%s'\n  Declared:  '%s'",
                        $route->name,
                        $className,
                        $methodName,
                        $route->pattern,
                        $declared['pattern']
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Found Route attributes with mismatched patterns:\n" . implode("\n", $errors)
        );
    }

    /**
     * Test that Route attributes match declared HTTP methods
     */
    public function testRouteAttributeMethodsMatch(): void
    {
        $declaredRoutes = $this->getDeclaredRoutes();
        $routeAttributes = $this->getRouteAttributesFromControllers();

        $errors = [];

        foreach ($routeAttributes as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            if (isset($declaredRoutes[$route->name])) {
                $declared = $declaredRoutes[$route->name];

                $attrMethods = $route->getMethods();
                $declaredMethods = $declared['methods'];

                sort($attrMethods);
                sort($declaredMethods);

                if ($attrMethods !== $declaredMethods) {
                    $errors[] = sprintf(
                        "Route '%s' HTTP methods mismatch in %s::%s():\n  Attribute: %s\n  Declared:  %s",
                        $route->name,
                        $className,
                        $methodName,
                        implode(', ', $attrMethods),
                        implode(', ', $declaredMethods)
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Found Route attributes with mismatched HTTP methods:\n" . implode("\n", $errors)
        );
    }

    /**
     * Guardrail against a massive regression in attribute coverage.
     * The exact target is enforced by testAllRoutesHaveAttributes(); this
     * test only ensures the discovery mechanism itself stays functional.
     */
    public function testRouteAttributesExist(): void
    {
        $count = count($this->getRouteAttributesFromControllers());

        $this->assertGreaterThanOrEqual(
            40,
            $count,
            sprintf(
                'Only %d Route attribute(s) found in controllers — the attribute '
                . 'discovery mechanism may be broken, or coverage has regressed massively.',
                $count
            )
        );
    }

    /**
     * Test Route attribute structure
     */
    public function testRouteAttributeStructure(): void
    {
        $routeAttributes = $this->getRouteAttributesFromControllers();

        foreach ($routeAttributes as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            $this->assertInstanceOf(
                Route::class,
                $route,
                sprintf('Route attribute in %s::%s() is not an instance of Route', $className, $methodName)
            );

            $this->assertNotEmpty(
                $route->name,
                sprintf('Route name is empty in %s::%s()', $className, $methodName)
            );

            $this->assertNotEmpty(
                $route->pattern,
                sprintf('Route pattern is empty in %s::%s()', $className, $methodName)
            );

            $this->assertNotEmpty(
                $route->getMethods(),
                sprintf('Route methods are empty in %s::%s()', $className, $methodName)
            );

            //a route on a class names its handler, a route on a method does not
            if ($attrData['on_class']) {
                $this->assertTrue(
                    $route->handler !== null && method_exists($className, $route->handler),
                    sprintf('Route "%s" declared on %s must name an existing method as handler', $route->name, $className)
                );
            } else {
                $this->assertNull(
                    $route->handler,
                    sprintf('Route "%s" declared on %s::%s() cannot name a handler', $route->name, $className, $methodName)
                );
            }
        }
    }

    /**
     * Test that every named route (except those in EXCLUDED_ROUTES) is covered
     * by a #[Route] attribute on the controller method that handles it.
     */
    public function testAllRoutesHaveAttributes(): void
    {
        $routes = $this->app->getRouteCollector()->getRoutes();
        $totalRoutes = count($routes);
        $routeAttributes = $this->getRouteAttributesFromControllers();

        // Routes a plugin declares in its own _routes.php are the plugin's
        // business, not the core's: they live under /plugins/<plugin route>/.
        // Core routes under /plugins/ start with a placeholder, never with an
        // actual plugin route, so they are still checked.
        $plugin_prefixes = array_map(
            fn(array $module): string => '/plugins/' . $module['route'] . '/',
            array_values($this->container->get(\Galette\Core\Plugins::class)->getActiveModules())
        );

        // Routes without a name cannot have an attribute: they are reported
        foreach ($routes as $key => $route) {
            $name = $route->getName();
            foreach ($plugin_prefixes as $prefix) {
                if (str_starts_with($route->getPattern(), $prefix)) {
                    unset($routes[$key]);
                    continue 2;
                }
            }
            if ($name === null) {
                continue;
            }
            foreach ($routeAttributes as $akey => $attrData) {
                if ($name === $attrData['route']->name) {
                    unset($routes[$key], $routeAttributes[$akey]);
                    continue 2;
                }
            }
        }

        $remaining = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            $callable = $route->getCallable();
            $target = is_array($callable)
                ? sprintf('%s::%s', $callable[0], $callable[1])
                : (is_string($callable) ? $callable : '(closure)');
            $remaining[] = sprintf(
                "  - '%s' [%s] %s → %s",
                $name ?? '(unnamed)',
                implode(',', $route->getMethods()),
                $route->getPattern(),
                $target
            );
        }

        $this->assertCount(
            0,
            $remaining,
            sprintf(
                "Found %d route(s) without a name or a #[Route] attribute (over %d total). "
                . "Add the attribute to the target controller method.\n%s",
                count($remaining),
                $totalRoutes,
                implode("\n", $remaining)
            )
        );
    }

    /**
     * Test that the #[Route] middlewares, and Authenticate when it requires
     * authentication, are the ones the route is declared with
     */
    public function testMiddlewaresMatch(): void
    {
        $middlewares = $this->getRoutesMiddlewares();
        $attrs = $this->getRouteAttributesFromControllers();
        $errors = [];

        foreach ($attrs as $attrData) {
            $route = $attrData['route'];
            if (!array_key_exists($route->name, $middlewares)) {
                // Attribute points at a non-existent route — handled by
                // testAllRouteAttributesExist; nothing to assert here.
                continue;
            }

            $expected = $route->middleware;
            if ($route->requiresAuth) {
                $expected[] = Authenticate::class;
            }
            $declared = $middlewares[$route->name];
            sort($expected);
            sort($declared);

            if ($expected !== $declared) {
                $errors[] = sprintf(
                    "%s::%s() — route '%s' middlewares mismatch:\n  Attribute: %s\n  Declared:  %s",
                    $attrData['class'],
                    $attrData['method'],
                    $route->name,
                    implode(', ', $expected),
                    implode(', ', $declared)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Found Route attributes with middlewares inconsistent with the declared route:\n"
            . implode("\n", $errors)
        );
    }

    /**
     * Test that every route requiring authentication has an ACL, a known level
     * or permission: Authenticate throws on a route without one, only when it
     * is called.
     */
    public function testAuthenticatedRoutesHaveAcl(): void
    {
        $authenticate = $this->container->get(Authenticate::class);
        $permissions = $this->container->get(\Galette\Access\Permissions::class);
        $levels = ['superadmin', 'admin', 'staff', 'groupmanager', 'member'];
        $errors = [];

        foreach ($this->getRouteAttributesFromControllers() as $attrData) {
            $route = $attrData['route'];
            if (!$route->requiresAuth) {
                continue;
            }

            try {
                $acl = $authenticate->getAclFor($route->name);
            } catch (\RuntimeException) {
                $errors[] = sprintf("Route '%s' has no ACL", $route->name);
                continue;
            }

            if (in_array($acl, $levels, strict: true)) {
                continue;
            }
            //or permissions, any of them granting access
            foreach (explode('|', $acl) as $permission) {
                if (!$permissions->has($permission)) {
                    $errors[] = sprintf("Route '%s' ACL '%s' is neither a level nor a permission", $route->name, $permission);
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Found routes requiring authentication without a valid ACL:\n" . implode("\n", $errors)
        );
    }

    /**
     * Test that each #[Route] attribute points to a route whose callable is
     * actually [thisClass, thisMethod]. Detects stale attributes left behind
     * after a method rename or a controller split.
     */
    public function testNoOrphanRouteAttributes(): void
    {
        $declaredRoutes = $this->getDeclaredRoutes();
        $attrs = $this->getRouteAttributesFromControllers();
        $errors = [];

        foreach ($attrs as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            if (!isset($declaredRoutes[$route->name])) {
                continue; // handled by testAllRouteAttributesExist
            }

            $callable = $declaredRoutes[$route->name]['callable'];
            if (!is_array($callable) || count($callable) !== 2) {
                $errors[] = sprintf(
                    "%s::%s() — route '%s' is declared with a non-array callable; "
                    . "the attribute cannot describe a closure handler",
                    $className,
                    $methodName,
                    $route->name
                );
                continue;
            }

            [$declaredClass, $declaredMethod] = $callable;
            if ($declaredClass !== $className || $declaredMethod !== $methodName) {
                $errors[] = sprintf(
                    "%s::%s() — route '%s' is actually handled by %s::%s()",
                    $className,
                    $methodName,
                    $route->name,
                    is_string($declaredClass) ? $declaredClass : '(unknown)',
                    $declaredMethod
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Found orphan Route attributes (attribute is on a method that does not "
            . "handle the named route):\n" . implode("\n", $errors)
        );
    }
}
