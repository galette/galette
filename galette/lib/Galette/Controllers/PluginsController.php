<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Throwable;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\Galette;
use Galette\Core\Install;
use Galette\Core\PluginInstall;
use Laminas\Db\Adapter\Adapter;
use Analog\Analog;

/**
 * Galette plugins controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginsController extends AbstractController
{
    /**
     * Plugins page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function showPlugins(Request $request, Response $response): Response
    {
        $plugins = $this->plugins;

        $plugins_list = $plugins->getModules();
        $disabled_plugins = $plugins->getDisabledModules();

        // display page
        $this->view->render(
            $response,
            'pages/plugins.html.twig',
            array(
                'page_title'            => _T("Plugins"),
                'plugins_list'          => $plugins_list,
                'plugins_disabled_list' => $disabled_plugins
            )
        );
        return $response;
    }

    /**
     * Plugins activation/desactivaion
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param string   $action    Action
     * @param string   $module_id Module id
     *
     * @return Response
     */
    public function togglePlugin(Request $request, Response $response, string $action, string $module_id): Response
    {
        if (!Galette::isDemo()) {
            $plugins = $this->plugins;
            $reload_plugins = false;
            if ($action == 'activate') {
                try {
                    $plugins->activateModule($module_id);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $module_id,
                            _T("Plugin %name has been enabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (Throwable $e) {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
            } elseif ($action == 'deactivate') {
                try {
                    $plugins->deactivateModule($module_id);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $module_id,
                            _T("Plugin %name has been disabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (Throwable $e) {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
            }

            //If some plugins have been (de)activated, we have to reload
            if ($reload_plugins === true) {
                $plugins->loadModules($this->preferences, GALETTE_PLUGINS_PATH, $this->i18n->getLongID());
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('plugins'));
    }

    /**
     * Plugins database activation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $id       Plugin id
     *
     * @return Response
     */
    public function initPluginDb(Request $request, Response $response, string $id): Response
    {
        if (Galette::isDemo()) {
            Analog::log(
                'Trying to access plugin database initialization in DEMO mode.',
                Analog::WARNING
            );
            return $response->withStatus(403);
        }

        $params = [];
        $error_detected = [];

        $plugid = $id;
        if (!$this->plugins->moduleExists($plugid)) {
            Analog::log(
                'Unable to load plugin `' . $plugid . '`!',
                Analog::URGENT
            );
            return $response->withStatus(404);
        }

        $plugin = $this->plugins->getModules($plugid);

        $install = null;
        $mdplugin = md5($plugin['root']);
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
            if ($post['install_type'] === PluginInstall::INSTALL) {
                $istep = 4;
            } else {
                $istep = 3;
            }
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
                $install_plugin = true; //not used here, but from include
                $zdb = $this->zdb;
                ob_start();
                include_once GALETTE_ROOT . '/install/steps/db_checks.php';
                $params['results'] = ob_get_contents();
                ob_end_clean();
                break;
            case 'u3':
                $update_scripts = Install::getUpdateScripts($plugin['root'], TYPE_DB);
                $params['update_scripts'] = $update_scripts;
                break;
            case 'i4':
            case 'u4':
                $install->setDbType(TYPE_DB, $error_detected);
                $install->setTablesPrefix(PREFIX_DB);
                $install->setInstalledVersion($post['previous_version'] ?? null);
                $install->executeScripts($this->zdb, $plugin['root']);
                break;
        }

        $this->session->$mdplugin = $install;

        $params += [
            'page_title'    => $install->getStepTitle(),
            'step'          => $step,
            'istep'         => $istep,
            'plugid'        => $plugid,
            'plugin'        => $plugin,
            'mode'          => (($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : ''),
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
}
