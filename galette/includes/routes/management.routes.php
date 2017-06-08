<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Management routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Core\PrintLogo;
use Galette\Core\GaletteMail;
use Galette\Core\Preferences;
use Galette\Core\Logo;
use Galette\Core\History;
use Galette\Filters\HistoryList;
use Galette\Core\MailingHistory;
use Galette\Filters\MailingsList;
use Galette\Entity\FieldsCategories;
use Galette\Entity\DynamicFields;
use Galette\DynamicFieldsTypes\DynamicFieldType;
use Galette\Repository\Members;
use Galette\IO\News;
use Galette\IO\Charts;
use \Analog\Analog;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use Galette\IO\CsvIn;
use Galette\Entity\ImportModel;
use Galette\Entity\PdfModel;
use Galette\Repository\PdfModels;
use Galette\Entity\Title;
use Galette\Repository\Titles;
use Galette\Entity\Texts;
use Galette\Core\Install;
use Zend\Db\Adapter\Adapter;
use Galette\Core\PluginInstall;

//galette's dashboard
$app->get(
    __('/dashboard', 'routes'),
    function ($request, $response, $args = []) {
        $news = new News($this->preferences->pref_rss_url);

        // display page
        $this->view->render(
            $response,
            'desktop.tpl',
            array(
                'page_title'        => _T("Dashboard"),
                'contentcls'        => 'desktop',
                'news'              => $news->getPosts(),
                'show_dashboard'    => $_COOKIE['show_galette_dashboard'],
                'require_cookie'    => true
            )
        );
        return $response;
    }
)->setName('dashboard')->add($authenticate);

//preferences page
$app->get(
    __('/preferences', 'routes'),
    function ($request, $response) {
        $print_logo = new PrintLogo();

        // flagging required fields
        $required = array(
            'pref_nom'              => 1,
            'pref_lang'             => 1,
            'pref_numrows'          => 1,
            'pref_log'              => 1,
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

        $prefs_fields = $this->preferences->getFieldsNames();

        // collect data
        foreach ($prefs_fields as $fieldname) {
            $pref[$fieldname] = $this->preferences->$fieldname;
        }

        //List available themes
        $themes = array();
        $d = dir(GALETTE_TEMPLATES_PATH);
        while (($entry = $d->read()) !== false) {
            $full_entry = GALETTE_TEMPLATES_PATH . $entry;
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
                'print_logo'            => $print_logo,
                'required'              => $required,
                'languages'             => $this->i18n->getList(),
                'themes'                => $themes,
                'require_tabs'          => true,
                'color_picker'          => true,
                'require_dialog'        => true
            )
        );
        return $response;
    }
)->setName('preferences')->add($authenticate);

