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

//galette's dashboard
$app->get(
    '/' . _T("dashboard"),
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
    function ($request, $response) use ($app, &$session) {
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
            foreach ($prefs_fields as $fieldname ) {
                if (isset($_POST[$fieldname])) {
                    $value=trim($_POST[$fieldname]);
                } else {
                    $value="";
                }

                $error_detected = array();
                $success_detected = array();

                // now, check validity
                if ( $value != '' ) {
                    switch ( $fieldname ) {
                    case 'pref_email':
                        if ( GALETTE_MODE === 'DEMO' ) {
                            Analog::log(
                                'Trying to set pref_email while in DEMO.',
                                Analog::WARNING
                            );
                        } else {
                            if ( !GaletteMail::isValidEmail($value) ) {
                                $error_detected[] = _T("- Non-valid E-Mail address!");
                            }
                        }
                        break;
                    case 'pref_admin_login':
                        if ( GALETTE_MODE === 'DEMO' ) {
                            Analog::log(
                                'Trying to set superadmin login while in DEMO.',
                                Analog::WARNING
                            );
                        } else {
                            if ( strlen($value) < 4 ) {
                                $error_detected[] = _T("- The username must be composed of at least 4 characters!");
                            } else {
                                //check if login is already taken
                                if ($this->login->loginExists($value)) {
                                    $error_detected[] = _T("- This username is already used by another member !");
                                }
                            }
                        }
                        break;
                    case 'pref_numrows':
                        if ( !is_numeric($value) || $value < 0 ) {
                            $error_detected[] = "<li>"._T("- The numbers and measures have to be integers!")."</li>";
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
                        if ( $fieldname=='pref_numrows' && $value=='0' ) {
                            $value = '10';
                        }
                        if ( !is_numeric($value) || $value < 0 ) {
                            $error_detected[] = _T("- The numbers and measures have to be integers!");
                        }
                        break;
                    case 'pref_card_tcol':
                        // Set strip text color to white
                        if ( ! preg_match("/#([0-9A-F]{6})/i", $value) ) {
                            $value = '#FFFFFF';
                        }
                        break;
                    case 'pref_card_scol':
                    case 'pref_card_bcol':
                    case 'pref_card_hcol':
                        // Set strip background colors to black
                        if ( !preg_match("/#([0-9A-F]{6})/i", $value) ) {
                            $value = '#000000';
                        }
                        break;
                    case 'pref_admin_pass':
                        if ( GALETTE_MODE == 'DEMO' ) {
                            Analog::log(
                                'Trying to set superadmin pass while in DEMO.',
                                Analog::WARNING
                            );
                        } else {
                            if ( strlen($value) < 4 ) {
                                $error_detected[] = _T("- The password must be of at least 4 characters!");
                            }
                        }
                        break;
                    case 'pref_membership_ext':
                        if ( !is_numeric($value) || $value < 0 ) {
                            $error_detected[] = _T("- Invalid number of months of membership extension.");
                        }
                        break;
                    case 'pref_beg_membership':
                        $beg_membership = explode("/", $value);
                        if ( count($beg_membership) != 2 ) {
                            $error_detected[] = _T("- Invalid format of beginning of membership.");
                        } else {
                            $now = getdate();
                            if ( !checkdate($beg_membership[1], $beg_membership[0], $now['year']) ) {
                                $error_detected[] = _T("- Invalid date for beginning of membership.");
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
            if ( GALETTE_MODE !== 'DEMO'
                && isset($insert_values['pref_mail_method'])
            ) {
                if ( $insert_values['pref_mail_method'] > GaletteMail::METHOD_DISABLED ) {
                    if ( !isset($insert_values['pref_email_nom'])
                        || $insert_values['pref_email_nom'] == ''
                    ) {
                        $error_detected[] = _T("- You must indicate a sender name for emails!");
                    }
                    if ( !isset($insert_values['pref_email'])
                        || $insert_values['pref_email'] == ''
                    ) {
                        $error_detected[] = _T("- You must indicate an email address Galette should use to send emails!");
                    }
                    if ( $insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP ) {
                        if ( !isset($insert_values['pref_mail_smtp_host'])
                            || $insert_values['pref_mail_smtp_host'] == ''
                        ) {
                            $error_detected[] = _T("- You must indicate the SMTP server you want to use!");
                        }
                    }
                    if ( $insert_values['pref_mail_method'] == GaletteMail::METHOD_GMAIL
                        || ( $insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
                        && $insert_values['pref_mail_smtp_auth'] )
                    ) {
                        if ( !isset($insert_values['pref_mail_smtp_user'])
                            || trim($insert_values['pref_mail_smtp_user']) == ''
                        ) {
                            $error_detected[] = _T("- You must provide a login for SMTP authentication.");
                        }
                        if ( !isset($insert_values['pref_mail_smtp_password'])
                            || ($insert_values['pref_mail_smtp_password']) == ''
                        ) {
                            $error_detected[] = _T("- You must provide a password for SMTP authentication.");
                        }
                    }
                }
            }

            if ( isset($insert_values['pref_beg_membership'])
                && $insert_values['pref_beg_membership'] != ''
                && isset($insert_values['pref_membership_ext'])
                && $insert_values['pref_membership_ext'] != ''
            ) {
                $error_detected[] = _T("- Default membership extention and beginning of membership are mutually exclusive.");
            }

            // missing required fields?
            while ( list($key, $val) = each($required) ) {
                if ( !isset($pref[$key]) ) {
                    $error_detected[] = _T("- Mandatory field empty.")." ".$key;
                } elseif ( isset($pref[$key]) ) {
                    if (trim($pref[$key])=='') {
                        $error_detected[] = _T("- Mandatory field empty.")." ".$key;
                    }
                }
            }

            if (GALETTE_MODE !== 'DEMO' ) {
                // Check passwords. MD5 hash will be done into the Preferences class
                if (strcmp($insert_values['pref_admin_pass'], $_POST['pref_admin_pass_check']) != 0) {
                    $error_detected[] = _T("Passwords mismatch");
                }
            }

            //postal adress
            if ( isset($insert_values['pref_postal_adress']) ) {
                $value = $insert_values['pref_postal_adress'];
                if ( $value == Preferences::POSTAL_ADDRESS_FROM_PREFS ) {
                    if ( isset($insert_values['pref_postal_staff_member']) ) {
                        unset($insert_values['pref_postal_staff_member']);
                    }
                } else if ( $value == Preferences::POSTAL_ADDRESS_FROM_STAFF ) {
                    if ( !isset($value) || $value < 1 ) {
                        $error_detected[] = _T("You have to select a staff member");
                    }
                }
            }

            if ( count($error_detected) == 0 ) {
                // update preferences
                while ( list($champ,$valeur) = each($insert_values) ) {
                    if ($this->login->isSuperAdmin()
                        || (!$this->login->isSuperAdmin()
                        && ($champ != 'pref_admin_pass' && $champ != 'pref_admin_login'))
                    ) {
                        if ( ($champ == "pref_admin_pass" && $_POST['pref_admin_pass'] !=  '')
                            || ($champ != "pref_admin_pass")
                        ) {
                            $this->preferences->$champ = $valeur;
                        }
                    }
                }
                //once all values has been updated, we can store them
                if (!$this->preferences->store()) {
                    $error_detected[] = _T("An SQL error has occured while storing preferences. Please try again, and contact the administrator if the problem persists.");
                } else {
                    $success_detected[] = _T("Preferences has been saved.");
                }

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
                                $this->session['logo'] = serialize($this->logo);
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

                if (GALETTE_MODE !== 'DEMO' && isset($_POST['del_logo'])) {
                    if (!$this->logo->delete()) {
                        $error_detected[] = _T("Delete failed");
                    } else {
                        $this->logo = new Logo(); //get default Logo
                        $this->session['logo'] = serialize($this->logo);
                    }
                }

                // Card logo upload
                if ( GALETTE_MODE !== 'DEMO' && isset($_FILES['card_logo']) ) {
                    if ( $_FILES['card_logo']['error'] === UPLOAD_ERR_OK ) {
                        if ( $_FILES['card_logo']['tmp_name'] !='' ) {
                            if ( is_uploaded_file($_FILES['card_logo']['tmp_name']) ) {
                                $res = $print_logo->store($_FILES['card_logo']);
                                if ( $res < 0 ) {
                                    $error_detected[] = $print_logo->getErrorMessage($res);
                                } else {
                                    $print_logo = new PrintLogo();
                                }
                            }
                        }
                    } else if ($_FILES['card_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        Analog::log(
                            $print_logo->getPhpErrorMessage($_FILES['card_logo']['error']),
                            Analog::WARNING
                        );
                        $error_detected[] = $print_logo->getPhpErrorMessage(
                            $_FILES['card_logo']['error']
                        );
                    }
                }

                if ( GALETTE_MODE !== 'DEMO' && isset($_POST['del_card_logo']) ) {
                    if ( !$print_logo->delete() ) {
                        $error_detected[] = _T("Delete failed");
                    } else {
                        $print_logo = new PrintLogo();
                    }
                }
            }

            if ( count($error_detected) > 0 ) {
                $this->flash->addMessage(
                    'error_detected',
                    $error_detected
                );
            }

            if ( count($success_detected) > 0 ) {
                $this->flash->addMessage(
                    'success_detected',
                    $success_detected
                );
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
                    $success_detected[] = str_replace(
                        '%name',
                        $_GET['activate'],
                        _T("Plugin %name has been enabled")
                    );
                    $reload_plugins = true;
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if (isset($_GET['deactivate'])) {
                try {
                    $plugins->deactivateModule($_GET['deactivate']);
                    $success_detected[] = str_replace(
                        '%name',
                        $_GET['deactivate'],
                        _T("Plugin %name has been disabled")
                    );
                    $reload_plugins = true;
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
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
)->add($authenticate)/*->conditions(
    array(
        'option'    => '(page|order|reset)'
    )
)*/;

//mailings management
$app->get(
    '/mailings[/{option}/{value}]',
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
)->add($authenticate)/*->conditions(
    array(
        'option'    => '(page|order)'
    )
)*/;
