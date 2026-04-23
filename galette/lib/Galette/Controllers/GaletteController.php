<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use DI\Attribute\Inject;
use Galette\Controllers\Attributes\Route;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Social;
use Galette\Repository\PaymentTypes;
use Galette\Util\Telemetry;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\Logo;
use Galette\Core\PrintLogo;
use Galette\Core\Galette;
use Galette\Core\GaletteMail;
use Galette\Core\SysInfos;
use Galette\Entity\FieldsCategories;
use Galette\Entity\Status;
use Galette\Entity\Texts;
use Galette\Filters\MembersList;
use Galette\IO\Charts;
use Galette\Repository\Members;
use Galette\Repository\Reminders;

use function Safe\dir;
use function Safe\file_get_contents;
use function Safe\file_put_contents;

/**
 * Galette main controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class GaletteController extends AbstractController
{
    #[Inject]
    protected Status $status;

    /**
     * Main route
     */
    #[Route(
        name: 'slash',
        pattern: '/',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function slash(Request $request, Response $response): Response
    {
        return $this->galetteRedirect($request, $response);
    }

    /**
     * System information
     */
    #[Route(
        name: 'sysinfos',
        pattern: '/system-information',
        methods: ['GET']
    )]
    public function systemInformation(Response $response, SysInfos $sysinfos): Response
    {
        $raw_infos = $sysinfos->getRawData(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        // display page
        $this->view->render(
            $response,
            'pages/sysinfos.html.twig',
            [
                'page_title'    => _T("System information"),
                'rawinfos'      => $raw_infos,
                'documentation' => 'usermanual/avancee.html#galette-modes'
            ]
        );
        return $response;
    }

    /**
     * Dashboard page
     */
    #[Route(
        name: 'dashboard',
        pattern: '/dashboard',
        methods: ['GET']
    )]
    public function dashboard(Request $request, Response $response, Telemetry $telemetry): Response
    {
        $news = Galette::getNews();
        $params = [
            'page_title'        => _T("Dashboard"),
            'contentcls'        => 'desktop',
            'news'              => $news,
            'show_dashboard'    => $request->getCookieParams()['show_galette_dashboard'],
            'documentation'     => 'usermanual'
        ];

        $hide_telemetry = true;
        if ($this->login->isAdmin()) {
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
            'pages/desktop.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Preferences page
     */
    #[Route(
        name: 'preferences',
        pattern: '/preferences',
        methods: ['GET']
    )]
    public function preferences(Request $request, Response $response, PaymentTypes $ptypes, Members $m): Response
    {
        // flagging required fields
        $required = $this->preferences->getRequiredFields($this->login);

        $prefs_fields = $this->preferences->getFieldsNames();
        // collect data
        $pref = [];
        foreach ($prefs_fields as $fieldname) {
            $pref[$fieldname] = $this->preferences->$fieldname;
        }

        //on error, user values are stored into session
        if ($this->session->entered_preferences) {
            $pref = array_merge($pref, $this->session->entered_preferences);
            $this->session->entered_preferences = null;
        }

        //List available themes
        $themes = [];
        $d = dir(GALETTE_THEMES_PATH);
        while (($entry = $d->read()) !== false) {
            $full_entry = GALETTE_THEMES_PATH . $entry;
            if (
                $entry != '.'
                && $entry != '..'
                && is_dir($full_entry)
                && file_exists($full_entry . '/page.html.twig')
            ) {
                $themes[] = $entry;
            }
        }
        $d->close();

        //List payment types for default to be selected
        $ptlist = $ptypes->getList(false);

        //Active tab on page
        $tab = $request->getQueryParams()['tab'] ?? 'general';

        // display page
        $this->view->render(
            $response,
            'pages/preferences.html.twig',
            [
                'page_title'            => _T("Settings"),
                'staff_members'         => $m->getStaffMembersList(true),
                'time'                  => time(),
                'pref'                  => $pref,
                'pref_numrows_options'  => [
                    10 => '10',
                    20 => '20',
                    50 => '50',
                    100 => '100'
                ],
                'print_logo'            => $this->print_logo,
                'required'              => $required,
                'themes'                => $themes,
                'statuts'               => $this->status->getList(),
                'accounts_options'      => [
                    Members::ALL_ACCOUNTS       => _T("All accounts"),
                    Members::ACTIVE_ACCOUNT     => _T("Active accounts"),
                    Members::INACTIVE_ACCOUNT   => _T("Inactive accounts")
                ],
                'paymenttypes'          => $ptlist,
                'osocials'              => new Social($this->zdb),
                'tab'                   => $tab,
                'documentation'         => 'usermanual/preferences.html'
            ]
        );
        return $response;
    }

    /**
     * Store preferences
     */
    #[Route(
        name: 'store-preferences',
        pattern: '/preferences',
        methods: ['POST']
    )]
    public function storePreferences(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];
        $success_detected = [];

        // Validation
        if (isset($post['valid']) && $post['valid'] == '1') {
            if ($this->preferences->check($post, $this->login)) {
                if (!$this->preferences->store()) {
                    $error_detected[] = _T("An SQL error has occurred while storing preferences. Please try again, and contact the administrator if the problem persists.");
                } else {
                    $success_detected[] = _T("Preferences has been saved.");
                }

                if (!Galette::isDemo()) {
                    $files = $request->getUploadedFiles();
                    //handle logo
                    if (isset($files['logo'])) {
                        $file_res = $this->preferences->handleLogo($this->logo, $files['logo']);
                        if (is_array($file_res)) {
                            $error_detected = array_merge($error_detected, $file_res);
                        }
                    }
                    if (isset($post['del_logo']) && !$this->logo->delete()) {
                        $error_detected[] = _T("Delete failed");
                    }

                    //handle card logo
                    if (isset($files['card_logo'])) {
                        $file_res = $this->preferences->handleLogo($this->print_logo, $files['card_logo']);
                        if (is_array($file_res)) {
                            $error_detected = array_merge($error_detected, $file_res);
                        }
                    }
                    if (isset($post['del_card_logo']) && !$this->print_logo->delete()) {
                        $error_detected[] = _T("Delete failed");
                    }

                    if (!count($error_detected)) {
                        $this->logo = new Logo();
                        $this->print_logo = new PrintLogo();
                    }

                    $res = $this->preferences->handleFiles($files);
                    if ($res !== true) {
                        $error_detected = array_merge($error_detected, $res);
                    }
                }
            } else {
                $error_detected = $this->preferences->getErrors();
            }

            if (count($error_detected) > 0) {
                $this->session->entered_preferences = $post;
            }
        }
        $tab = isset($post['tab']) && $post['tab'] != 'general' ? '?tab=' . $post['tab'] : '';

        // Reset dark mode CSS file if required
        $this->preferences->resetDarkCss($this->flash);
        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('preferences') . $tab,
            successes: $success_detected,
            errors: $error_detected
        );
    }

    /**
     * Test mail parameters
     */
    #[Route(
        name: 'testEmail',
        pattern: '/test/email',
        methods: ['GET']
    )]
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
            $dest = ($get['adress'] ?? $this->preferences->pref_email_newadh);
            if (GaletteMail::isValidEmail($dest)) {
                $mail = new GaletteMail($this->preferences);
                $mail->setSubject(_T('Test message'));
                $mail->setRecipients(
                    [
                        $dest => _T("Galette admin")
                    ]
                );
                $mail->setMessage(_T('Test message.'));
                $sent = $mail->send();

                if ($sent) {
                    $this->flash->addMessage(
                        'success_detected',
                        sprintf(
                            _T('An email has been sent to %1$s'),
                            $dest
                        )
                    );
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        sprintf(
                            _T('No email sent to %1$s'),
                            $dest
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

        if (!$this->isAjax($request)) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('preferences'));
        } else {
            return $this->withJson(
                $response,
                [
                    'sent'  => $sent
                ]
            );
        }
    }

    /**
     * Charts page
     */
    #[Route(
        name: 'charts',
        pattern: '/charts',
        methods: ['GET']
    )]
    public function charts(Response $response): Response
    {
        $charts = new Charts(
            [
                Charts::MEMBERS_STATUS_PIE,
                Charts::MEMBERS_STATEDUE_PIE,
                Charts::CONTRIBS_TYPES_PIE,
                Charts::COMPANIES_OR_NOT,
                Charts::CONTRIBS_ALLTIME
            ]
        );

        // display page
        $this->view->render(
            $response,
            'pages/charts.html.twig',
            [
                'page_title'        => _T("Charts"),
                'charts'            => $charts->getCharts(),
                'require_charts'    => true
            ]
        );
        return $response;
    }

    /**
     * Core fields configuration page
     */
    #[Route(
        name: 'configureCoreFields',
        pattern: '/fields/core/configure',
        methods: ['GET']
    )]
    public function configureCoreFields(Response $response): Response
    {
        $fc = $this->fields_config;

        $params = [
            'page_title'            => _T("Core fields"),
            'time'                  => time(),
            'categories'            => FieldsCategories::getList($this->zdb),
            'categorized_fields'    => $fc->getCategorizedFields(),
            'non_required'          => $fc->getNonRequired(),
            'perm_names'            => FieldsConfig::getPermissionsList(),
            'documentation'         => 'usermanual/configuration.html#mandatory-fields-and-access-rights'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/configuration_core_fields.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Process core fields configuration
     */
    #[Route(
        name: 'storeCoreFieldsConfig',
        pattern: '/fields/core/configure',
        methods: ['POST']
    )]
    public function storeCoreFieldsConfig(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $fc = $this->fields_config;

        $current_cat = 0;
        $res = [];
        foreach ($post['fields'] as $field) {
            if ($current_cat != $post[$field . '_category']) {
                //set new current category
                $current_cat = $post[$field . '_category'];
            }

            $required = $post[$field . '_required'] ?? false;

            $res[$current_cat][] = [
                'field_id'      =>  $field,
                'label'         =>  htmlspecialchars((string)$post[$field . '_label'], ENT_QUOTES),
                'category'      =>  $post[$field . '_category'],
                'visible'       =>  $post[$field . '_visible'],
                'required'      =>  $required,
                'width_in_forms'  =>  $post[$field . '_width_in_forms'] ?? 1
            ];
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
            ->withHeader('Location', $this->routeparser->urlFor('configureCoreFields'));
    }

    /**
     * Core lists configuration page
     *
     * @param string $table Table name
     */
    #[Route(
        name: 'configureListFields',
        pattern: '/lists/{table}/configure',
        methods: ['GET']
    )]
    public function configureListFields(Response $response, string $table): Response
    {
        $lc = $this->lists_config;

        $params = [
            'page_title'    => _T("Core lists"),
            'table'         => $table,
            'time'          => time(),
            'listed_fields' => $lc->getListedFields(),
            'remaining_fields'  => $lc->getRemainingFields(),
            'permissions' => $lc::getPermissionsList(),
            'documentation'  => 'usermanual/configuration.html#list-fields'
        ];

        // display page
        $this->view->render(
            $response,
            'pages/configuration_core_lists.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Process list fields configuration
     */
    #[Route(
        name: 'storeListFields',
        pattern: '/lists/{table}/configure',
        methods: ['POST']
    )]
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
            ->withHeader('Location', $this->routeparser->urlFor('configureListFields', $this->getArgs($request)));
    }

    /**
     * Reminders page
     */
    #[Route(
        name: 'reminders',
        pattern: '/reminders',
        methods: ['GET']
    )]
    public function reminders(Response $response, Texts $texts, Members $members): Response
    {
        $previews = [
            'impending' => $texts->getTexts('impendingduedate', $this->preferences->pref_lang),
            'late'      => $texts->getTexts('lateduedate', $this->preferences->pref_lang)
        ];

        $reminders = $members->getRemindersCount();

        // display page
        $this->view->render(
            $response,
            'pages/reminder.html.twig',
            [
                'page_title'                => _T("Reminders"),
                'previews'                  => $previews,
                'count_impending'           => $reminders['impending'],
                'count_impending_nomail'    => $reminders['nomail']['impending'],
                'count_late'                => $reminders['late'],
                'count_late_nomail'         => $reminders['nomail']['late'],
                'documentation'             => 'usermanual/contributions.html#reminders'
            ]
        );
        return $response;
    }

    /**
     * Send reminders
     */
    #[Route(
        name: 'doReminders',
        pattern: '/reminders',
        methods: ['POST']
    )]
    public function doReminders(Request $request, Response $response): Response
    {
        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        $post = $request->getParsedBody();
        $texts = new Texts($this->preferences, $this->routeparser);
        $selected = null;
        if (isset($post['reminders'])) {
            $selected = $post['reminders'];
        }
        $reminders = new Reminders($selected);

        $labels = false;
        $labels_members = [];
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
                        ->setRouteParser($this->routeparser)
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
                    $session_var = $this->getFilterName('reminders_labels');
                    $labels_filters = new MembersList();
                    $labels_filters->selected = $labels_members;
                    $this->session->$session_var = $labels_filters;
                    return $response
                        ->withStatus(307)
                        ->withHeader(
                            'Location',
                            $this->routeparser->urlFor('pdf-members-labels') . '?session_var=' . $session_var
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

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('reminders'),
            successes: $success_detected,
            warnings: $warning_detected,
            errors: $error_detected
        );
    }

    /**
     * Main route
     *
     * @param string $membership Either 'late' or 'nearly'
     * @param string $mail       Either 'withmail' or 'withoutmail'
     */
    #[Route(
        name: 'reminders-filter',
        pattern: '/members/reminder-filter/{membership:nearly|late}/{mail:withmail|withoutmail}',
        methods: ['GET']
    )]
    public function filterReminders(Response $response, string $membership, string $mail): Response
    {
        //always reset filters
        $filters = new MembersList();
        $filters->filter_account = Members::ACTIVE_ACCOUNT;

        $membership = ($membership === 'nearly'
            ? Members::MEMBERSHIP_NEARLY : Members::MEMBERSHIP_LATE);
        $filters->membership_filter = $membership;

        $mail = ($mail === 'withmail'
            ? Members::FILTER_W_EMAIL : Members::FILTER_WO_EMAIL);
        $filters->email_filter = $mail;

        $this->session->{$this->getFilterName(Crud\MembersController::getDefaultFilterName())} = $filters;

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('members')
        );
    }

    /**
     * Direct document page
     */
    #[Route(
        name: 'directlink',
        pattern: '/document/download/{hash}',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function documentLink(Response $response, string $hash): Response
    {
        // display page
        $this->view->render(
            $response,
            'pages/directlink.html.twig',
            [
                'hash'          => $hash,
                'page_title'    => _T('Download document')
            ]
        );
        return $response;
    }

    /**
     * Empty route (for default requests on favicon.ico, robots.txt, ...)
     */
    #[Route(
        name: 'defaultEmpty',
        pattern: '/{url:favicon.ico|robots.txt}',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function empty(Response $response): Response
    {
        return $response;
    }

    /**
     * Store dark mode CSS in cache directory.
     */
    #[Route(
        name: 'writeDarkCSS',
        pattern: '/write-dark-css',
        methods: ['POST'],
        requiresAuth: false
    )]
    public function writeDarkCss(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        file_put_contents(GALETTE_CACHE_DIR . '/dark.css', $post);
        return $response->withStatus(200);
    }

    /**
     * Serve cached dark mode CSS.
     */
    #[Route(
        name: 'getDarkCSS',
        pattern: '/get-dark-css',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function getDarkCss(Response $response): Response
    {
        $cssfile = GALETTE_CACHE_DIR . '/dark.css';
        if (file_exists($cssfile)) {
            $response = $response->withHeader('Content-type', 'text/css');
            $response->getBody()->write(file_get_contents($cssfile));
        }
        return $response;
    }
}
