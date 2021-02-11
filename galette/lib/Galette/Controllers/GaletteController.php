<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette main controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
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
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\Logo;
use Galette\Core\PrintLogo;
use Galette\Core\GaletteMail;
use Galette\Core\SysInfos;
use Galette\Entity\Contribution;
use Galette\Entity\FieldsCategories;
use Galette\Entity\Status;
use Galette\Entity\Texts;
use Galette\Filters\MembersList;
use Galette\IO\News;
use Galette\IO\Charts;
use Galette\IO\PdfMembersCards;
use Galette\IO\PdfContribution;
use Galette\Repository\Members;
use Galette\Repository\Reminders;
use Analog\Analog;

/**
 * Galette main controller
 *
 * @category  Controllers
 * @name      GaletteController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
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
     *
     * @return Response
     */
    public function slash(Request $request, Response $response): Response
    {
        return $this->galetteRedirect($request, $response);
    }

    /**
     * System information
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function systemInformation(Request $request, Response $response): Response
    {
        $sysinfos = new SysInfos();
        $raw_infos = $sysinfos->getRawData(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        // display page
        $this->view->render(
            $response,
            'sysinfos.tpl',
            array(
                'page_title'    => _T("System information"),
                'rawinfos'      => $raw_infos
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
    public function dashboard(Request $request, Response $response): Response
    {
        $news = new News($this->preferences->pref_rss_url);

        $params = [
            'page_title'        => _T("Dashboard"),
            'contentcls'        => 'desktop',
            'news'              => $news->getPosts(),
            'show_dashboard'    => $_COOKIE['show_galette_dashboard']
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
                || isset($_COOKIE['hide_galette_telemetry']) && $_COOKIE['hide_galette_telemetry'];
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
    public function preferences(Request $request, Response $response): Response
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

        $prefs_fields = $this->preferences->getFieldsNames();
        // collect data
        foreach ($prefs_fields as $fieldname) {
            $pref[$fieldname] = $this->preferences->$fieldname;
        }

        //on error, user values are stored into session
        if ($this->session->entered_preferences) {
            $pref = array_merge($pref, $this->session->entered_preferences);
            $this->session->entered_preferences = null;
        }

        //List available themes
        $themes = array();
        $d = dir(GALETTE_THEMES_PATH);
        while (($entry = $d->read()) !== false) {
            $full_entry = GALETTE_THEMES_PATH . $entry;
            if (
                $entry != '.'
                && $entry != '..'
                && is_dir($full_entry)
                && file_exists($full_entry . '/page.tpl')
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
    public function storePreferences(Request $request, Response $response): Response
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
                if (GALETTE_MODE !== 'DEMO' && isset($_FILES['logo'])) {
                    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['logo']['tmp_name'] != '') {
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
                        if ($_FILES['card_logo']['tmp_name'] != '') {
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
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('preferences'));
    }

    /**
     * Test mail parameters
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function testEmail(Request $request, Response $response): Response
    {
        $sent = false;
        if (!$this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
            $this->flash->addMessage(
                'error_detected',
                _T("You asked Galette to send a test email, but email has been disabled in the preferences.")
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
    public function charts(Request $request, Response $response): Response
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
     * Core fields configuration page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function configureCoreFields(Request $request, Response $response): Response
    {
        $fc = $this->fields_config;

        $params = [
            'page_title'            => _T("Fields configuration"),
            'time'                  => time(),
            'categories'            => FieldsCategories::getList($this->zdb),
            'categorized_fields'    => $fc->getCategorizedFields(),
            'non_required'          => $fc->getNonRequired()
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
    public function storeCoreFieldsConfig(Request $request, Response $response): Response
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
                'label'     =>  htmlspecialchars($post[$field . '_label'], ENT_QUOTES),
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
     * Core lists configuration page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $table    Tbale name
     *
     * @return Response
     */
    public function configureListFields(Request $request, Response $response, string $table): Response
    {
        //TODO: check if type table exists

        $lc = $this->lists_config;

        $params = [
            'page_title'    => _T("Lists configuration"),
            'table'         => $table,
            'time'          => time(),
            'listed_fields' => $lc->getListedFields(),
            'remaining_fields'  => $lc->getRemainingFields()
        ];

        // display page
        $this->view->render(
            $response,
            'config_lists.tpl',
            $params
        );
        return $response;
    }

    /**
     * Process list fields configuration
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storeListFields(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $lc = $this->lists_config;
        $fields = [];
        foreach ($post['fields'] as $field) {
            $fields[] = $lc->getField($field);
        }
        $success = $lc->setListFields($fields);

        if ($success === true) {
            $this->flash->addMessage(
                'success_detected',
                _T("List configuration has been successfully stored")
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("An error occurred while storing list configuration :(")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('configureListFields', $this->getArgs($request)));
    }

    /**
     * Reminders page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function reminders(Request $request, Response $response): Response
    {
        $texts = new Texts($this->preferences, $this->router);

        $previews = array(
            'impending' => $texts->getTexts('impendingduedate', $this->preferences->pref_lang),
            'late'      => $texts->getTexts('lateduedate', $this->preferences->pref_lang)
        );

        $members = new Members();
        $reminders = $members->getRemindersCount();

        // display page
        $this->view->render(
            $response,
            'reminder.tpl',
            [
                'page_title'                => _T("Reminders"),
                'previews'                  => $previews,
                'count_impending'           => $reminders['impending'],
                'count_impending_nomail'    => $reminders['nomail']['impending'],
                'count_late'                => $reminders['late'],
                'count_late_nomail'         => $reminders['nomail']['late']
            ]
        );
        return $response;
    }

    /**
     * Main route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doReminders(Request $request, Response $response): Response
    {
        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        $post = $request->getParsedBody();
        $texts = new Texts($this->preferences, $this->router);
        $selected = null;
        if (isset($post['reminders'])) {
            $selected = $post['reminders'];
        }
        $reminders = new Reminders($selected);

        $labels = false;
        $labels_members = array();
        if (isset($post['reminder_wo_mail'])) {
            $labels = true;
        }

        $list_reminders = $reminders->getList($this->zdb, $labels);
        if (count($list_reminders) == 0) {
            $warning_detected[] = _T("No reminder to send for now.");
        } else {
            foreach ($list_reminders as $reminder) {
                if ($labels === false) {
                    $reminder
                        ->setDb($this->zdb)
                        ->setLogin($this->login)
                        ->setPreferences($this->preferences)
                        ->setRouter($this->router)
                    ;
                    //send reminders by email
                    $sent = $reminder->send($texts, $this->history, $this->zdb);

                    if ($sent === true) {
                        $success_detected[] = $reminder->getMessage();
                    } else {
                        $error_detected[] = $reminder->getMessage();
                    }
                } else {
                    //generate labels for members without email address
                    $labels_members[] = $reminder->member_id;
                }
            }

            if ($labels === true) {
                if (count($labels_members) > 0) {
                    $session_var = 'filters_reminders_labels';
                    $labels_filters = new MembersList();
                    $labels_filters->selected = $labels_members;
                    $this->session->$session_var = $labels_filters;
                    return $response
                        ->withStatus(307)
                        ->withHeader(
                            'Location',
                            $this->router->pathFor('pdf-members-labels') . '?session_var=' . $session_var
                        );
                } else {
                    $error_detected[] = _T("There are no member to proceed.");
                }
            }

            if (count($error_detected) > 0) {
                array_unshift(
                    $error_detected,
                    _T("Reminder has not been sent:")
                );
            }

            if (count($success_detected) > 0) {
                array_unshift(
                    $success_detected,
                    _T("Sent reminders:")
                );
            }
        }

        //flash messages if any
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
        }
        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage('warning_detected', $warning);
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage('success_detected', $success);
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('reminders'));
    }

    /**
     * Main route
     *
     * @param Request  $request    PSR Request
     * @param Response $response   PSR Response
     * @param string   $membership Either 'late' or 'nearly'
     * @param string   $mail       Either 'withmail' or 'withoutmail'
     *
     * @return Response
     */
    public function filterReminders(Request $request, Response $response, string $membership, string $mail): Response
    {
        //always reset filters
        $filters = new MembersList();
        $filters->filter_account = Members::ACTIVE_ACCOUNT;

        $membership = ($membership === 'nearly' ?
            Members::MEMBERSHIP_NEARLY : Members::MEMBERSHIP_LATE);
        $filters->membership_filter = $membership;

        //TODO: filter on reminder may take care of parent email as well
        $mail = ($mail === 'withmail' ?
            Members::FILTER_W_EMAIL : Members::FILTER_WO_EMAIL);
        $filters->email_filter = $mail;

        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
    }

    /**
     * Direct document page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $hash     Hash
     *
     * @return Response
     */
    public function documentLink(Request $request, Response $response, string $hash): Response
    {
        // display page
        $this->view->render(
            $response,
            'directlink.tpl',
            array(
                'hash'          => $hash,
                'page_title'    => _T('Download document')
            )
        );
        return $response;
    }
}