//preferences procedure
$app->post(
    __('/preferences', 'routes'),
    function ($request, $response) {
        // Validation
        if (isset($_POST['valid']) && $_POST['valid'] == '1') {
            // verification de champs
            $insert_values = array();

            $prefs_fields = $this->preferences->getFieldsNames();

            // flagging required fields
            $required = array(
                'pref_nom'              => 1,
                'pref_lang'             => 1,
                'pref_numrows'          => 1,
                'pref_log'              => 1,
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

            // obtain fields
            foreach ($prefs_fields as $fieldname) {
                if (isset($_POST[$fieldname])) {
                    $value=trim($_POST[$fieldname]);
                } else {
                    $value="";
                }

                // now, check validity
                if ($value != '') {
                    switch ($fieldname) {
                        case 'pref_email':
                            if (GALETTE_MODE === 'DEMO') {
                                Analog::log(
                                    'Trying to set pref_email while in DEMO.',
                                    Analog::WARNING
                                );
                            } else {
                                if (!GaletteMail::isValidEmail($value)) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        _T("- Non-valid E-Mail address!")
                                    );
                                }
                            }
                            break;
                        case 'pref_admin_login':
                            if (GALETTE_MODE === 'DEMO') {
                                Analog::log(
                                    'Trying to set superadmin login while in DEMO.',
                                    Analog::WARNING
                                );
                            } else {
                                if (strlen($value) < 4) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        _T("- The username must be composed of at least 4 characters!")
                                    );
                                } else {
                                    //check if login is already taken
                                    if ($this->login->loginExists($value)) {
                                        $this->flash->addMessage(
                                            'error_detected',
                                            _T("- This username is already used by another member !")
                                        );
                                    }
                                }
                            }
                            break;
                        case 'pref_numrows':
                            if (!is_numeric($value) || $value < 0) {
                                $this->flash->addMessage(
                                    'error_detected',
                                    _T("- The numbers and measures have to be integers!")
                                );
                            }
                            break;
                        case 'pref_etiq_marges_h':
                        case 'pref_etiq_marges_v':
                        case 'pref_etiq_hspace':
                        case 'pref_etiq_vspace':
                        case 'pref_etiq_hsize':
                        case 'pref_etiq_vsize':
                        case 'pref_etiq_cols':
                        case 'pref_etiq_rows':
                        case 'pref_etiq_corps':
                        case 'pref_card_marges_v':
                        case 'pref_card_marges_h':
                        case 'pref_card_hspace':
                        case 'pref_card_vspace':
                            // prevent division by zero
                            if ($fieldname=='pref_numrows' && $value=='0') {
                                $value = '10';
                            }
                            if (!is_numeric($value) || $value < 0) {
                                $this->flash->addMessage(
                                    'error_detected',
                                    _T("- The numbers and measures have to be integers!")
                                );
                            }
                            break;
                        case 'pref_card_tcol':
                            // Set strip text color to white
                            if (! preg_match("/#([0-9A-F]{6})/i", $value)) {
                                $value = '#FFFFFF';
                            }
                            break;
                        case 'pref_card_scol':
                        case 'pref_card_bcol':
                        case 'pref_card_hcol':
                            // Set strip background colors to black
                            if (!preg_match("/#([0-9A-F]{6})/i", $value)) {
                                $value = '#000000';
                            }
                            break;
                        case 'pref_admin_pass':
                            if (GALETTE_MODE == 'DEMO') {
                                Analog::log(
                                    'Trying to set superadmin pass while in DEMO.',
                                    Analog::WARNING
                                );
                            } else {
                                if (strlen($value) < 4) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        _T("- The password must be of at least 4 characters!")
                                    );
                                }
                            }
                            break;
                        case 'pref_membership_ext':
                            if (!is_numeric($value) || $value < 0) {
                                $this->flash->addMessage(
                                    'error_detected',
                                    _T("- Invalid number of months of membership extension.")
                                );
                            }
                            break;
                        case 'pref_beg_membership':
                            $beg_membership = explode("/", $value);
                            if (count($beg_membership) != 2) {
                                $this->flash->addMessage(
                                    'error_detected',
                                    _T("- Invalid format of beginning of membership.")
                                );
                            } else {
                                $now = getdate();
                                if (!checkdate($beg_membership[1], $beg_membership[0], $now['year'])) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        _T("- Invalid date for beginning of membership.")
                                    );
                                }
                            }
                            break;
                    }
                }

                // fill up pref structure (after $value's modifications)
                $pref[$fieldname] = stripslashes($value);

                $insert_values[$fieldname] = $value;
            }

            // missing relations
            if (GALETTE_MODE !== 'DEMO'
                && isset($insert_values['pref_mail_method'])
            ) {
                if ($insert_values['pref_mail_method'] > GaletteMail::METHOD_DISABLED) {
                    if (!isset($insert_values['pref_email_nom'])
                        || $insert_values['pref_email_nom'] == ''
                    ) {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("- You must indicate a sender name for emails!")
                        );
                    }
                    if (!isset($insert_values['pref_email'])
                        || $insert_values['pref_email'] == ''
                    ) {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("- You must indicate an email address Galette should use to send emails!")
                        );
                    }
                    if ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP) {
                        if (!isset($insert_values['pref_mail_smtp_host'])
                            || $insert_values['pref_mail_smtp_host'] == ''
                        ) {
                            $this->flash->addMessage(
                                'error_detected',
                                _T("- You must indicate the SMTP server you want to use!")
                            );
                        }
                    }
                    if ($insert_values['pref_mail_method'] == GaletteMail::METHOD_GMAIL
                        || ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
                        && $insert_values['pref_mail_smtp_auth'])
                    ) {
                        if (!isset($insert_values['pref_mail_smtp_user'])
                            || trim($insert_values['pref_mail_smtp_user']) == ''
                        ) {
                            $this->flash->addMessage(
                                'error_detected',
                                _T("- You must provide a login for SMTP authentication.")
                            );
                        }
                        if (!isset($insert_values['pref_mail_smtp_password'])
                            || ($insert_values['pref_mail_smtp_password']) == ''
                        ) {
                            $this->flash->addMessage(
                                'error_detected',
                                _T("- You must provide a password for SMTP authentication.")
                            );
                        }
                    }
                }
            }

            if (isset($insert_values['pref_beg_membership'])
                && $insert_values['pref_beg_membership'] != ''
                && isset($insert_values['pref_membership_ext'])
                && $insert_values['pref_membership_ext'] != ''
            ) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("- Default membership extention and beginning of membership are mutually exclusive.")
                );
            }

            // missing required fields?
            foreach ($required as $key => $val) {
                if (!isset($pref[$key])) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("- Mandatory field empty.")." ".$key
                    );
                } elseif (isset($pref[$key])) {
                    if (trim($pref[$key])=='') {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("- Mandatory field empty.")." ".$key
                        );
                    }
                }
            }

            if (GALETTE_MODE !== 'DEMO') {
                // Check passwords. MD5 hash will be done into the Preferences class
                if (strcmp($insert_values['pref_admin_pass'], $_POST['pref_admin_pass_check']) != 0) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("Passwords mismatch")
                    );
                }
            }

            //postal address
            if (isset($insert_values['pref_postal_adress'])) {
                $value = $insert_values['pref_postal_adress'];
                if ($value == Preferences::POSTAL_ADDRESS_FROM_PREFS) {
                    if (isset($insert_values['pref_postal_staff_member'])) {
                        unset($insert_values['pref_postal_staff_member']);
                    }
                } elseif ($value == Preferences::POSTAL_ADDRESS_FROM_STAFF) {
                    if (!isset($value) || $value < 1) {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("You have to select a staff member")
                        );
                    }
                }
            }

            if (count($this->flash->getMessage('error_detected')) == 0) {
                // update preferences
                foreach ($insert_values as $champ=>$valeur) {
                    if ($this->login->isSuperAdmin()
                        || (!$this->login->isSuperAdmin()
                        && ($champ != 'pref_admin_pass' && $champ != 'pref_admin_login'))
                    ) {
                        if (($champ == "pref_admin_pass" && $_POST['pref_admin_pass'] !=  '')
                            || ($champ != "pref_admin_pass")
                        ) {
                            $this->preferences->$champ = $valeur;
                        }
                    }
                }
                //once all values has been updated, we can store them
                if (!$this->preferences->store()) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("An SQL error has occured while storing preferences. Please try again, and contact the administrator if the problem persists.")
                    );
                } else {
                    $this->flash->addMessage(
                        'success_detected',
                        _T("Preferences has been saved.")
                    );
                }

                // picture upload
                if (GALETTE_MODE !== 'DEMO' &&  isset($_FILES['logo'])) {
                    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['logo']['tmp_name'] !='') {
                            if (is_uploaded_file($_FILES['logo']['tmp_name'])) {
                                $res = $this->logo->store($_FILES['logo']);
                                if ($res < 0) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        $this->logo->getErrorMessage($res)
                                    );
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
                        $this->flash->addMessage(
                            'error_detected',
                            $this->logo->getPhpErrorMessage(
                                $_FILES['logo']['error']
                            )
                        );
                    }
                }

                if (GALETTE_MODE !== 'DEMO' && isset($_POST['del_logo'])) {
                    if (!$this->logo->delete()) {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("Delete failed")
                        );
                    } else {
                        $this->logo = new Logo(); //get default Logo
                    }
                }

                // Card logo upload
                if (GALETTE_MODE !== 'DEMO' && isset($_FILES['card_logo'])) {
                    if ($_FILES['card_logo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['card_logo']['tmp_name'] !='') {
                            if (is_uploaded_file($_FILES['card_logo']['tmp_name'])) {
                                $res = $print_logo->store($_FILES['card_logo']);
                                if ($res < 0) {
                                    $this->flash->addMessage(
                                        'error_detected',
                                        $print_logo->getErrorMessage($res)
                                    );
                                } else {
                                    $print_logo = new PrintLogo();
                                }
                            }
                        }
                    } elseif ($_FILES['card_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        Analog::log(
                            $print_logo->getPhpErrorMessage($_FILES['card_logo']['error']),
                            Analog::WARNING
                        );
                        $this->flash->addMessage(
                            'error_detected',
                            $print_logo->getPhpErrorMessage(
                                $_FILES['card_logo']['error']
                            )
                        );
                    }
                }

                if (GALETTE_MODE !== 'DEMO' && isset($_POST['del_card_logo'])) {
                    if (!$print_logo->delete()) {
                        $this->flash->addMessage(
                            'error_detected',
                            _T("Delete failed")
                        );
                    } else {
                        $print_logo = new PrintLogo();
                    }
                }
            }

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('preferences'));
        }
    }
)->setName('store-preferences')->add($authenticate);

