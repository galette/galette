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
use Galette\Core\Install;
use Galette\Core\PluginInstall;
use Analog\Analog;

use function Safe\file_get_contents;
use function Safe\ob_end_clean;
use function Safe\ob_start;

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
    public function showPlugins(Response $response): Response
    {
        $plugins = $this->plugins;

        $plugins_list = $plugins->getActiveModules();
        $disabled_plugins = $plugins->getDisabledModules();

        // display page
        $this->view->render(
            $response,
            'pages/plugins.html.twig',
            [
                'page_title'            => _T("Plugins"),
                'plugins_list'          => $plugins_list,
                'plugins_disabled_list' => $disabled_plugins,
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
            && !in_array($this->plugins->getDisabledCause($plugid), [Plugins::DISABLED_NOT_INSTALLED, Plugins::DISABLED_NOT_UP2DATE], true)
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

        $mdplugin = md5((string)$plugin['root']);
        if (
            isset($this->session->$mdplugin)
            && !isset($_GET['raz'])
        ) {
            $install = $this->session->$mdplugin;
        } else {
            $install = new PluginInstall();
        }
        $install->reinitReport();

        $post = $request->getParsedBody();

        if (isset($post['stepback_btn'])) {
            $install->atPreviousStep();
        } elseif (isset($post['install_prefs_ok'])) {
            $install->atEndStep();
        } elseif (isset($post['install_dbwrite_ok'])) {
            $install->atEndStep();
        } elseif (isset($post['previous_version'])) {
            $install->setInstalledVersion($post['previous_version']);
            $install->atDbUpgradeStep();
        } elseif (isset($post['install_dbperms_ok'])) {
            if ($install->isInstall()) {
                $install->atDbInstallStep();
            } elseif ($install->isUpgrade()) {
                $install->atVersionSelection();
            }
        } elseif (isset($post['install_type'])) {
            $install->setMode($post['install_type']);
            $install->atDbStep();
        }

        $step = 1;
        $istep = 1;

        if (isset($post['install_type'])) {
            $params['install_type'] = $post['install_type'];
            $istep = 2;
        }

        if (isset($post['install_dbperms_ok'])) {
            $istep = $post['install_type'] === PluginInstall::INSTALL ? 4 : 3;
        }

        if (isset($post['previous_version'])) {
            $istep = 4;
        }

        if (isset($post['install_dbwrite_ok'])) {
            $istep = 5;
        }

        if (isset($post['install_type'])) {
            if ($post['install_type'] == PluginInstall::INSTALL) {
                $step = 'i' . $istep;
            } elseif ($istep > 1 && $post['install_type'] == PluginInstall::UPDATE) {
                $step = 'u' . $istep;
            }
        }

        switch ($step) {
            case '1':
                //let's look for updates scripts
                $update_scripts = $install::getUpdateScripts($plugin['root'], TYPE_DB);
                if (count($update_scripts) > 0) {
                    $params['update_scripts'] = $update_scripts;
                }
                break;
            case 'i2':
            case 'u2':
                $install_plugin = true; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but from include
                $zdb = $this->zdb; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but from include
                ob_start();
                include_once GALETTE_ROOT . '/install/steps/db_checks.php';
                $params['results'] = ob_get_contents();
                ob_end_clean();
                if (!($conndb_ok === true && $supported_db === true && $permsdb_ok === true)) {
                    break;
                }

                if ($install->isInstall()) {
                    $install->atDbInstallStep();
                } elseif ($install->isUpgrade()) {
                    $install->atVersionSelection();
                }

                $istep = $post['install_type'] === PluginInstall::INSTALL ? 4 : 3;
                if ($post['install_type'] == PluginInstall::INSTALL) {
                    $step = 'i' . $istep;
                } elseif ($post['install_type'] == PluginInstall::UPDATE) {
                    $step = 'u' . $istep;
                }
                $post['install_dbperms_ok'] = 1;
                //nobreak when permissions are ok, we can go to next step
            case 'u3':
                if ($step == 'u3') {
                    $update_scripts = Install::getUpdateScripts($plugin['root'], TYPE_DB);
                    $params['update_scripts'] = $update_scripts;
                    break;
                }
            case 'i4':
            case 'u4':
                if ($install->setDbType(TYPE_DB, $error_detected)
                    ->setTablesPrefix(PREFIX_DB)
                    ->setInstalledVersion($post['previous_version'] ?? null)
                    ->executeScripts($this->zdb, $plugin['root'])
                ) {
                    $install->atEndStep();
                    $istep = 5;
                    if ($post['install_type'] == PluginInstall::INSTALL) {
                        $step = 'i' . $istep;
                    } elseif ($post['install_type'] == PluginInstall::UPDATE) {
                        $step = 'u' . $istep;
                    }
                    $post['install_dbwrite_ok'] = 1;
                } else{
                    break;
                }
            case 'i5':
            case 'u5':
                $install->setPluginInstalled($this->zdb, $this->plugins, $plugid);
        }

        if ($step !== 'i5' && $step !== 'u5') {
            $this->session->$mdplugin = $install;
        } else {
            unset($this->session->$mdplugin);
        }

        $params += [
            'page_title'    => $install->getStepDetail('title'),
            'step'          => $step,
            'istep'         => $istep,
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
    public function pluginInfo(Request $request, Response $response, string $route): Response
    {
        $module = null;
        foreach ($this->plugins->getActiveModules() as $mod) {
            if ($mod['route'] === $route) {
                $module = $mod;
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
