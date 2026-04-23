<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\Attributes\Route;
use Galette\Tests\GaletteRoutingTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

use function Safe\file;
use function Safe\file_get_contents;
use function Safe\glob;
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
     * Build a map of named routes → whether their declaration includes
     * `->add(Authenticate::class)` (checked textually in *.routes.php files
     * since Slim does not expose route middleware via its public API).
     *
     * @return array<string, bool>
     */
    private function getRouteAuthRequirements(): array
    {
        $routesDir = GALETTE_ROOT . 'includes/routes/';
        $authMap = [];

        foreach (glob($routesDir . '*.routes.php') as $file) {
            $lines = file($file);
            foreach ($lines as $line) {
                if (preg_match('/->setName\([\'"]([^\'"]+)[\'"]\)/i', $line, $m)) {
                    $authMap[$m[1]] = str_contains((string)$line, 'Authenticate::class');
                }
            }
        }

        return $authMap;
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
     * @return array<string, array{class: string, method: string, route: Route}>
     */
    private function getRouteAttributesFromControllers(): array
    {
        $classes = $this->getControllerClasses();
        $routeAttributes = [];

        foreach ($classes as $className) {
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods() as $method) {
                // Skip inherited methods to avoid duplicates
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $attributes = $method->getAttributes(Route::class);

                foreach ($attributes as $attribute) {
                    /** @var Route $route */
                    $route = $attribute->newInstance();

                    $key = $route->name . '::' . $className . '::' . $method->getName();
                    $routeAttributes[$key] = [
                        'class' => $className,
                        'method' => $method->getName(),
                        'route' => $route
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

        // Drop routes that have no name (closures, debug/legacy redirects) —
        // they cannot be addressed by name and are out of scope.
        foreach ($routes as $key => $route) {
            $name = $route->getName();
            if ($name === null) {
                unset($routes[$key]);
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
                $name,
                implode(',', $route->getMethods()),
                $route->getPattern(),
                $target
            );
        }

        $this->assertCount(
            0,
            $remaining,
            sprintf(
                "Found %d named route(s) without a #[Route] attribute (over %d total). "
                . "Add the attribute to the target controller method.\n%s",
                count($remaining),
                $totalRoutes,
                implode("\n", $remaining)
            )
        );
    }

    /**
     * Test that the #[Route] requiresAuth flag matches the actual
     * `->add(Authenticate::class)` declared on the route in *.routes.php.
     */
    public function testRequiresAuthMatchesMiddleware(): void
    {
        $authMap = $this->getRouteAuthRequirements();
        $attrs = $this->getRouteAttributesFromControllers();
        $errors = [];

        foreach ($attrs as $attrData) {
            $route = $attrData['route'];
            $className = $attrData['class'];
            $methodName = $attrData['method'];

            if (!array_key_exists($route->name, $authMap)) {
                // Attribute points at a non-existent route — handled by
                // testAllRouteAttributesExist; nothing to assert here.
                continue;
            }

            $hasAuth = $authMap[$route->name];
            if ($route->requiresAuth && !$hasAuth) {
                $errors[] = sprintf(
                    "%s::%s() — route '%s' declared requiresAuth=true but has no "
                    . "Authenticate middleware",
                    $className,
                    $methodName,
                    $route->name
                );
            } elseif (!$route->requiresAuth && $hasAuth) {
                $errors[] = sprintf(
                    "%s::%s() — route '%s' declared requiresAuth=false but has "
                    . "Authenticate middleware",
                    $className,
                    $methodName,
                    $route->name
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Found Route attributes with requiresAuth flag inconsistent with the "
            . "Authenticate middleware:\n" . implode("\n", $errors)
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
            // Accept attributes on a parent class — Slim resolves the callable
            // through the inheritance chain, so an attribute on CrudController
            // legitimately documents a route handled by a subclass.
            $classMatches = is_string($declaredClass)
                && is_a($declaredClass, $className, true);
            if (!$classMatches || $declaredMethod !== $methodName) {
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
