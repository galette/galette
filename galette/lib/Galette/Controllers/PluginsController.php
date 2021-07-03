<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette plugins controller
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

namespace Galette\Controllers;

use Throwable;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\Install;
use Galette\Core\PluginInstall;
use Laminas\Db\Adapter\Adapter;
use Analog\Analog;

/**
 * Galette plugins controller
 *
 * @category  Controllers
 * @name      PluginsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
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
            'plugins.tpl',
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
        if (GALETTE_MODE !== 'DEMO') {
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
            ->withHeader('Location', $this->router->pathFor('plugins'));
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
        if (GALETTE_MODE === 'DEMO') {
            Analog::log(
                'Trying to access plugin database initialization in DEMO mode.',
                Analog::WARNING
            );
            return $response->withStatus(403);
        }

        $params = [];
        $warning_detected = [];
        $error_detected = [];

        $plugid = $id;
        $plugin = $this->plugins->getModules($plugid);

        if ($plugin === null) {
            Analog::log(
                'Unable to load plugin `' . $plugid . '`!',
                Analog::URGENT
            );
            $notFound = $this->notFoundHandler;
            return $notFound($request, $response);
        }

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
                if (!defined('GALETTE_THEME_DIR')) {
                    define('GALETTE_THEME_DIR', './themes/default/');
                }

                $install_plugin = true;
                //not used here, but from include
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
                $messages = [];

                // begin : copyright (2002) the phpbb group (support@phpbb.com)
                // load in the sql parser
                include GALETTE_ROOT . 'includes/sql_parse.php';
                if ($step == 'u4') {
                    $update_scripts = Install::getUpdateScripts(
                        $plugin['root'],
                        TYPE_DB,
                        $post['previous_version']
                    );
                } else {
                    $update_scripts['current'] = TYPE_DB . '.sql';
                }

                $sql_query = '';
                foreach ($update_scripts as $key => $val) {
                    $sql_query .= @fread(
                        @fopen($plugin['root'] . '/scripts/' . $val, 'r'),
                        @filesize($plugin['root'] . '/scripts/' . $val)
                    );
                    $sql_query .= "\n";
                }

                $sql_query = preg_replace('/galette_/', PREFIX_DB, $sql_query);
                $sql_query = remove_remarks($sql_query);

                $sql_query = split_sql_file($sql_query, ';');

                $sql_size = sizeof($sql_query);
                for ($i = 0; $i < $sql_size; $i++) {
                    $query = trim($sql_query[$i]);
                    if ($query != '' && $query[0] != '-') {
                        //some output infos
                        @list($w1, $w2, $w3, $extra) = array_pad(explode(' ', $query, 4), 4, '');
                        if ($extra != '') {
                            $extra = '...';
                        }
                        try {
                            $this->zdb->db->query(
                                $query,
                                Adapter::QUERY_MODE_EXECUTE
                            );
                            $messages['success'][] = $w1 . ' ' . $w2 . ' ' . $w3 .
                                ' ' . $extra;
                        } catch (Throwable $e) {
                            Analog::log(
                                'Error executing query | ' . $e->getMessage() .
                                ' | Query was: ' . $query,
                                Analog::WARNING
                            );
                            if (
                                (strcasecmp(trim($w1), 'drop') != 0)
                                && (strcasecmp(trim($w1), 'rename') != 0)
                            ) {
                                $error_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                                $error_detected[] = $e->getMessage() . '<br/>(' . $query . ')';
                            } else {
                                //if error are on drop, DROP, rename or RENAME we can continue
                                $warning_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                                $warning_detected[] = $e->getMessage() . '<br/>(' . $query . ')';
                            }
                        }
                    }
                }
                break;
        }

        $this->session->$mdplugin = $install;

        $params += [
            'page_title'    => $install->getStepTitle(),
            'step'          => $step,
            'istep'         => $istep,
            'plugid'        => $plugid,
            'plugin'        => $plugin,
            'mode'          => ($request->isXhr() ? 'ajax' : ''),
            'error_detected' => $error_detected
        ];

        // display page
        $this->view->render(
            $response,
            'plugin_initdb.tpl',
            $params
        );
        return $response;
    }
}
