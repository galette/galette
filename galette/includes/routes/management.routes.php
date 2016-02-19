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
use Galette\Core\MailingHistory;
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

//galette's dashboard
$app->get(
    '/dashboard',
    /*'/' . _T("dashboard"),*/
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
    '/preferences',
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
            )
        );
        return $response;
    }
)->setName('preferences')->add($authenticate);

//preferences procedure
$app->post(
    '/preferences',
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
            while (list($key, $val) = each($required)) {
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

            //postal adress
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
                while (list($champ,$valeur) = each($insert_values)) {
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

//charts
$app->get(
    '/charts',
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
    '/plugins',
    function ($request, $response) {
        $plugins = $this->get('plugins');
        if (GALETTE_MODE !== 'DEMO') {
            $reload_plugins = false;
            if (isset($_GET['activate'])) {
                try {
                    $plugins->activateModule($_GET['activate']);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $_GET['activate'],
                            _T("Plugin %name has been enabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (Exception $e) {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
            }

            if (isset($_GET['deactivate'])) {
                try {
                    $plugins->deactivateModule($_GET['deactivate']);
                    $this->flash->addMessage(
                        'success_detected',
                        str_replace(
                            '%name',
                            $_GET['deactivate'],
                            _T("Plugin %name has been disabled")
                        )
                    );
                    $reload_plugins = true;
                } catch (Exception $e) {
                    $this->flash->addMessage(
                        'error_detected',
                        $e->getMessage()
                    );
                }
            }

            //If some plugins have been (de)activated, we have to reload
            if ($reload_plugins === true) {
                $plugins->loadModules(GALETTE_PLUGINS_PATH, $i18n->getFileName());
            }
        }

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

//galette logs
$app->get(
    '/logs[/{option:page|order|reset}/{value}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $this->history->current_page = (int)$value;
                    break;
                case 'order':
                    $this->history->tri = $value;
                    break;
                case 'reset':
                    $this->history->clean();
                    //reinitialize object after flush
                    $this->history = new History();
                    break;
            }
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $this->history->show = $request->getQueryParams()['nbshow'];
        }

        $logs = array();
        $logs = $this->history->getHistory();

        //assign pagination variables to the template and add pagination links
        $this->history->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'history.tpl',
            array(
                'page_title'        => _T("Logs"),
                'logs'              => $logs,
                'nb_lines'          => count($logs),
                'history'           => $this->history
            )
        );
        return $response;
    }
)->setName(
    'history'
)->add($authenticate);

//mailings management
$app->get(
    '/mailings[/{option:page|order}/{value:\d+}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }

        $value = null;
        if (isset($args['value'])) {
            $option = $args['value'];
        }

        $mailhist = new MailingHistory();

        if (isset($request->getQueryParams()['reset'])) {
            $mailhist->clean();
            //reinitialize object after flush
            $mailhist = new MailingHistory();
        }

        //delete mailings
        if (isset($request->getQueryParams()['sup']) || isset($request->getParsedBody()['delete'])) {
            if (isset($request->getQueryParams()['sup'])) {
                $mailhist->removeEntries($request->getQueryParams()['sup']);
            } elseif (isset($request->getParsedBody()['member_sel'])) {
                $mailhist->removeEntries($request->getParsedBody()['member_sel']);
            }
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $mailhist->current_page = (int)$value;
                    break;
                case 'order':
                    $mailhist->orderby = $value;
                    break;
            }
        }

        if (isset($request->getQueryParams()['nbshow'])
            && is_numeric($request->getQueryParams()['nbshow'])
        ) {
            $mailhist->show = $request->getQueryParams()['nbshow'];
        }

        if (isset($request->getQueryParams()['order'])) {
            $mailhist->orderby = $request->getQueryParams()['order'];
        }

        $history_list = array();
        $history_list = $mailhist->getHistory();

        //assign pagination variables to the template and add pagination links
        $mailhist->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'gestion_mailings.tpl',
            array(
                'page_title'        => _T("Mailings"),
                'require_dialog'    => true,
                'logs'              => $history_list,
                'nb_lines'          => count($history_list),
                'history'           => $mailhist
            )
        );
        return $response;
    }
)->setName(
    'mailings'
)->add($authenticate);

//galette exports
$app->get(
    '/export',
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
                'parameted'         => $parameted
            )
        );
        return $response;
    }
)->setName(
    'export'
)->add($authenticate);

$app->get(
    '/{type:export|import}/remove/{file}',
    function ($request, $response, $args) {
        $csv = $args['type'] === 'export' ? new CsvOut() : new CsvIn;
        $res = $csv->remove($args['file']);
        if ($res === true) {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%export',
                    $args['file'],
                    _T("'%export' file has been removed from disk.")
                )
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                str_replace(
                    '%export',
                    $args['file'],
                    _T("Cannot remove '%export' from disk :/")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor($args['type']));
    }
)->setName('removeCsv')->add($authenticate);

