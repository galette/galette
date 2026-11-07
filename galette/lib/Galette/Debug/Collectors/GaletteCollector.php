<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Galette\Core\Db;
use Galette\Core\FeatureFlagManager;
use Galette\Core\Galette;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Middleware\Authenticate;
use Psr\Container\ContainerInterface;
use Slim\Interfaces\RouteInterface;
use Throwable;

/**
 * Galette state collector: current user, route, ACL, plugins, flags
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteCollector extends DataCollector implements Renderable
{
    private ?ContainerInterface $container = null;
    private ?RouteInterface $route = null;

    /**
     * Set container, to reach Galette services when collecting
     *
     * @param ContainerInterface $container Container instance
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Set the route that served current request
     *
     * @param ?RouteInterface $route Matched route
     */
    public function setRoute(?RouteInterface $route): self
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Collect data
     *
     * @return array<string, string>
     */
    public function collect(): array
    {
        $data = [
            'version' => Galette::gitVersion(time: true),
            'mode' => GALETTE_MODE,
        ] + $this->collectRoute() + $this->collectServices();

        return array_map($this->stringify(...), $data);
    }

    /**
     * Render a value as plain text
     *
     * @param mixed $value Value
     */
    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '(none)';
            }
            if (array_is_list($value)) {
                return implode(', ', array_map($this->stringify(...), $value));
            }
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = $key . ': ' . $this->stringify($item);
            }
            return implode(', ', $parts);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return is_scalar($value) ? (string)$value : get_debug_type($value);
    }

    /**
     * Route information
     *
     * @return array<string, mixed>
     */
    private function collectRoute(): array
    {
        if ($this->route === null) {
            return ['route' => '(none)'];
        }

        $name = $this->route->getName() ?? '(unnamed)';
        $data = [
            'route' => $name,
            'route pattern' => $this->route->getPattern(),
            'route arguments' => $this->route->getArguments(),
            'route callable' => $this->describeCallable($this->route->getCallable()),
        ];

        if ($this->container !== null && $this->route->getName() !== null) {
            try {
                $data['route ACL'] = $this->container->get(Authenticate::class)->getAclFor($name);
            } catch (Throwable) {
                // no log: an unregistered route is what the bar has to show
                $data['route ACL'] = '(not registered)';
            }
        }

        return $data;
    }

    /**
     * Information from Galette services
     *
     * @return array<string, mixed>
     */
    private function collectServices(): array
    {
        if ($this->container === null) {
            return [];
        }

        $data = [];
        try {
            $login = $this->container->get(Login::class);
            $data['user'] = match (true) {
                !$login->isLogged() => '(anonymous)',
                $login->isSuperAdminAccount() => $login->login . ' (superadmin account)',
                default => sprintf('%s (#%d)', $login->login, (int)$login->id),
            };
            if ($login->isLogged()) {
                $data['user roles'] = implode(', ', array_keys(array_filter([
                    'superadmin' => $login->isSuperAdmin(),
                    'admin' => $login->isAdmin(),
                    'staff' => $login->isStaff(),
                    'group manager' => $login->isGroupManager(),
                    'impersonated' => $login->isImpersonated(),
                ]))) ?: 'member';
            }
        } catch (Throwable $e) {
            // no log: the failure is shown in the bar itself
            $data['user'] = 'Unavailable: ' . $e->getMessage();
        }

        //no query from here: it would show up in the SQL panel
        try {
            $data['database'] = sprintf(
                '%s, %s',
                TYPE_DB,
                $this->container->get(Db::class)->getDatabase()
            );
        } catch (Throwable $e) {
            // no log: the failure is shown in the bar itself
            $data['database'] = 'Unavailable: ' . $e->getMessage();
        }

        try {
            $plugins = $this->container->get(Plugins::class);
            $data['plugins'] = array_map(
                static fn(array $module): string => $module['name'] . ' ' . $module['version'],
                $plugins->getActiveModules()
            );
            $disabled = array_keys($plugins->getDisabledModules());
            if (count($disabled) > 0) {
                $data['disabled plugins'] = $disabled;
            }
        } catch (Throwable $e) {
            // no log: the failure is shown in the bar itself
            $data['plugins'] = 'Unavailable: ' . $e->getMessage();
        }

        try {
            $data['feature flags'] = $this->container->get(FeatureFlagManager::class)->getDeclaredFlags();
        } catch (Throwable $e) {
            // no log: the failure is shown in the bar itself
            $data['feature flags'] = 'Unavailable: ' . $e->getMessage();
        }

        return $data;
    }

    /**
     * Describe a route callable
     *
     * @param mixed $callable Route callable
     */
    private function describeCallable(mixed $callable): string
    {
        if (is_string($callable)) {
            return $callable;
        }
        if (is_array($callable) && count($callable) === 2) {
            $class = is_object($callable[0]) ? $callable[0]::class : (string)$callable[0];
            return $class . '::' . (string)$callable[1];
        }
        if ($callable instanceof \Closure) {
            $reflection = new \ReflectionFunction($callable);
            return sprintf(
                'Closure in %s:%d',
                basename((string)$reflection->getFileName()),
                (int)$reflection->getStartLine()
            );
        }
        return get_debug_type($callable);
    }

    /**
     * Collector name
     */
    public function getName(): string
    {
        return 'galette';
    }

    /**
     * Widgets
     *
     * @return array<string, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return [
            'galette' => [
                'icon' => 'info-circle',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'galette',
                'default' => '{}',
            ],
        ];
    }
}