$app->get(
    __('/test/email', 'routes'),
    function ($request, $response) {
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
                $mail = new GaletteMail();
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
)->setName('testEmail')->add($authenticate);

//charts
$app->get(
    __('/charts', 'routes'),
    function ($request, $response) {
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
)->setName('charts')->add($authenticate);

//plugins
$app->get(
    __('/plugins', 'routes'),
    function ($request, $response) {
        $plugins = $this->get('plugins');

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
)->setName('plugins')->add($authenticate);

//plugins (de)activation
$app->get(
    __('/plugins', 'routes') .
    '/{action:' . __('activate', 'routes') . '|' . __('deactivate', 'routes') .'}/{module_id}',
    function ($request, $response, $args) {
        if (GALETTE_MODE !== 'DEMO') {
            $plugins = $this->get('plugins');
            $action = $args['action'];
            $reload_plugins = false;
            if ($action == __('activate', 'routes')) {
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
            } elseif ($args['action'] == __('deactivate', 'routes')) {
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
                $plugins->loadModules(GALETTE_PLUGINS_PATH, $this->i18n->getFileName());
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('plugins'));
    }
)->setName('pluginsActivation')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    __('/plugins', 'routes') . __('/initialize-database', 'routes') . '/{id}',
    function ($request, $response, $args) {
        if (GALETTE_MODE === 'DEMO') {
            Analog::log(
                'Trying to access plugin database initialization in DEMO mode.',
                Analog::WARNING
            );
            return $response->withStatus(403);
        }

        $params = [];
        $success_detected = [];
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
            'mode'          => ($request->isXhr() ? 'ajax' : '')
        ];

        // display page
        $this->view->render(
            $response,
            'plugin_initdb.tpl',
            $params
        );
        return $response;
    }
)->setName('pluginInitDb')->add($authenticate);

//galette logs
$app->get(
    __('/logs', 'routes') . '[/{option:' . __('page', 'routes') .'|' .
        __('order', 'routes') .'}/{value}]',
    function ($request, $response, $args = []) {
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
                case __('page', 'routes'):
                    $filters->current_page = (int)$value;
                    break;
                case __('order', 'routes'):
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
)->setName(
    'history'
)->add($authenticate);

$app->post(
    __('/logs', 'routes') . __('/filter', 'routes'),
    function ($request, $response, $args) {
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
)->setName(
    'history_filter'
)->add($authenticate);

$app->get(
    __('/logs', 'routes') . __('/flush', 'routes'),
    function ($request, $response) {
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
)->setName('flushHistory')->add($authenticate);

$app->post(
    __('/logs', 'routes') . __('/flush', 'routes'),
    function ($request, $response) {
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
                    'An error occured flushing logs | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occured trying to flush logs :(')
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
)->setName('doFlushHistory')->add($authenticate);

//mailings management
$app->get(
    __('/mailings', 'routes') . '[/{option:' . __('page', 'routes') .'|' .
        __('order', 'routes') .'|' . __('reset', 'routes') .'}/{value}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }

        $value = null;
        if (isset($args['value'])) {
            $option = $args['value'];
        }

        if (isset($this->session->filter_mailings)) {
            $filters = $this->session->filter_mailings;
        } else {
            $filters = new MailingsList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
        }

        $mailhist = new MailingHistory($this->zdb, $this->login, $filters);

        if ($option !== null) {
            switch ($option) {
                case __('page', 'routes'):
                    $filters->current_page = (int)$value;
                    break;
                case __('order', 'routes'):
                    $filters->orderby = $value;
                    break;
                case __('reset', 'routes'):
                    $mailhist->clean();
                    //reinitialize object after flush
                    $filters = new MailingsList();
                    $mailhist = new MailingHistory($this->zdb, $this->login, $filters);
                    break;
            }
        }

        $this->session->filter_mailings = $filters;

        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setSmartyPagination($this->router, $this->view->getSmarty());
        $history_list = $mailhist->getHistory();
        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'gestion_mailings.tpl',
            array(
                'page_title'        => _T("Mailings"),
                'require_dialog'    => true,
                'logs'              => $history_list,
                'history'           => $mailhist,
                'require_calendar'  => true,
            )
        );
        return $response;
    }
)->setName(
    'mailings'
)->add($authenticate);

$app->post(
    __('/mailings', 'routes') . __('/filter', 'routes'),
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->filter_mailings !== null) {
            $filters = $this->session->filter_mailings;
        } else {
            $filters = new MailingsList();
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

            if (isset($post['sender_filter'])) {
                $filters->sender_filter = $post['sender_filter'];
            }

            if (isset($post['sent_filter'])) {
                $filters->sent_filter = $post['sent_filter'];
            }


            if (isset($post['subject_filter'])) {
                $filters->subject_filter = $post['subject_filter'];
            }
        }

        $this->session->filter_mailings = $filters;

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
            ->withHeader('Location', $this->router->pathFor('mailings'));
    }
)->setName(
    'mailings_filter'
)->add($authenticate);

$app->get(
    __('/mailings', 'routes') . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('mailings')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove mailing #%1$s'),
                    $args['id']
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveMailing',
                    ['id' => $args['id']]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeMailing')->add($authenticate);

$app->post(
    __('/mailings', 'routes') . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
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
                $mailhist = new MailingHistory($this->zdb, $this->login);
                $mailhist->removeEntries($args['id'], $this->history);

                $this->flash->addMessage(
                    'success_detected',
                    _T('Mailing has been successfully deleted!')
                );
                $success = true;
            } catch (\Exception $e) {
                $this->zdb->connection->rollBack();
                Analog::log(
                    'An error occured deleting mailing | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occured trying to delete mailing :(')
                );

                $success = false;
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
)->setName('doRemoveMailing')->add($authenticate);

//galette exports
$app->get(
    __('/export', 'routes'),
    function ($request, $response) {
        $csv = new CsvOut();

        $tables_list = $this->zdb->getTables();
        $parameted = $csv->getParametedExports();
        $existing = $csv->getExisting();

        // display page
        $this->view->render(
            $response,
            'export.tpl',
            array(
                'page_title'        => _T("CVS database Export"),
                'tables_list'       => $tables_list,
                'written'           => $this->flash->getMessage('written_exports'),
                'existing'          => $existing,
                'parameted'         => $parameted,
                'require_dialog'    => true
            )
        );
        return $response;
    }
)->setName(
    'export'
)->add($authenticate);

$app->get(
    '/{type:' . __('export', 'routes') . '|' . __('import', 'routes') . '}' . __('/remove', 'routes') .'/{file}',
    function ($request, $response, $args) {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor($args['type'])
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove %1$s file %2$s'),
                    $args['type'],
                    $args['file']
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveCsv',
                    [
                        'type' => $args['type'],
                        'file' => $args['file']
                    ]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeCsv')->add($authenticate);

$app->post(
    '/{type:' . __('export', 'routes') . '|' . __('import', 'routes') . '}' . __('/remove', 'routes') .'/{file}',
    function ($request, $response, $args) {
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
            $csv = $args['type'] === __('export', 'routes') ?
                new CsvOut() :
                new CsvIn($this->zdb);
            $res = $csv->remove($args['file']);
            if ($res === true) {
                $success = true;
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%export',
                        $args['file'],
                        _T("'%export' file has been removed from disk.")
                    )
                );
            } else {
                $success = false;
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%export',
                        $args['file'],
                        _T("Cannot remove '%export' from disk :/")
                    )
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
)->setName('doRemoveCsv')->add($authenticate);

$app->post(
    __('/export', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $csv = new CsvOut();
        $written = [];

        if (isset($post['export_tables']) && $post['export_tables'] != '') {
            foreach ($post['export_tables'] as $table) {
                $select = $this->zdb->sql->select($table);
                $results = $this->zdb->execute($select);

                if ($results->count() > 0) {
                    $filename = $table . '_full.csv';
                    $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
                    $fp = fopen($filepath, 'w');
                    if ($fp) {
                        $res = $csv->export(
                            $results,
                            Csv::DEFAULT_SEPARATOR,
                            Csv::DEFAULT_QUOTE,
                            true,
                            $fp
                        );
                        fclose($fp);
                        $written[] = [
                            'name' => $table,
                            'file' => $filename
                        ];
                    }
                } else {
                    $this->flash->addMessage(
                        'warning_detected',
                        str_replace(
                            '%table',
                            $table,
                            _T("Table %table is empty, and has not been exported.")
                        )
                    );
                }
            }
        }

        if (isset($post['export_parameted']) && $post['export_parameted'] != '') {
            foreach ($post['export_parameted'] as $p) {
                $res = $csv->runParametedExport($p);
                $pn = $csv->getParamedtedExportName($p);
                switch ($res) {
                    case Csv::FILE_NOT_WRITABLE:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("Export file could not be write on disk for '%export'. Make sure web server can write in the exports directory.")
                            )
                        );
                        break;
                    case Csv::DB_ERROR:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occured running parameted export '%export'.")
                            )
                        );
                        break;
                    case false:
                        $this->flash->addMessage(
                            'error_detected',
                            str_replace(
                                '%export',
                                $pn,
                                _T("An error occured running parameted export '%export'. Please check the logs.")
                            )
                        );
                        break;
                    default:
                        //no error, file has been writted to disk
                        $written[] = [
                            'name' => $pn,
                            'file' => (string)$res
                        ];
                        break;
                }
            }
        }

        if (count($written)) {
            foreach ($written as $ex) {
                $path = $this->router->pathFor('getCsv', ['type' => __('export', 'routes'), 'file' => $ex['file']]);
                $this->flash->addMessage(
                    'written_exports',
                    '<a href="' . $path . '">' . $ex['name'] . ' (' . $ex['file'] . ')</a>'
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('export'));
    }
)->setName('doExport')->add($authenticate);