$app->post(
    '/export',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $csv = new CsvOut();

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
                        $this->flash->addMessage(
                            'written_exports',
                            array(
                                'name' => $filename,
                                'file' => $filepath
                            )
                        );
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
                        $this->flash->addMessage(
                            'written_exports',
                            array(
                                'name' => $pn,
                                'file' => $res
                            )
                        );
                        break;
                }
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('export'));
    }
)->setName('doExport')->add($authenticate);

$app->get(
    '/{type:export|import}/get/{file}',
    function ($request, $response, $args) {
        $filename = $args['file'];

        //Exports main contain user confidential data, they're accessible only for
        //admins or staff members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $filepath = $args['type'] === 'export' ? CsvOut::DEFAULT_DIRECTORY : CsvIn::DEFAULT_DIRECTORY;
            $filepath .= $filename;
            if (file_exists($filepath)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '";');
                header('Pragma: no-cache');
                readfile($filepath);
            } else {
                Analog::log(
                    'A request has been made to get an ' . $args['type'] . 'ed file named `' .
                    $filename .'` that does not exists.',
                    Analog::WARNING
                );
                header('HTTP/1.0 404 Not Found');
            }
        } else {
            Analog::log(
                'A non authorized person asked to retrieve ' . $args['type'] . 'ed file named `' .
                $filename . '`. Access has not been granted.',
                Analog::WARNING
            );
            header('HTTP/1.0 403 Forbidden');
        }
    }
)->setName('getCsv')->add($authenticate);

$app->get(
    '/import',
    function ($request, $response) {
        $csv = new CsvIn();
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
    '/import',
    function ($request, $response) {
        $csv = new CsvIn();
        $post = $request->getParsedBody();
        $dryrun = isset($post['dryrun']);
        $res = $csv->import($post['import_file'], $this->members_fields, $dryrun);
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
    '/import/upload',
    function ($request, $response) {
        $csv = new CsvIn();
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
    '/import/model',
    function ($request, $response) {
        $model = new ImportModel();
        $model->load();

        if (isset($request->getQueryParams()['remove'])) {
            $model->remove($this->zdb);
            $model->load();
        }

        $csv = new CsvIn();

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
    '/import/model/get',
    function ($request, $response) {
        $model = new ImportModel();
        $model->load();

        $csv = new CsvIn();

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
    '/import/model/store',
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
    '/models/pdf',
    function ($request, $response) {
        $id = 1;
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        } elseif (isset($_POST[PdfModel::PK])) {
            $id = (int)$_POST[PdfModel::PK];
        }

        $model = null;

        $ms = new PdfModels($this->zdb, $this->preferences);
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
    '/models/pdf',
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
    '/titles',
    function ($request, $response) {

        $titles = Titles::getList($this->zdb);

        // display page
        $this->view->render(
            $response,
            'gestion_titres.tpl',
            [
                'page_title'    => _T("Titles management"),
                'titles_list'   => $titles
            ]
        );
        return $response;
    }
)->setName('titles')->add($authenticate);

$app->post(
    '/titles',
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
    '/titles/remove/{id:\d+}',
    function ($request, $response, $args) {
        $id = $args['id'];

        $title = new Title((int)$id);
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

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('titles'));
    }
)->setName('removeTitle')->add($authenticate);

$app->get(
    '/titles/edit/{id:\d+}',
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
    '/titles/edit/{id:\d+}',
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
    '/texts',
    function ($request, $response) {
        $cur_lang = $this->preferences->pref_lang;
        $cur_ref = Texts::DEFAULT_REF;

        $texts = new Texts($this->texts_fields, $this->preferences);

        $mtxt = $texts->getTexts($cur_ref, $cur_lang);

        // display page
        $this->view->render(
            $response,
            'gestion_textes.tpl',
            [
                'page_title'        => _T("Automatic emails texts edition"),
                'reflist'           => $texts->getRefs($cur_lang),
                'langlist'          => $this->i18n->getList(),
                'cur_lang'          => $cur_lang,
                'cur_ref'           => $cur_ref,
                'mtxt'              => $mtxt,
                'require_dialog'    => true
            ]
        );
        return $response;
    }
)->setName('texts')->add($authenticate);

$app->post(
    '/texts',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $texts = new Texts($this->texts_fields, $this->preferences);

        //set the language
        if (isset($post['sel_lang'])) {
            $cur_lang = $post['sel_lang'];
        }
        //set the text entry
        if (isset($post['sel_ref'])) {
            $cur_ref = $post['sel_ref'];
        }

        $mtxt = $texts->getTexts($cur_ref, $cur_lang);
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
