<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * Checks routes naming conventions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:check-routes',
    description: 'Check Galette routes naming conventions'
)]
class CheckRoutes extends AbstractCommand
{
    // Convention regexp: module(.sub-module)*.action(.post)?
    private const string ROUTE_NAME_PATTERN = '/^[a-z]+(\.[a-z]+)*\.(list|show|add|add\.post|edit|edit\.post|delete|export|import)$/';

    // Legacy naming conventions: simple word, camelCase, kebab-case or snake_case
    private const string LEGACY_ROUTE_NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9\-_]*$/';

    // URLs conventions (applied on normalized pattern): lowercase, kebab-case, no trailing slash.
    // Each segment is either a literal (/word), a parameter (/{param}), or a mix (/word{param}).
    private const string URL_PATTERN = '/^(\/([a-z0-9][a-z0-9\-]*(\{param})?|\{param}))+$/';

    /**
     * Default constructor
     *
     * @param RouteCollectorInterface $routeCollector Routes collector
     * @param string                  $basepath       Base path to Galette installation
     * @param string[]                $pluginRoutes   List of known plugin route slugs
     */
    public function __construct(
        private readonly RouteCollectorInterface $routeCollector,
        protected string $basepath,
        private readonly array $pluginRoutes = []
    ) {
        parent::__construct($basepath);
    }

    /**
     * Command execution
     */
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Check routes names')]
        ?bool $names = null,
        #[Option(description: 'Check routes URLs')]
        ?bool $urls = null,
        #[Option(description: 'Check also plugins')]
        ?bool $plugins = null
    ): int {
        $output->writeln([
            '<info><href=https://galette.eu>Galette</> routes naming conventions check</info>',
            '<info>=======================================</info>',
            ''
        ]);

        $withnames = $this->input->getOption('names') ?? true;
        $withurls = $this->input->getOption('urls') ?? true;
        $withplugins = $this->input->getOption('plugins') ?? true;
        $verbose = $this->input->getOption('verbose');
        if (!$withnames && !$withurls) {
            $output->writeln('<comment>Nothing to check, at least one of --names or --urls must be set.</comment>');
            return Command::INVALID;
        }

        $io = new SymfonyStyle($input, $output);

        $routes = $this->routeCollector->getRoutes();
        $namesErrors = 0;
        $urlsErrors = 0;
        $warnings = [];
        $rows = [];

        $headers = [];
        if ($withnames) {
            $headers[] = 'Name';
        }
        if ($withurls) {
            $headers[] = 'URL';
        }
        $headers[] = 'Methods';

        foreach ($routes as $k => $route) {
            $row = [];
            $name = $route->getName() ?? '(not named)';
            $pattern = $route->getPattern();
            $methods = implode('|', $route->getMethods());

            if (str_starts_with($pattern, '/plugins/') && !$withplugins) {
                unset($routes[$k]);
                continue;
            }

            if ($withnames) {
                $nameOk = $this->validateLegacyRouteName($route) || $this->validateRouteName($route);
                $nameStatus = $nameOk ? '✅' : '❌';
                if (!$route->getName()) {
                    $nameStatus .= ' /!\ ';
                    $warnings[] = "Not named route: [$methods] $pattern";
                } elseif (!$nameOk) {
                    ++$namesErrors;
                }
                $row[] = sprintf('%s %s', $nameStatus, $name);
            }

            if ($withurls) {
                $urlOk = $this->validateURL($pattern);
                $urlStatus = $urlOk ? '✅' : '❌';
                if (!$urlOk) {
                    ++$urlsErrors;
                }
                $row[] = sprintf('%s %s', $urlStatus, $pattern);
            }

            if ($verbose || !($nameOk ?? true) || !($urlOk ?? true)) {
                $row[] = $methods;
                $rows[] = $row;
            }
        }

        if (count($rows)) {
            $io->table($headers, $rows);
        }

        if ($warnings) {
            $io->newLine();
            $io->writeln('<comment>⚠️  Warnings</comment>');
            foreach ($warnings as $warning) {
                $io->writeln(sprintf('   %s', $warning));
            }
            $io->newLine();
        }
        if ($namesErrors || $urlsErrors) {
            $io->error(
                sprintf(
                    '%d checked routes%s%s%s',
                    count($routes),
                    $withnames && $namesErrors ? sprintf(', %d names errors', $namesErrors) : '',
                    $withurls && $urlsErrors ? sprintf(', %d URLs errors', $urlsErrors) : '',
                    count($warnings) ? sprintf(', %d warnings', count($warnings)) : '',
                )
            );
            return Command::FAILURE;
        }

        $io->success(sprintf('%d checked routes.', count($routes)));
        return Command::SUCCESS;
    }

    /**
     * Validates a route name against the legacy conventions (word, camelCase, kebab-case, snake_case).
     * For plugin routes (literal plugin name extractable from URL), the name must be prefixed
     * with the plugin name.
     */
    private function validateLegacyRouteName(RouteInterface $route): bool
    {
        $name = $route->getName();
        if (!$name) {
            return true;
        }

        $pluginName = $this->extractPluginName($route->getPattern());
        if ($pluginName !== '' && !str_starts_with($name, $pluginName)) {
            return false;
        }

        return (bool)preg_match(self::LEGACY_ROUTE_NAME_PATTERN, $name);
    }

    /**
     * Validates a route name against the future convention: module(.sub-module)*.action(.post)?
     * For plugin routes (literal plugin name extractable from URL), the first module segment
     * must match the plugin name.
     */
    private function validateRouteName(RouteInterface $route): bool
    {
        $name = $route->getName();
        if (!$name) {
            return true;
        }

        $pluginName = $this->extractPluginName($route->getPattern());
        if ($pluginName !== '' && !str_starts_with($name, $pluginName . '.')) {
            return false;
        }

        return (bool)preg_match(self::ROUTE_NAME_PATTERN, $name);
    }

    /**
     * Extracts the literal plugin name from a route URL pattern.
     * Returns an empty string for core routes like /plugins/{plugin}/res/... where the
     * plugin segment is a parameter (not a literal name), or when the extracted name
     * does not correspond to a known plugin route slug.
     */
    private function extractPluginName(string $pattern): string
    {
        // Only match a literal (non-parameter) plugin name after /plugins/ ending with a slash.
        if (preg_match('#^/plugins/([a-z0-9][a-z0-9\-]*)/(.+|$)#', $pattern, $matches)) {
            $candidate = $matches[1];
            // Verify the extracted name is a known plugin route slug (if the list is available)
            if (!in_array($candidate, $this->pluginRoutes, true)) {
                return '';
            }
            return $candidate;
        }
        return '';
    }

    /**
     * Validate URL pattern
     */
    private function validateURL(string $pattern): bool
    {
        // Root route is always valid
        if ($pattern === '/') {
            return true;
        }

        // Remove Slim optional segment markers [ and ]
        $normalized = str_replace(['[', ']'], '', $pattern);

        // Replace any parameter expression {name} or {name:regex} with a fixed placeholder
        $normalized = preg_replace('/\{[^}]+}/', '{param}', $normalized);

        return (bool)preg_match(self::URL_PATTERN, $normalized);
    }
}