$app->get(
    '/{type:' . __('export', 'routes') . '|' . __('import', 'routes') . '}' . __('/get', 'routes') . '/{file}',
    function ($request, $response, $args) {
        $filename = $args['file'];

        //Exports main contain user confidential data, they're accessible only for
        //admins or staff members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $filepath = $args['type'] === __('export', 'routes') ?
                CsvOut::DEFAULT_DIRECTORY :
                CsvIn::DEFAULT_DIRECTORY;
            $filepath .= $filename;
            if (file_exists($filepath)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '";');
                header('Pragma: no-cache');
                readfile($filepath);
            } else {
                Analog::log(
                    'A request has been made to get an ' . $args['type'] . ' file named `' .
                    $filename .'` that does not exists.',
                    Analog::WARNING
                );
                $notFound = $this->notFoundHandler;
                return $notFound($request, $response);
            }
        } else {
            Analog::log(
                'A non authorized person asked to retrieve ' . $args['type'] . ' file named `' .
                $filename . '`. Access has not been granted.',
                Analog::WARNING
            );
            $error = $this->errorHandler;
            return $error(
                $request,
                $response->withStatus(403)
            );
        }
    }
)->setName('getCsv')->add($authenticate);

$app->get(
    __('/import', 'routes'),
    function ($request, $response) {
        $csv = new CsvIn($this->zdb);
        $existing = $csv->getExisting();
        $dryrun = true;

        // display page
        $this->view->render(
            $response,
            'import.tpl',
            array(
                'page_title'        => _T("CSV members import"),
                'require_dialog'    => true,
                'existing'          => $existing,
                'dryrun'            => $dryrun,
                'import_file'       => $this->flash->getMessage('import_file')[0]
            )
        );
        return $response;
    }
)->setName('import')->add($authenticate);

