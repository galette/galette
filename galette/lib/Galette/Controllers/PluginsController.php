<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Galette\Core\Plugins;
use Throwable;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\Galette;
use Galette\Core\PluginInstall;
use Analog\Analog;
use Galette\Core\Logs;
use Galette\Util\Release;

use function Safe\file_get_contents;

/**
 * Galette plugins controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginsController extends AbstractController
{
    /**
     * Plugins page
     */
    #[Route(
        name: 'plugins',
        pattern: '/plugins',
        methods: ['GET']
    )]
    public function showPlugins(Response $response, Release $release): Response
    {
        $plugins = $this->plugins;

        $plugins_list = $plugins->getActiveModules();
        $disabled_plugins = $plugins->getDisabledModules();

        $plugins_updates = [];
        $plugins_releases_status = [];
        try {
            $plugins_updates = $release->getPluginsUpdates();
            $plugins_releases_status = $release->getPluginsReleasesStatus();
        } catch (Throwable $e) {
            Logs::exception($e, 'Error looking for new plugins releases', Analog::ERROR);
        }

        // display page
        $this->view->render(
            $response,
            'pages/plugins.html.twig',
            [
                'page_title'            => _T("Plugins"),
                'plugins_list'          => $plugins_list,
                'plugins_disabled_list' => $disabled_plugins,
                'plugins_updates'       => $plugins_updates,
                'plugins_releases_status' => $plugins_releases_status,
                'documentation'         => 'plugins/#plugins-management-interface'
            ]
        );
        return $response;
    }

    /**
     * Plugins activation/deactivation
     */
    #[Route(
        name: 'pluginsActivation',
        pattern: '/plugins/{action:activate|deactivate}/{module_id}',
        methods: ['GET']
    )]
    public function togglePlugin(Response $response, string $action, string $module_id): Response
    {
        $error_detected = [];
        $success_detected = [];

        if (!Galette::isDemo()) {
            $plugins = $this->plugins;
            $reload_plugins = false;
            if ($action == 'activate') {
                try {
                    $plugins->activateModule($module_id);
                    $success_detected[] = sprintf(
                        _T('Plugin %1$s has been enabled'),
                        $module_id
                    );
                    $reload_plugins = true;
                } catch (Throwable $e) {
                    Logs::exception($e, 'Unable to activate plugin ' . $module_id, Analog::ERROR);
                    $error_detected[] = $e->getMessage();
                }
            } elseif ($action == 'deactivate') {
                try {
                    $plugins->deactivateModule($module_id);
                    $success_detected[] = sprintf(
                        _T('Plugin %1$s has been disabled'),
                        $module_id
                    );
                    $reload_plugins = true;
                } catch (Throwable $e) {
                    Logs::exception($e, 'Unable to deactivate plugin ' . $module_id, Analog::ERROR);
                    $error_detected[] = $e->getMessage();
                }
            }

            //If some plugins have been (de)activated, we have to reload
            if ($reload_plugins === true) {
                $plugins->loadModules($this->preferences, GALETTE_PLUGINS_PATH, $this->i18n->getLongID());
            }
        }

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('plugins'),
            successes: $success_detected,
            errors: $error_detected
        );
    }

    /**
     * Plugins database activation
     *
     * @param string $id Plugin id
     */
    #[Route(
        name: 'pluginInitDb',
        pattern: '/plugins/initialize-database/{id}',
        methods: ['GET', 'POST']
    )]
    public function initPluginDb(Request $request, Response $response, string $id): Response
    {
        if (Galette::isDemo()) {
            Analog::log(
                'Trying to access plugin database initialization in DEMO mode.',
                Analog::WARNING
            );
            throw new HttpForbiddenException($request);
        }

        $params = [];
        $error_detected = [];

        $plugid = $id;
        if (!$this->plugins->moduleExists($plugid)) {
            Analog::log(
                'Unable to load plugin `' . $plugid . '`!',
                Analog::URGENT
            );
            throw new HttpNotFoundException($request);
        }

        $plugin = $this->plugins->getModule($plugid);

        // Reject plugins that cannot be installed or updated due to missing files
        // or incompatibility. Only plugins that are installable/upgradable should
        // reach the database initialization step.
        if (
            $this->plugins->isDisabled($plugid)
            && !in_array($this->plugins->getDisabledCause($plugid), [Plugins::DISABLED_NOT_INSTALLED, Plugins::DISABLED_NOT_UP2DATE], strict: true)
        ) {
            Analog::log(
                'Plugin `' . $plugid . '` is disabled and cannot be initialized (reason: '
                . $this->plugins->getDisabledCause($plugid) . ').',
                Analog::WARNING
            );

            throw new HttpNotFoundException($request);
        }

        // If available, ensure the plugin actually uses a database before offering
        // database initialization.
        if (!$this->plugins->needsDatabase($plugid)) {
            Analog::log(
                'Database initialization requested for plugin `' . $plugid
                . '` that does not require a database.',
                Analog::WARNING
            );

            throw new HttpBadRequestException($request);
        }

        $install = (new PluginInstall())->prepareFor($this->plugins, $plugid, $error_detected);
        //an active plugin is up to date: a new installation would erase its data
        $reinstall = !$this->plugins->isDisabled($plugid);

        $step = 'confirm';
        $post = $request->getParsedBody();
        if ($request->getMethod() === 'POST' && isset($post['confirm'])) {
            if ($reinstall && !isset($post['confirm_reinstall'])) {
                $error_detected[] = _T("Please confirm you want to erase existing data.");
            } else {
                $db_checks = $install->checkDbPermissions($this->zdb);
                if (!$db_checks['ok']) {
                    $step = 'perms';
                    $params['db_checks'] = $db_checks;
                    if (!$db_checks['supported']) {
                        $params['unsupported_message'] = $this->zdb->getUnsupportedMessage();
                    }
                } else {
                    $step = 'done';
                    $params['success'] = $install->run($this->zdb, $this->plugins, $plugid);
                    $params['report'] = $install->getDbInstallReport();
                }
            }
        }

        $params += [
            'page_title'    => sprintf(_T('Plugin database initialization: %1$s'), $plugin['name']),
            'step'          => $step,
            'reinstall'     => $reinstall,
            'installed_version' => $install->isUpgrade() ? $this->plugins->getInstalledDbVersion($plugid) : null,
            'plugid'        => $plugid,
            'plugin'        => $plugin,
            'mode'          => (($this->isAjax($request)) ? 'ajax' : ''),
            'error_detected' => $error_detected,
            'install' => $install,
        ];

        // display page
        $this->view->render(
            $response,
            'modals/plugin_initdb.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Plugin info page
     *
     * @param string $route Plugin route identifier
     */
    #[Route(
        name: 'pluginInfo',
        pattern: '/plugins/{route}',
        methods: ['GET']
    )]
    public function pluginInfo(Request $request, Response $response, Release $release, string $route): Response
    {
        $module = null;
        $module_id = null;
        foreach ($this->plugins->getActiveModules() as $id => $mod) {
            if ($mod['route'] === $route) {
                $module = $mod;
                $module_id = $id;
                break;
            }
        }

        if ($module === null) {
            throw new HttpNotFoundException($request);
        }

        $params = [
            'page_title' => $module['name'],
            'name'       => $module['name'],
            'version'    => $module['version'],
            'date'       => $module['date'],
            'author'     => $module['author'],
        ];
        if ($this->login->isAdmin()) {
            $params['module'] = $module;
            try {
                $params['release_update'] = $release->getPluginsUpdates()[$module_id] ?? null;
                $params['release_status'] = $release->getPluginsReleasesStatus()[$module_id] ?? null;
            } catch (Throwable $e) {
                Logs::exception($e, 'Error looking for new plugin release', Analog::ERROR);
            }
        }

        $this->view->render(
            $response,
            'pages/plugin_info.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Serve a plugin static resource (CSS, JS, image, font, ...).
     *
     * @param string $plugin Plugin identifier
     * @param string $path   Path of the resource within the plugin
     */
    #[Route(
        name: 'plugin_res',
        pattern: '/plugins/{plugin}/res/{path:.*}',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function resource(Request $request, Response $response, string $plugin, string $path): Response
    {
        $ext = pathinfo($path)['extension'] ?? '';
        $auth_ext = [
            'js'    => 'text/javascript',
            'css'   => 'text/css',
            'png'   => 'image/png',
            'jpg'   => 'image/jpg',
            'jpeg'  => 'image/jpg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'map'   => 'application/json',
            'woff'  => 'application/font-woff',
            'woff2' => 'application/font-woff2'
        ];
        if (str_contains($path, '../') || !isset($auth_ext[$ext])) {
            Analog::log(
                sprintf('Invalid extension %1$s (%2$s)!', $ext, $path),
                Analog::WARNING
            );
            throw new HttpNotFoundException($request);
        }

        try {
            $file = $this->plugins->getFile($plugin, $path);
        } catch (Throwable $e) {
            Analog::log(
                sprintf(
                    'Unable to serve resource `%1$s` from plugin `%2$s`: %3$s',
                    $path,
                    $plugin,
                    $e->getMessage()
                ),
                Analog::WARNING
            );
            throw new HttpNotFoundException($request, previous: $e);
        }

        $response = $response->withHeader('Content-type', $auth_ext[$ext]);
        $response->getBody()->write(file_get_contents($file));
        return $response;
    }
}
