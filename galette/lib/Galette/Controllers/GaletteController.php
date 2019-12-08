<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette main controller
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\SysInfos;
use Galette\Core\GaletteMail;
use Galette\IO\Charts;
use Galette\Core\PluginInstall;
use Analog\Analog;

/**
 * Galette main controller
 *
 * @category  Controllers
 * @name      GaletteController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

class GaletteController extends AbstractController
{
    /**
     * Main route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function slash(Request $request, Response $response, array $args = []) :Response
    {
        return $this->galetteRedirect($request, $response, $args);
    }

    /**
     * Logo route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param Logo     $logo     Logo instance
     *
     * @return Response
     */
    public function logo(Request $request, Response $response, \galette\Core\Logo $logo) :Response
    {
        return $logo->display($response);
    }

    /**
     * Print logo route
     *
     * @param Request   $request  PSR Request
     * @param Response  $response PSR Response
     * @param PrintLogo $logo     Print logo instance
     *
     * @return Response
     */
    public function printLogo(Request $request, Response $response, \Galette\Core\PrintLogo $logo) :Response
    {
        return $logo->display($response);
    }

    /**
     * System informations
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param Plugins  $plugins  Plugins instance
     *
     * @return Response
     */
    public function sysInfos(Request $request, Response $response, \Galette\Core\Plugins $plugins) :Response
    {
        $sysinfos = new SysInfos();
        $sysinfos->grab();

        // display page
        $this->view->render(
            $response,
            'sysinfos.tpl',
            array(
                'page_title'    => _T("System informations"),
                'rawinfos'      => $sysinfos->getRawData($this->plugins)
            )
        );
        return $response;
    }

    /**
     * Lost password page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function lostpasswd(Request $request, Response $response) :Response
    {
        if ($this->preferences->pref_mail_method === GaletteMail::METHOD_DISABLED) {
            throw new \RuntimeException('Mailing disabled.');
        }
        // display page
        $this->view->render(
            $response,
            'lostpasswd.tpl',
            array(
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }

    /**
     * Dashboard page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function dashboard(Request $request, Response $response) :Response
    {
        $news = new \Galette\IO\News($this->preferences->pref_rss_url);

        $params = [
            'page_title'        => _T("Dashboard"),
            'contentcls'        => 'desktop',
            'news'              => $news->getPosts(),
            'show_dashboard'    => $_COOKIE['show_galette_dashboard'],
            'require_cookie'    => true,
            'require_dialog'    => true,
        ];

        $hide_telemetry = true;
        if ($this->login->isAdmin()) {
            $telemetry = new \Galette\Util\Telemetry(
                $this->zdb,
                $this->preferences,
                $this->plugins
            );
            $params['reguuid'] = $telemetry->getRegistrationUuid();
            $params['telemetry_sent'] = $telemetry->isSent();
            $params['registered'] = $telemetry->isRegistered();

            $hide_telemetry = $telemetry->isSent() && $telemetry->isRegistered()
                || $_COOKIE['hide_galette_telemetry'];
        }
        $params['hide_telemetry'] = $hide_telemetry;

        // display page
        $this->view->render(
            $response,
            'desktop.tpl',
            $params
        );
        return $response;
    }

    /**
     * Preferences page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function preferences(Request $request, Response $response) :Response
    {
        // flagging required fields
        $required = array(
            'pref_nom'              => 1,
            'pref_lang'             => 1,
            'pref_numrows'          => 1,
            'pref_log'              => 1,
            'pref_statut'           => 1,
            'pref_etiq_marges_v'    => 1,
            'pref_etiq_marges_h'    => 1,
            'pref_etiq_hspace'      => 1,
            'pref_etiq_vspace'      => 1,
            'pref_etiq_hsize'       => 1,
            'pref_etiq_vsize'       => 1,
            'pref_etiq_cols'        => 1,
            'pref_etiq_rows'        => 1,
            'pref_etiq_corps'       => 1,
            'pref_card_abrev'       => 1,
            'pref_card_strip'       => 1,
            'pref_card_marges_v'    => 1,
            'pref_card_marges_h'    => 1,
            'pref_card_hspace'      => 1,
            'pref_card_vspace'      => 1
        );

        if ($this->login->isSuperAdmin() && GALETTE_MODE !== 'DEMO') {
            $required['pref_admin_login'] = 1;
        }

        if ($this->session->entered_preferences) {
            $pref = $this->session->entered_preferences;
            $this->session->entered_preferences = null;
        } else {
            $prefs_fields = $this->preferences->getFieldsNames();
            // collect data
            foreach ($prefs_fields as $fieldname) {
                $pref[$fieldname] = $this->preferences->$fieldname;
            }
        }

        //List available themes
        $themes = array();
        $d = dir(GALETTE_THEMES_PATH);
        while (($entry = $d->read()) !== false) {
            $full_entry = GALETTE_THEMES_PATH . $entry;
            if ($entry != '.'
                && $entry != '..'
                && is_dir($full_entry)
                && file_exists($full_entry.'/page.tpl')
            ) {
                $themes[] = $entry;
            }
        }
        $d->close();

        $m = new Members();
        $s = new Status($this->zdb);

        // display page
        $this->view->render(
            $response,
            'preferences.tpl',
            array(
                'page_title'            => _T("Settings"),
                'staff_members'         => $m->getStaffMembersList(true),
                'time'                  => time(),
                'pref'                  => $pref,
                'pref_numrows_options'  => array(
                    10 => '10',
                    20 => '20',
                    50 => '50',
                    100 => '100'
                ),
                'print_logo'            => $this->print_logo,
                'required'              => $required,
                'themes'                => $themes,
                'statuts'               => $s->getList(),
                'require_tabs'          => true,
                'color_picker'          => true,
                'require_dialog'        => true,
                'accounts_options'      => array(
                    Members::ALL_ACCOUNTS       => _T("All accounts"),
                    Members::ACTIVE_ACCOUNT     => _T("Active accounts"),
                    Members::INACTIVE_ACCOUNT   => _T("Inactive accounts")
                )
            )
        );
        return $response;
    }

    /**
     * Store preferences
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storePreferences(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];
        $warning_detected = [];

        // Validation
        if (isset($post['valid']) && $post['valid'] == '1') {
            if ($this->preferences->check($post, $this->login)) {
                if (!$this->preferences->store()) {
                    $error_detected[] = _T("An SQL error has occurred while storing preferences. Please try again, and contact the administrator if the problem persists.");
                } else {
                    $this->flash->addMessage(
                        'success_detected',
                        _T("Preferences has been saved.")
                    );
                }
                $warning_detected = array_merge($warning_detected, $this->preferences->checkCardsSizes());

                // picture upload
                if (GALETTE_MODE !== 'DEMO' &&  isset($_FILES['logo'])) {
                    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['logo']['tmp_name'] !='') {
                            if (is_uploaded_file($_FILES['logo']['tmp_name'])) {
                                $res = $this->logo->store($_FILES['logo']);
                                if ($res < 0) {
                                    $error_detected[] = $this->logo->getErrorMessage($res);
                                } else {
                                    $this->logo = new Logo();
                                }
                            }
                        }
                    } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        Analog::log(
                            $this->logo->getPhpErrorMessage($_FILES['logo']['error']),
                            Analog::WARNING
                        );
                        $error_detected[] = $this->logo->getPhpErrorMessage(
                            $_FILES['logo']['error']
                        );
                    }
                }

                if (GALETTE_MODE !== 'DEMO' && isset($post['del_logo'])) {
                    if (!$this->logo->delete()) {
                        $error_detected[] = _T("Delete failed");
                    } else {
                        $this->logo = new Logo(); //get default Logo
                    }
                }

                // Card logo upload
                if (GALETTE_MODE !== 'DEMO' && isset($_FILES['card_logo'])) {
                    if ($_FILES['card_logo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['card_logo']['tmp_name'] !='') {
                            if (is_uploaded_file($_FILES['card_logo']['tmp_name'])) {
                                $res = $this->print_logo->store($_FILES['card_logo']);
                                if ($res < 0) {
                                    $error_detected[] = $this->print_logo->getErrorMessage($res);
                                } else {
                                    $this->print_logo = new PrintLogo();
                                }
                            }
                        }
                    } elseif ($_FILES['card_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        Analog::log(
                            $this->print_logo->getPhpErrorMessage($_FILES['card_logo']['error']),
                            Analog::WARNING
                        );
                        $error_detected[] = $this->print_logo->getPhpErrorMessage(
                            $_FILES['card_logo']['error']
                        );
                    }
                }

                if (GALETTE_MODE !== 'DEMO' && isset($post['del_card_logo'])) {
                    if (!$this->print_logo->delete()) {
                        $error_detected[] = _T("Delete failed");
                    } else {
                        $this->print_logo = new PrintLogo();
                    }
                }
            } else {
                $error_detected = $this->preferences->getErrors();
            }

            if (count($error_detected) > 0) {
                $this->session->entered_preferences = $post;
                //report errors
                foreach ($error_detected as $error) {
                    $this->flash->addMessage(
                        'error_detected',
                        $error
                    );
                }
            }

            if (count($warning_detected) > 0) {
                //report warnings
                foreach ($warning_detected as $warning) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning
                    );
                }
            }

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('preferences'));
        }
    }

    /**
     * Test mlail parameters
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function testMail(Request $request, Response $response) :Response
    {
        $sent = false;
        if (!$this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
            $this->flash->addMessage(
                'error_detected',
                _T("You asked Galette to send a test email, but mail has been disabled in the preferences.")
            );
        } else {
            $get = $request->getQueryParams();
            $dest = (isset($get['adress']) ? $get['adress'] : $this->preferences->pref_email_newadh);
            if (GaletteMail::isValidEmail($dest)) {
                $mail = new GaletteMail($this->preferences);
                $mail->setSubject(_T('Test message'));
                $mail->setRecipients(
                    array(
                        $dest => _T("Galette admin")
                    )
                );
                $mail->setMessage(_T('Test message.'));
                $sent = $mail->send();

                if ($sent) {
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%email',
                            $dest,
                            _T("An email has been sent to %email")
                        )
                    );
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        str_replace(
                            '%email',
                            $dest,
                            _T("No email sent to %email")
                        )
                    );
                }
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Invalid email adress!")
                );
            }
        }

        if (!$request->isXhr()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('preferences'));
        } else {
            return $response->withJson(
                [
                    'sent'  => $sent
                ]
            );
        }
    }

    /**
     * Charts page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function charts(Request $request, Response $response) :Response
    {
        $charts = new Charts(
            array(
                Charts::MEMBERS_STATUS_PIE,
                Charts::MEMBERS_STATEDUE_PIE,
                Charts::CONTRIBS_TYPES_PIE,
                Charts::COMPANIES_OR_NOT,
                Charts::CONTRIBS_ALLTIME
            )
        );

        // display page
        $this->view->render(
            $response,
            'charts.tpl',
            array(
                'page_title'        => _T("Charts"),
                'charts'            => $charts->getCharts(),
                'require_charts'    => true
            )
        );
        return $response;
    }

    /**
     * Plugins page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function plugins(Request $request, Response $response) :Response
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
                'plugins_disabled_list' => $disabled_plugins,
                'require_dialog'        => true
            )
        );
        return $response;
    }

    /**
     * Plugins activation/desactivaion
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function togglePlugin(Request $request, Response $response, array $args = []) :Response
    {
        if (GALETTE_MODE !== 'DEMO') {
            $plugins = $this->plugins;
            $action = $args['action'];
            $reload_plugins = false;
            if ($action == 'activate') {
                try {
                    $plugins->activateModule($args['module_id']);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $args['module_id'],
                            _T("Plugin %name has been enabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (\Exception $e) {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
            } elseif ($args['action'] == 'deactivate') {
                try {
                    $plugins->deactivateModule($args['module_id']);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $args['module_id'],
                            _T("Plugin %name has been disabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (\Exception $e) {
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
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function initPluginDb(Request $request, Response $response, array $args = []) :Response
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

        $plugid = $args['id'];
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
        if (isset($this->session->$mdplugin)
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
        } elseif (isset($_POST['previous_version'])) {
            $install->setInstalledVersion($_POST['previous_version']);
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

        if ($post['install_type'] == PluginInstall::INSTALL) {
            $step = 'i' . $istep;
        } elseif ($istep > 1 && $post['install_type'] == PluginInstall::UPDATE) {
            $step = 'u' . $istep;
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
                include_once __DIR__ . '/../../install/steps/db_checks.php';
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
                        $_POST['previous_version']
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

                for ($i = 0; $i < sizeof($sql_query); $i++) {
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
                        } catch (Exception $e) {
                            Analog::log(
                                'Error executing query | ' . $e->getMessage() .
                                ' | Query was: ' . $query,
                                Analog::WARNING
                            );
                            if ((strcasecmp(trim($w1), 'drop') != 0)
                                && (strcasecmp(trim($w1), 'rename') != 0)
                            ) {
                                $error_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                                $error_detected[] = $e->getMessage() . '<br/>(' . $query  . ')';
                            } else {
                                //if error are on drop, DROP, rename or RENAME we can continue
                                $warning_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                                $warning_detected[] = $e->getMessage() . '<br/>(' . $query  . ')';
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

    /**
     * Dynamic fields translations
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function dynamicTranslations(Request $request, Response $response, array $args = []) :Response
    {
        $text_orig = '';
        if (isset($args['text_orig'])) {
            $text_orig = $args['text_orig'];
        } elseif (isset($_GET['text_orig'])) {
            $text_orig = $_GET['text_orig'];
        }

        $params = [
            'page_title'    => _T("Translate labels")
        ];

        $nb_fields = 0;
        try {
            $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
            $select->columns(
                array('nb' => new Zend\Db\Sql\Expression('COUNT(text_orig)'))
            );
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $nb_fields = $result->nb;
        } catch (Exception $e) {
            Analog::log(
                'An error occurred counting l10n entries | ' .
                $e->getMessage(),
                Analog::WARNING
            );
        }

        if (is_numeric($nb_fields) && $nb_fields > 0) {
            try {
                $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
                $select->quantifier('DISTINCT')->columns(
                    array('text_orig')
                )->order('text_orig');

                $all_texts = $this->zdb->execute($select);

                $orig = array();
                foreach ($all_texts as $idx => $row) {
                    $orig[] = $row->text_orig;
                }
                $exists = true;
                if ($text_orig == '') {
                    $text_orig = $orig[0];
                } elseif (!in_array($text_orig, $orig)) {
                    $exists = false;
                    $this->flash->addMessage(
                        'error_detected',
                        str_replace(
                            '%s',
                            $text_orig,
                            _T("No translation for '%s'!<br/>Please fill and submit above form to create it.")
                        )
                    );
                }

                $trans = array();
                /**
                * FIXME : it would be faster to get all translations at once
                * for a specific string
                */
                foreach ($this->i18n->getList() as $l) {
                    $text_trans = getDynamicTranslation($text_orig, $l->getLongID());
                    $lang_name = $l->getName();
                    $trans[] = array(
                        'key'  => $l->getLongID(),
                        'name' => ucwords($lang_name),
                        'text' => $text_trans
                    );
                }

                $params['exists'] = $exists;
                $params['orig'] = $orig;
                $params['trans'] = $trans;
            } catch (\Exception $e) {
                Analog::log(
                    'An error occurred retrieving l10n entries | ' .
                    $e->getMessage(),
                    Analog::WARNING
                );
            }
        }

        $params['text_orig'] = $text_orig;

        // display page
        $this->view->render(
            $response,
            'traduire_libelles.tpl',
            $params
        );
        return $response;
    }

    /**
     * Do dynamic fields translations
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doDynamicTranslations(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();
        $error_detected = false;

        if (isset($post['trans']) && isset($post['text_orig'])) {
            if (isset($_POST['new']) && $_POST['new'] == 'true') {
                //create translation if it does not exists yet
                $res = addDynamicTranslation(
                    $post['text_orig']
                );
            }

            // Validate form
            foreach ($post as $key => $value) {
                if (substr($key, 0, 11) == 'text_trans_') {
                    $trans_lang = substr($key, 11);
                    $trans_lang = str_replace('_utf8', '.utf8', $trans_lang);
                    $res = updateDynamicTranslation(
                        $post['text_orig'],
                        $trans_lang,
                        $value
                    );
                    if ($res !== true) {
                        $error_detected = true;
                        $this->flash->addMessage(
                            'error_detected',
                            preg_replace(
                                array(
                                    '/%label/',
                                    '/%lang/'
                                ),
                                array(
                                    $post['text_orig'],
                                    $trans_lang
                                ),
                                _T("An error occurred saving label `%label` for language `%lang`")
                            )
                        );
                    }
                }
            }

            if ($error_detected === false) {
                $this->flash->addMessage(
                    'success_detected',
                    _T("Labels has been sucessfully translated!")
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor(
                'dynamicTranslations',
                ['text_orig' => $post['text_orig']]
            ));
    }

    /**
     * Coe fields configuration page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function configureCoreFields(Request $request, Response $response) :Response
    {
        $fc = $this->fields_config;

        $params = [
            'page_title'            => _T("Fields configuration"),
            'time'                  => time(),
            'categories'            => FieldsCategories::getList($this->zdb),
            'categorized_fields'    => $fc->getCategorizedFields(),
            'non_required'          => $fc->getNonRequired(),
            'require_dialog'        => true,
            'require_sorter'        => true
        ];

        // display page
        $this->view->render(
            $response,
            'config_fields.tpl',
            $params
        );
        return $response;
    }

    /**
     * Process core fields configuration
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storeCoreFieldsConfig(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();
        $fc = $this->fields_config;

        $pos = 0;
        $current_cat = 0;
        $res = array();
        foreach ($post['fields'] as $abs_pos => $field) {
            if ($current_cat != $post[$field . '_category']) {
                //reset position when category has changed
                $pos = 0;
                //set new current category
                $current_cat = $post[$field . '_category'];
            }

            $required = null;
            if (isset($post[$field . '_required'])) {
                $required = $post[$field . '_required'];
            } else {
                $required = false;
            }

            $res[$current_cat][] = array(
                'field_id'  =>  $field,
                'label'     =>  $post[$field . '_label'],
                'category'  =>  $post[$field . '_category'],
                'visible'   =>  $post[$field . '_visible'],
                'required'  =>  $required
            );
            $pos++;
        }
        //okay, we've got the new array, we send it to the
        //Object that will store it in the database
        $success = $fc->setFields($res);
        FieldsCategories::setCategories($this->zdb, $post['categories']);
        if ($success === true) {
            $this->flash->addMessage(
                'success_detected',
                _T("Fields configuration has been successfully stored")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("An error occurred while storing fields configuration :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('configureCoreFields'));
    }

    /**
     * Dynamic fields configuration page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function configureDynamicFields(Request $request, Response $response, array $args = []) :Response
    {
        $form_name = (isset($args['form'])) ? $args['form'] : 'adh';
        if (isset($_POST['form']) && trim($_POST['form']) != '') {
            $form_name = $_POST['form'];
        }
        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login);
        $fields_list = $fields->getList($form_name, $this->login);

        $field_type_names = DynamicField::getFieldsTypesNames();

        $params = [
            'require_tabs'      => true,
            'require_dialog'    => true,
            'fields_list'       => $fields_list,
            'form_name'         => $form_name,
            'form_title'        => DynamicField::getFormTitle($form_name),
            'page_title'        => _T("Dynamic fields configuration")
        ];

        $tpl = 'configurer_fiches.tpl';
        //Render directly template if we called from ajax,
        //render in a full page otherwise
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $tpl = 'configurer_fiche_content.tpl';
        } else {
            $all_forms = DynamicField::getFormsNames();
            $params['all_forms'] = $all_forms;
        }

        // display page
        $this->view->render(
            $response,
            $tpl,
            $params
        );
        return $response;
    }

    /**
     * Move dynamic fields
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function moveDynamicField(Request $request, Response $response, array $args = []) :Response
    {
        $field_id = (int)$args['id'];
        $form_name = $args['form'];

        $field = DynamicField::loadFieldType($this->zdb, $field_id);
        if ($field->move($args['direction'])) {
            $this->flash->addMessage(
                'success_detected',
                _T("Field has been successfully moved")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("An error occurred moving field :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('configureDynamicFields', ['form' => $form_name]));
    }

    /**
     * Dynamic fields removal confirmation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function removeDynamicField(Request $request, Response $response, array $args = []) :Response
    {
        $field = DynamicField::loadFieldType($this->zdb, (int)$args['id']);
        if ($field === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Requested field does not exists!")
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('configureDynamicFields', ['form' => $args['form']]));
        }
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('configureDynamicFields', ['form' => $args['form']])
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Dynamic field"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove dynamic field %1$s'),
                    $field->getName()
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveDynamicField',
                    ['form' => $args['form'], 'id' => $args['id']]
                ),
                'cancel_uri'    => $this->router->pathFor('configureDynamicFields', ['form' => $args['form']]),
                'data'          => $data
            )
        );
        return $response;
    }

    /**
     * Process dynamic fields removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doRemoveDynamicField(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] :
            $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            $field_id = (int)$args['id'];
            $field = DynamicField::loadFieldType($this->zdb, $field_id);
            if ($field->remove()) {
                $this->flash->addMessage(
                    'success_detected',
                    _T('Field has been successfully deleted!')
                );
                $success = true;
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occurred trying to delete field :(')
                );
                $success = false;
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $response->withJson(['success'   => $success]);
        }
    }

    /**
     * Process dynamic fields removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function editDynamicField(Request $request, Response $response, array $args = []) :Response
    {
        $action = $args['action'];

        $id_dynf = null;
        if (isset($args['id'])) {
            $id_dynf = $args['id'];
        }

        if ($action === 'edit' && $id_dynf === null) {
            throw new \RuntimeException(
                _T("Dynamic field ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $id_dynf !== null) {
             return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'editDynamicField',
                        [
                            'action'    => 'add',
                            'form'      => $arg['form']
                        ]
                    )
                );
        }

        $form_name = $args['form'];

        $df = null;
        if ($this->session->dynamicfieldtype) {
            $df = $this->session->dynamicfieldtype;
            $this->session->dynamicfieldtype = null;
        } elseif ($action === 'edit') {
            $df = DynamicField::loadFieldType($this->zdb, $id_dynf);
            if ($df === false) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Unable to retrieve field informations.")
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('configureDynamicFields'));
            }
        }

        $params = [
            'page_title'    => _T("Edit field"),
            'action'        => $action,
            'form_name'     => $form_name,
            'perm_names'    => DynamicField::getPermsNames(),
            'mode'          => ($request->isXhr() ? 'ajax' : '')
        ];

        if ($df !== null) {
            $params['df'] = $df;
        }
        if ($action === 'add') {
            $params['field_type_names'] = DynamicField::getFieldsTypesNames();
        }

        // display page
        $this->view->render(
            $response,
            'editer_champ.tpl',
            $params
        );
        return $response;
    }

    /**
     * Process dynamic fields removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doEditDynamicField(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $warning_detected = [];

        if ($args['action'] === 'add') {
            $df = DynamicField::getFieldType($this->zdb, $post['field_type']);
        } else {
            $field_id = (int)$args['id'];
            $df = DynamicField::loadFieldType($this->zdb, $field_id);
        }

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (\Exception $e) {
            if ($args['action'] === 'edit') {
                $msg = 'An error occurred storing dynamic field ' . $df->getId() . '.';
            } else {
                $msg = 'An error occurred adding new dynamic field.';
            }
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (GALETTE_MODE == 'DEV') {
                throw $e;
            }
            $error_detected[] = _T('An error occurred adding dynamic field :(');
        }

        //flash messages
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                _T('Dynamic field has been successfully stored!')
            );
        }

        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
        }

        //handle redirections
        if (count($error_detected) > 0) {
            //something went wrong :'(
            $this->session->dynamicfieldtype = $df;
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'editDynamicField',
                        $args
                    )
                );
        } else {
            if (!$df instanceof \Galette\DynamicFields\Separator
                && $args['action'] == 'add'
            ) {
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor(
                            'editDynamicField',
                            [
                                'action'    => 'edit',
                                'form'      => $args['form'],
                                'id'        => $df->getId()
                            ]
                        )
                    );
            }

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'configureDynamicFields',
                        ['form' => $args['form']]
                    )
                );
        }
    }

    /**
     * Process dynamic fields removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function fakeData(Request $request, Response $response) :Response
    {
        $params = [
            'page_title'            => _T('Generate fake data'),
            'number_members'        => \Galette\Util\FakeData::DEFAULT_NB_MEMBERS,
            'number_contrib'        => \Galette\Util\FakeData::DEFAULT_NB_CONTRIB,
            'number_groups'         => \Galette\Util\FakeData::DEFAULT_NB_GROUPS,
            'number_transactions'   => \Galette\Util\FakeData::DEFAULT_NB_TRANSACTIONS,
            'photos'                => \Galette\Util\FakeData::DEFAULT_PHOTOS
        ];

        // display page
        $this->view->render(
            $response,
            'fake_data.tpl',
            $params
        );
        return $response;
    }

    /**
     * Process dynamic fields removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doFakeData(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);

        $fakedata->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history,
            $this->login
        );

        $fakedata
            ->setNbMembers($post['number_members'])
            ->setNbGroups($post['number_groups'])
            ->setNbTransactions($post['number_transactions'])
            ->setMaxContribs($post['number_contrib'])
            ->setWithPhotos(isset($post['photos']));

        $fakedata->generate();

        $report = $fakedata->getReport();

        foreach ($report['success'] as $success) {
            $this->flash->addMessage(
                'success_detected',
                $success
            );
        }

        foreach ($report['errors'] as $error) {
            $this->flash->addMessage(
                'error_detected',
                $error
            );
        }

        foreach ($report['warnings'] as $warning) {
            $this->flash->addMessage(
                'warning_detected',
                $warning
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }

    /**
     * Administraiton tools page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function adminTools(Request $request, Response $response) :Response
    {
        $params = [
            'page_title'        => _T('Administration tools'),
            'require_dialog'    => true
        ];

        $cm = new Galette\Core\CheckModules();
        $modules_ok = $cm->isValid();
        if (!$modules_ok) {
            $this->flash->addMessage(
                _T("Some PHP modules are missing. Please install them or contact your support.<br/>More informations on required modules may be found in the documentation.")
            );
        }

        // display page
        $this->view->render(
            $response,
            'admintools.tpl',
            $params
        );
        return $response;
    }

    /**
     * Process Administration tools
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doAdminTools(Request $request, Response $response) :Response
    {
        $post = $request->getParsedBody();

        $error_detected = [];
        $success_detected = [];

        if (isset($post['inittexts'])) {
            //proceed mails texts reinitialization
            $texts = new Texts($this->texts_fields, $this->preferences);
            $res = $texts->installInit(false);
            if ($res === true) {
                $success_detected[] = _T("Texts has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing texts :(");
            }
        }

        if (isset($post['initfields'])) {
            //proceed fields configuration reinitialization
            $fc = $this->fields_config;
            $res = $fc->installInit();
            if ($res === true) {
                $success_detected[] = _T("Fields configuration has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing fields configuration :(");
            }
        }

        if (isset($post['initpdfmodels'])) {
            //proceed mails texts reinitialization
            $models = new PdfModels($this->zdb, $this->preferences, $this->login);
            $res = $models->installInit($this->pdfmodels_fields, false);
            if ($res === true) {
                $success_detected[] = _T("PDF models has been successfully reinitialized.");
            } else {
                $error_detected[] = _T("An error occurred reinitializing PDF models :(");
            }
        }

        if (isset($post['emptylogins'])) {
            //proceed empty logins and passwords
            //those ones cannot be null
            $members = new Members();
            $res = $members->emptylogins();
            if ($res === true) {
                $success_detected[] = str_replace(
                    '%i',
                    $members->getCount(),
                    _T("Logins and passwords has been successfully filled (%i processed).")
                );
            } else {
                $error_detected[] = _T("An error occurred filling empty logins and passwords :(");
            }
        }

        //flash messages
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage(
                    'success_detected',
                    $success
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('adminTools'));
    }

    /**
     * History page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function history(Request $request, Response $response, array $args = []) :Response
    {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if (isset($this->session->filter_history)) {
            $filters = $this->session->filter_history;
        } else {
            $filters = new HistoryList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $this->session->filter_history = $filters;

        $this->history->setFilters($filters);
        $logs = $this->history->getHistory();

        //assign pagination variables to the template and add pagination links
        $this->history->filters->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'history.tpl',
            array(
                'page_title'        => _T("Logs"),
                'logs'              => $logs,
                'history'           => $this->history,
                'require_calendar'  => true,
                'require_dialog'    => true
            )
        );
        return $response;
    }

    /**
     * History filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function historyFilter(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->filter_history !== null) {
            $filters = $this->session->filter_history;
        } else {
            $filters = new HistoryList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if ((isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                try {
                    if (isset($post['start_date_filter'])) {
                        $field = _T("start date filter");
                        $filters->start_date_filter = $post['start_date_filter'];
                    }
                    if (isset($post['end_date_filter'])) {
                        $field = _T("end date filter");
                        $filters->end_date_filter = $post['end_date_filter'];
                    }
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if (isset($post['user_filter'])) {
                $filters->user_filter = $post['user_filter'];
            }

            if (isset($post['action_filter'])) {
                $filters->action_filter = $post['action_filter'];
            }
        }

        $this->session->filter_history = $filters;

        if (count($error_detected) > 0) {
            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('history'));
    }

    /**
     * History flush rconfirmation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function confirmHistoryFlush(Request $request, Response $response, array $args = []) :Response
    {
        $data = [
            'redirect_uri'  => $this->router->pathFor('history')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => _T('Flush the logs'),
                'form_url'      => $this->router->pathFor('doFlushHistory'),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }

    /**
     * History flush
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function flushHistory(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] :
            $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            try {
                $this->history->clean();
                //reinitialize object after flush
                $this->history = new History($this->zdb, $this->login);
                $filters = new HistoryList();
                $this->session->filter_history = $filters;

                $this->flash->addMessage(
                    'success_detected',
                    _T('Logs have been flushed!')
                );
                $success = true;
            } catch (\Exception $e) {
                $this->zdb->connection->rollBack();
                Analog::log(
                    'An error occurred flushing logs | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occurred trying to flush logs :(')
                );
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $response->withJson(
                [
                    'success'   => $success
                ]
            );
        }
    }
}