$app->post(
    __('/import', 'routes'),
    function ($request, $response) {
        $csv = new CsvIn($this->zdb);
        $post = $request->getParsedBody();
        $dryrun = isset($post['dryrun']);
        $res = $csv->import(
            $this->zdb,
            $this->preferences,
            $this->history,
            $post['import_file'],
            $this->members_fields,
            $this->members_fields_cats,
            $dryrun
        );
        if ($res !== true) {
            if ($res < 0) {
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getErrorMessage($res)
                );
                if (count($csv->getErrors()) > 0) {
                    foreach ($csv->getErrors() as $error) {
                        $this->flash->addMessage(
                            'error_detected',
                            $error
                        );
                    }
                }
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occured importing the file :(")
                );
            }

            $this->flash->addMessage(
                'import_file',
                $post['import_file']
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%filename%',
                    $post['import_file'],
                    _T("File '%filename%' has been successfully imported :)")
                )
            );
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('import'));
    }
)->setName('doImport')->add($authenticate);

$app->post(
    __('/import/upload', 'routes'),
    function ($request, $response) {
        $csv = new CsvIn($this->zdb);
        if (isset($_FILES['new_file'])) {
            if ($_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['new_file']['tmp_name'] !='') {
                    if (is_uploaded_file($_FILES['new_file']['tmp_name'])) {
                        $res = $csv->store($_FILES['new_file']);
                        if ($res < 0) {
                            $this->flash->addMessage(
                                'error_detected',
                                $csv->getErrorMessage($res)
                            );
                        } else {
                            $this->flash->addMessage(
                                'success_detected',
                                _T("Your file has been successfully uploaded!")
                            );
                        }
                    }
                }
            } elseif ($_FILES['new_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $csv->getPhpErrorMessage($_FILES['new_file']['error']),
                    Analog::WARNING
                );
                $this->flash->addMessage(
                    'error_detected',
                    $csv->getPhpErrorMessage(
                        $_FILES['new_file']['error']
                    )
                );
            } elseif (isset($_POST['upload'])) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("No files has been seleted for upload!")
                );
            }
        } else {
            $this->flash->addMessage(
                'warning_detected',
                _T("No files has been uploaded!")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('import'));
    }
)->setname('uploadImportFile')->add($authenticate);

$app->get(
    __('/import/model', 'routes'),
    function ($request, $response) {
        $model = new ImportModel();
        $model->load();

        if (isset($request->getQueryParams()['remove'])) {
            $model->remove($this->zdb);
            $model->load();
        }

        $csv = new CsvIn($this->zdb);

        /** FIXME:
        * - set fields that should not be part of import
        * - set fields that must be part of import, and visually disable them in the list
        */

        $fields = $model->getFields();
        $defaults = $csv->getDefaultFields();
        $defaults_loaded = false;

        if ($fields === null) {
            $fields = $defaults;
            $defaults_loaded = true;
        }

        $import_fields = $this->members_fields;
        //we do not want to import id_adh. Never.
        unset($import_fields['id_adh']);

        // display page
        $this->view->render(
            $response,
            'import_model.tpl',
            array(
                'page_title'        => _T("CSV import model"),
                'require_dialog'    => true,
                'fields'            => $fields,
                'model'             => $model,
                'defaults'          => $defaults,
                'members_fields'    => $import_fields,
                'defaults_loaded'   => $defaults_loaded,
                'require_tabs'      => true
            )
        );
        return $response;
    }
)->setName('importModel')->add($authenticate);

$app->get(
    __('/import/model/get', 'routes'),
    function ($request, $response) {
        $model = new ImportModel();
        $model->load();

        $csv = new CsvIn($this->zdb);

        /** FIXME:
        * - set fields that should not be part of import
        * - set fields that must be part of import, and visually disable them in the list
        */

        $fields = $model->getFields();
        $defaults = $csv->getDefaultFields();
        $defaults_loaded = false;

        if ($fields === null) {
            $fields = $defaults;
            $defaults_loaded = true;
        }

        $ocsv = new CsvOut();
        $res = $ocsv->export(
            $fields,
            Csv::DEFAULT_SEPARATOR,
            Csv::DEFAULT_QUOTE,
            $fields
        );
        $filename = _T("galette_import_model.csv");
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header('Pragma: no-cache');
        echo $res;
    }
)->setName('getImportModel')->add($authenticate);

$app->post(
    __('/import/model/store', 'routes'),
    function ($request, $response) {
        $model = new ImportModel();
        $model->load();

        $model->setFields($request->getParsedBody()['fields']);
        $res = $model->store($this->zdb);
        if ($res === true) {
            $this->flash->addMessage(
                'success_detected',
                _T("Import model has been successfully stored :)")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("Import model has not been stored :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('importModel'));
    }
)->setName('storeImportModel')->add($authenticate);

$app->get(
    __('/models/pdf', 'routes'),
    function ($request, $response) {
        $id = 1;
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        } elseif (isset($_POST[PdfModel::PK])) {
            $id = (int)$_POST[PdfModel::PK];
        }

        $model = null;

        $ms = new PdfModels($this->zdb, $this->preferences, $this->login);
        $models = $ms->getList();

        foreach ($models as $m) {
            if ($m->id === $id) {
                $model = $m;
                break;
            }
        }

        $ajax = false;
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        $tpl = null;
        $params = [];
        if ($ajax) {
            $tpl = 'gestion_pdf_content.tpl';
            $params['model'] = $model;
        } else {
            $tpl = 'gestion_pdf.tpl';
            $params = [
                'page_title'        => _T("PDF models"),
                'models'            => $models,
                'require_tabs'      => true,
                'require_dialog'    => true,
                'model'             => $model
            ];
        }

        // display page
        $this->view->render(
            $response,
            $tpl,
            $params
        );
        return $response;
    }
)->setName('pdfModels')->add($authenticate);

$app->post(
    __('/models/pdf', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $type = null;
        if (isset($post['model_type'])) {
            $type = (int)$post['model_type'];
        }

        if ($type === null) {
            $this->flash->addMessage(
                'error_detected',
                _T("Missing PDF model type!")
            );
        } else {
            $class = PdfModel::getTypeClass($type);
            if (isset($post[PdfModel::PK])) {
                $model = new $class($this->zdb, $this->preferences, (int)$_POST[PdfModel::PK]);
            } else {
                $model = new $class($this->zdb, $this->preferences);
            }

            try {
                $model->header = $post['model_header'];
                $model->footer = $post['model_footer'];
                $model->type = $type;
                if (isset($post['model_body'])) {
                    $model->body = $post['model_body'];
                }
                if (isset($post['model_title'])) {
                    $model->title = $post['model_title'];
                }
                if (isset($post['model_body'])) {
                    $model->subtitle = $post['model_subtitle'];
                }
                if (isset($post['model_styles'])) {
                    $model->styles = $post['model_styles'];
                }
                $res = $model->store();
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T("Model has been successfully stored!")
                    );
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("Model has not been stored :(")
                    );
                }
            } catch (\Exception $e) {
                $error_detected[] = $e->getMessage();
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('pdfModels'));
    }
)->setName('pdfModels')->add($authenticate);

$app->get(
    __('/titles', 'routes'),
    function ($request, $response) {

        $titles = Titles::getList($this->zdb);

        // display page
        $this->view->render(
            $response,
            'gestion_titres.tpl',
            [
                'page_title'        => _T("Titles management"),
                'titles_list'       => $titles,
                'require_dialog'    => true
            ]
        );
        return $response;
    }
)->setName('titles')->add($authenticate);

$app->post(
    __('/titles', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $title = new Title();

        $title->short = $post['short_label'];
        $title->long = $post['long_label'];

        $res = $title->store($this->zdb);

        if (!$res) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has not been added!")
                )
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has been successfully added.")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('titles'));
    }
)->setName('titles')->add($authenticate);

$app->get(
    __('/titles', 'routes') . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('titles')
        ];
        $title = new Title((int)$args['id']);

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove title %1$s'),
                    $title->short
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveTitle',
                    ['id' => $args['id']]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeTitle')->add($authenticate);

$app->post(
    __('/titles', 'routes') . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
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
            $title = new Title((int)$args['id']);
            try {
                $res = $title->remove($this->zdb);
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $title->short,
                            _T("Title '%name' has been successfully deleted.")
                        )
                    );
                    $success = true;
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        str_replace(
                            '%name',
                            $title->short,
                            _T("An error occured removing title '%name' :(")
                        )
                    );
                }
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("That title is still in use, you cannot delete it!")
                    );
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
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
)->setName('doRemoveTitle')->add($authenticate);

$app->get(
    __('/titles/edit', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $id = $args['id'];
        $title = new Title((int)$id);

        // display page
        $this->view->render(
            $response,
            'edit_title.tpl',
            [
                'page_title'    => _T("Edit title"),
                'title'         => $title
            ]
        );
        return $response;
    }
)->setname('editTitle')->add($authenticate);

$app->post(
    __('/titles/edit', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $id = $args['id'];
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('titles'));
        }

        $title = new Title((int)$id);
        $title->short = $post['short_label'];
        $title->long = $post['long_label'];
        $res = $title->store($this->zdb);

        if (!$res) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has not been modified!")
                )
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('editTitle', ['id' => $id]));
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has been successfully modified.")
                )
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('titles'));
        }
    }
)->setname('editTitle')->add($authenticate);

$app->get(
    __('/texts', 'routes') . '[/{lang}/{ref}]',
    function ($request, $response, $args) {
        if (!isset($args['lang'])) {
            $args['lang'] = $this->preferences->pref_lang;
        }
        if (!isset($args['ref'])) {
            $args['ref'] = Texts::DEFAULT_REF;
        }

        $texts = new Texts(
            $this->texts_fields,
            $this->preferences,
            $this->router
        );

        $mtxt = $texts->getTexts($args['ref'], $args['lang']);

        // display page
        $this->view->render(
            $response,
            'gestion_textes.tpl',
            [
                'page_title'        => _T("Automatic emails texts edition"),
                'reflist'           => $texts->getRefs($args['lang']),
                'langlist'          => $this->i18n->getList(),
                'cur_lang'          => $args['lang'],
                'cur_ref'           => $args['ref'],
                'mtxt'              => $mtxt,
                'require_dialog'    => true
            ]
        );
        return $response;
    }
)->setName('texts')->add($authenticate);

$app->post(
    __('/texts', 'routes') . __('/change', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->router->pathFor(
                    'texts',
                    [
                        'lang'  => $post['sel_lang'],
                        'ref'   => $post['sel_ref']
                    ]
                )
            );
    }
)->setName('changeText')->add($authenticate);

$app->post(
    __('/texts', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $texts = new Texts($this->texts_fields, $this->preferences, $this->router);

        //set the language
        if (isset($post['sel_lang'])) {
            $cur_lang = $post['sel_lang'];
        }
        //set the text entry
        if (isset($post['sel_ref'])) {
            $cur_ref = $post['sel_ref'];
        }

        $mtxt = $texts->getTexts($cur_ref, $cur_lang, $this->router);
        $res = $texts->setTexts(
            $cur_ref,
            $cur_lang,
            $post['text_subject'],
            $post['text_body']
        );

        if (!$res) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has not been modified!")
                )
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has been successfully modified.")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('texts'));
    }
)->setName('texts')->add($authenticate);

$app->get(
    '/{class:' . __('contributions-types', 'routes') . '|' . __('status', 'routes') . '}',
    function ($request, $response, $args) {
        $className = null;
        $class = null;

        $params = [
            'require_tabs'      => true,
            'require_dialog'    => true
        ];

        switch ($args['class']) {
            case __('status', 'routes'):
                $className = 'Status';
                $class = new Galette\Entity\Status($this->zdb);
                $params['page_title'] = _T("User statuses");
                $params['non_staff_priority'] = Galette\Repository\Members::NON_STAFF_MEMBERS;
                break;
            case __('contributions-types', 'routes'):
                $className = 'ContributionsTypes';
                $class = new Galette\Entity\ContributionsTypes($this->zdb);
                $params['page_title'] = _T("Contribution types");
                break;
        }

        $params['class'] = $className;
        $params['url_class'] = $args['class'];
        $params['fields'] = $class::$fields;

        $list = $class->getCompleteList();
        $params['entries'] = $list;

        if (count($class->errors) > 0) {
            $error_detected = array_merge($error_detected, $class->errors);
        }

        // display page
        $this->view->render(
            $response,
            'gestion_intitules.tpl',
            $params
        );
        return $response;
    }
)->setName('entitleds')->add($authenticate);

$app->get(
    '/{class:' . __('contributions-types', 'routes') . '|' . __('status', 'routes') .
        '}/{action:' . __('edit', 'routes') .'|' . __('add', 'routes') .'}[/{id:\d+}]',
    function ($request, $response, $args) {
        $className = null;
        $class = null;

        $params = [
            'require_tabs'  => true,
        ];

        switch ($args['class']) {
            case __('status', 'routes'):
                $className = 'Status';
                $class = new Galette\Entity\Status($this->zdb);
                $params['page_title'] = _T("Edit status");
                $params['non_staff_priority'] = Galette\Repository\Members::NON_STAFF_MEMBERS;
                break;
            case __('contributions-types', 'routes'):
                $className = 'ContributionsTypes';
                $class = new Galette\Entity\ContributionsTypes($this->zdb);
                $params['page_title'] = _T("Edit contribution type");
                break;
        }

        $params['class'] = $className;
        $params['url_class'] = $args['class'];
        $params['fields'] = $class::$fields;

        $entry = $class->get($args['id']);
        $params['entry'] = $entry;

        // display page
        $this->view->render(
            $response,
            'editer_intitule.tpl',
            $params
        );
        return $response;
    }
)->setName('editEntitled')->add($authenticate);

$app->post(
    '/{class:' . __('contributions-types', 'routes') . '|' . __('status', 'routes') .
        '}/{action:' . __('edit', 'routes') . '|' . __('add', 'routes') . '}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $class = null;

        switch ($args['class']) {
            case __('status', 'routes'):
                $class = new Galette\Entity\Status($this->zdb);
                break;
            case __('contributions-types', 'routes'):
                $class = new Galette\Entity\ContributionsTypes($this->zdb);
                break;
        }

        $label = trim($post[$class::$fields['libelle']]);
        $field = trim($post[$class::$fields['third']]);

        $ret = null;
        if ($args['action'] === __('add', 'routes')) {
            $ret = $class->add($label, $field);
            if ($ret === true) {
                addDynamicTranslation($label);
            }
        } else {
            $oldlabel = $class->getLabel($id, false);
            $ret = $class->update($args['id'], $label, $field);
            if ($ret === true) {
                if (isset($label) && ($oldlabel != $label)) {
                    deleteDynamicTranslation($oldlabel);
                    addDynamicTranslation($label);
                }
            }
        }

        if ($ret !== true) {
            $msg_type = 'error_detected';
            $msg = $args['action'] === __('add', 'routes') ?
                _T("%type has not been added :(") :
                _T("%type #%id has not been updated");
        } else {
            $msg_type = 'success_detected';
            $msg = $args['action'] === __('add', 'routes') ?
                _T("%type has been successfully added!") :
                _T("%type #%id has been successfully updated!");
        }

        $this->flash->addMessage(
            $msg_type,
            str_replace(
                ['%type', '%id'],
                [$class->getI18nType(), (isset($args['id']) ? $args['id'] : null)],
                $msg
            )
        );

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('entitleds', ['class' => $args['class']]));
    }
)->setName('editEntitled')->add($authenticate);

$app->get(
    '/{class:' . __('contributions-types', 'routes') . '|' . __('status', 'routes') .
        '}' . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('entitleds', ['class' => $args['class']])
        ];

        $class = null;
        switch ($args['class']) {
            case __('status', 'routes'):
                $class = new Galette\Entity\Status($this->zdb);
                break;
            case __('contributions-types', 'routes'):
                $class = new Galette\Entity\ContributionsTypes($this->zdb);
                break;
        }
        $label = $class->getLabel((int)$args['id']);

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => str_replace(
                    ['%type', '%label'],
                    [$class->getI18nType(), $label],
                    _T("Remove %type '%label'")
                ),
                'form_url'      => $this->router->pathFor(
                    'doRemoveEntitled',
                    [
                        'class' => $args['class'],
                        'id'    => $args['id']
                    ]
                ),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeEntitled')->add($authenticate);

$app->post(
    '/{class:' . __('contributions-types', 'routes') . '|' . __('status', 'routes') .
        '}' . __('/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
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
            $class = null;
            switch ($args['class']) {
                case __('status', 'routes'):
                    $class = new Galette\Entity\Status($this->zdb);
                    break;
                case __('contributions-types', 'routes'):
                    $class = new Galette\Entity\ContributionsTypes($this->zdb);
                    break;
            }

            try {
                $label = $class->getLabel((int)$args['id']);
                if ($label !== $class::ID_NOT_EXITS) {
                    $ret = $class->delete((int)$args['id']);

                    if ($ret === true) {
                        deleteDynamicTranslation($label);
                        $this->flash->addMessage(
                            'success_detected',
                            str_replace(
                                ['%type', '%label'],
                                [$class->getI18nType(), $label],
                                _T("%type '%label' was successfully removed")
                            )
                        );
                        $success = true;
                    } else {
                        $errors = $class->errors;
                        if (count($errors) === 0) {
                            $errors[] = str_replace(
                                ['%type', '%id'],
                                [$class->getI18nType(), $args['id']],
                                _T("An error occured trying to remove %type #%id")
                            );
                        }

                        foreach ($errors as $error) {
                            $this->flash->addMessage(
                                'error_detected',
                                $error
                            );
                        }
                    }
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("Requested label does not exists!")
                    );
                }
            } catch (RuntimeException $re) {
                $this->flash->addMessage(
                    'error_detected',
                    $re->getMessage()
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
)->setName('doRemoveEntitled')->add($authenticate);

$app->get(
    __('/dynamic-translations', 'routes') . '[/{text_orig}]',
    function ($request, $response, $args) {
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
                'An error occured counting l10n entries | ' .
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
                    'An error occured retrieving l10n entries | ' .
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
)->setName('dynamicTranslations')->add($authenticate);

$app->post(
    __('/dynamic-translations', 'routes'),
    function ($request, $response) {
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
                                _T("An error occured saving label `%label` for language `%lang`")
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
)->setName('editDynamicTranslation')->add($authenticate);

$app->get(
    __('/fields', 'routes') . __('/core', 'routes') . __('/configure', 'routes'),
    function ($request, $response) {
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
)->setName('configureCoreFields')->add($authenticate);

$app->post(
    __('/fields', 'routes') . __('/core', 'routes') . __('/configure', 'routes'),
    function ($request, $response) {
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
                _T("An error occured while storing fields configuration :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('configureCoreFields'));
    }
)->setName('storeCoreFieldsConfig')->add($authenticate);

$app->get(
    __('/fields', 'routes') . __('/dynamic', 'routes') . __('/configure', 'routes') . '[/{form:adh|contrib|trans}]',
    function ($request, $response, $args) {
        $form_name = (isset($args['form'])) ? $args['form'] : 'adh';
        if (isset($_POST['form']) && trim($_POST['form']) != '') {
            $form_name = $_POST['form'];
        }
        $fields = new \Galette\Repository\DynamicFieldsTypes($this->zdb);
        $fields_list = $fields->getList($form_name, $this->login);

        $field_type_names = DynamicFieldType::getFieldsTypesNames();

        $params = [
            'require_tabs'      => true,
            'require_dialog'    => true,
            'fields_list'       => $fields_list,
            'form_name'         => $form_name,
            'form_title'        => DynamicFieldType::getFormTitle($form_name),
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
            $all_forms = DynamicFieldType::getFormsNames();
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
)->setName('configureDynamicFields')->add($authenticate);

$app->get(
    __('/fields', 'routes') . __('/dynamic', 'routes') . __('/move', 'routes') . '/{form:adh|contrib|trans}' .
        '/{direction:' . __('up', 'routes') . '|' . __('down', 'routes') . '}/{id:\d+}',
    function ($request, $response, $args) {
        $field_id = (int)$args['id'];
        $form_name = $args['form'];

        $field = DynamicFieldType::loadFieldType($this->zdb, $field_id);
        if ($field->move($args['direction'])) {
            $this->flash->addMessage(
                'success_detected',
                _T("Field has been successfully moved")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("An error occured moving field :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('configureDynamicFields', ['form' => $form_name]));
    }
)->setName('moveDynamicField')->add($authenticate);

$app->get(
    __('/fields', 'routes') . __('/dynamic', 'routes') .
        __('/remove', 'routes') . '/{form:adh|contrib|trans}/{id:\d+}',
    function ($request, $response, $args) {
        $field = DynamicFieldType::loadFieldType($this->zdb, (int)$args['id']);
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
)->setName('removeDynamicField')->add($authenticate);

$app->post(
    __('/fields', 'routes') . __('/dynamic', 'routes') .
        __('/remove', 'routes') . '/{form:adh|contrib|trans}/{id:\d+}',
    function ($request, $response, $args) {
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
            $field = DynamicFieldType::loadFieldType($this->zdb, $field_id);
            if ($field->remove()) {
                $this->flash->addMessage(
                    'success_detected',
                    _T('Field has been successfully deleted!')
                );
                $success = true;
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occured trying to delete field :(')
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
)->setName('doRemoveDynamicField')->add($authenticate);

$app->get(
    __('/fields', 'routes') . __('/dynamic', 'routes') .
        '/{action:' . __('edit', 'routes') . '|' . __('add', 'routes') . '}' .
        '/{form:adh|contrib|trans}[/{id:\d+}]',
    function ($request, $response, $args) {
        $action = $args['action'];

        $id_dynf = null;
        if (isset($args['id'])) {
            $id_dynf = $args['id'];
        }

        if ($action === __('edit', 'routes') && $id_dynf === null) {
            throw new \RuntimeException(
                _T("Dynamic field ID cannot ben null calling edit route!")
            );
        } elseif ($action === __('add', 'routes') && $id_dynf !== null) {
             return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'editDynamicField',
                        [
                            'action'    => __('add', 'routes'),
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
        } elseif ($action === __('edit', 'routes')) {
            $df = DynamicFieldType::loadFieldType($this->zdb, $id_dynf);
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
            'perm_names'    => DynamicFieldType::getPermsNames(),
            'mode'          => ($request->isXhr() ? 'ajax' : '')
        ];

        if ($df !== null) {
            $params['df'] = $df;
        }
        if ($action === __('add', 'routes')) {
            $params['field_type_names'] = DynamicFieldType::getFieldsTypesNames();
        }

        // display page
        $this->view->render(
            $response,
            'editer_champ.tpl',
            $params
        );
        return $response;
    }
)->setName('editDynamicField')->add($authenticate);

$app->post(
    __('/fields', 'routes') . __('/dynamic', 'routes') .
        '/{action:' . __('edit', 'routes') . '|' . __('add', 'routes') . '}' .
        '/{form:adh|contrib|trans}[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();

        $error_detected = [];
        $warning_detected = [];

        if ($args['action'] === __('add', 'routes')) {
            $df = DynamicFieldType::getFieldType($this->zdb, $post['field_type']);
        } else {
            $field_id = (int)$args['id'];
            $df = DynamicFieldType::loadFieldType($this->zdb, $field_id);
        }

        try {
            $df->store($post);
            $error_detected = $df->getErrors();
            $warning_detected = $df->getWarnings();
        } catch (\Exception $e) {
            if ($args['action'] === __('edit', 'routes')) {
                $msg = 'An error occured storing dynamic field ' . $df->getId() . '.';
            } else {
                $msg = 'An error occured adding new dynamic field.';
            }
            Analog::log(
                $msg . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            if (GALETTE_MODE == 'DEV') {
                throw $e;
            }
            $error_detected[] = _T('An error occured adding dynamic field :(');
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
            if (!$df instanceof \Galette\DynamicFieldsTypes\Separator
                && $args['action'] == __('add', 'routes')
            ) {
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor(
                            'editDynamicField',
                            [
                                'action'    => __('edit', 'routes'),
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
)->setName('doEditDynamicField')->add($authenticate);

$app->get(
    __('/generate-data', 'routes'),
    function ($request, $response, $args) {

        $params = [
            'page_title'            => _T('Generate fake data'),
            'number_members'        => \Galette\Util\FakeData::DEFAULT_NB_MEMBERS,
            'number_contrib'        => \Galette\Util\FakeData::DEFAULT_NB_CONTRIB,
            'number_groups'         => \Galette\Util\FakeData::DEFAULT_NB_GROUPS,
            'number_transactions'   => \Galette\Util\FakeData::DEFAULT_NB_TRANSACTIONS
        ];

        // display page
        $this->view->render(
            $response,
            'fake_data.tpl',
            $params
        );
        return $response;
    }
)->setName('fakeData')->add($authenticate);

$app->post(
    __('/generate-data', 'routes'),
    function ($request, $response) {
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
            ->setMaxContribs($post['number_contrib']);

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
)->setName('doFakeData')->add($authenticate);
